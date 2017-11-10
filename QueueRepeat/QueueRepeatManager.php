<?php

namespace QueueRepeat;

use PhpAmqpLib\Channel\AMQPChannel,
    PhpAmqpLib\Wire\AMQPTable,
    PhpAmqpLib\Message\AMQPMessage;
use QueueRepeat\Exception\QueueRepeatException;
use Yriveiro\Backoff\Backoff,
    Yriveiro\Backoff\BackoffException;

class QueueRepeatManager
{
    /**
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * @var string
     */
    protected $queueName;

    /**
     * @var string
     */
    protected $exchangeName;

    /**
     * @param AMQPChannel $channel
     * @param string $queueName
     * @param string $exchangeName
     */
    public function init(AMQPChannel $channel, $queueName, $exchangeName)
    {
        $this->channel = $channel;
        $this->queueName = $queueName;
        $this->exchangeName = $exchangeName;
    }

    /**
     * @param array $messageHeaders
     * @param string $routingKey
     * @param array $data
     * @param int $retryMax
     *
     * @return array
     *
     * @throws QueueRepeatException
     */
    public function resendMessage($messageHeaders, $routingKey, $data, $retryMax)
    {
        if($retryMax == 1){
            throw new QueueRepeatException('Attempt resendMessage must be > 1');
        }
        $attempt = 1;
        if(!empty($messageHeaders['application_headers'])){
            $propertyApplicationHeaders = $messageHeaders['application_headers'];
            if(!empty($propertyApplicationHeaders['x-death'])){
                $attempt = count($propertyApplicationHeaders['x-death']);
                /*if($attempt > $retryMax){
                    throw new QueueRepeatException('Exceeded the maximum number of repeats: '.$retryMax);
                }*/
            }
        } else {
            $messageHeaders['application_headers'] = [];
        }
        try {
            $backoff = new Backoff(['maxAttempts' => $retryMax]);
            $delay = (int) floor($backoff->equalJitter($attempt) / 1000);
        } catch (BackoffException $e) {
            throw new QueueRepeatException($e->getMessage(), $e->getCode(), $e);
        }
        return $this->dispatch($messageHeaders, $routingKey, $data, $delay);
    }

    /**
     * @param array $messageHeaders
     * @param string $routingKey
     * @param array $data
     * @param int $delay
     *
     * @return array
     */
    protected function dispatch($messageHeaders, $routingKey, $data, $delay)
    {
        $deadLetterRoutingKey = isset($messageHeaders['dead-letter-routing-key']) ? $messageHeaders['dead-letter-routing-key'] : $routingKey;

        $delayQueue = $this->queueName.'.delay.'.$delay;
        $delayExchange = $this->exchangeName.'.delay';

        /**
         * Declares exchange
         *
         * @param string $exchange
         * @param string $type
         * @param bool $passive
         * @param bool $durable
         * @param bool $auto_delete
         * @param bool $internal
         * @param bool $nowait
         * @param array $arguments
         * @param int $ticket
         * @return mixed|null
         */
        $this->channel->exchange_declare($delayExchange, 'topic', false, true, false);

        $messageArguments = [
            'x-message-ttl' => $delay * 1000,            // message lifetime -> (2^32-1) мс
            'x-dead-letter-exchange' => $this->exchangeName, // where messages will be transferred
            'x-expires' => $delay * 1000 + 10000,        // lifetime queue
        ];
        $messageArguments['x-dead-letter-routing-key'] = $deadLetterRoutingKey;

        /**
         * Declares queue, creates if needed
         *
         * @param string $queue
         * @param bool $passive
         * @param bool $durable
         * @param bool $exclusive
         * @param bool $auto_delete
         * @param bool $nowait
         * @param null $arguments
         * @param null $ticket
         * @return mixed|null
         */
        $this->channel->queue_declare($delayQueue, false, true, false, false, false, new AMQPTable($messageArguments));
        $routingKey = $routingKey.'.delay.'.$delay; // hard name because duplication postfix after received to this function ($routingKey + '.reload')

        /**
         * Binds queue to an exchange
         *
         * @param string $queue
         * @param string $exchange
         * @param string $routing_key
         * @param bool $nowait
         * @param array $arguments
         * @param int $ticket
         * @return mixed|null
         */
        $this->channel->queue_bind($delayQueue, $delayExchange, $routingKey);

        /**
         * @param array $properties Message property content
         * @param array $propertyTypes Message property definitions
         */
        $AMQPMessage = new AMQPMessage(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), array('content_type' => 'application/json', 'delivery_mode' => 2));
        if(!empty($messageHeaders['application_headers']['x-death'])){
            $headers = new AMQPTable($messageHeaders['application_headers']);
            $AMQPMessage->set('application_headers', $headers);
        }

        /**
         * Publishes a message
         *
         * @param AMQPMessage $msg
         * @param string $exchange
         * @param string $routing_key
         * @param bool $mandatory
         * @param bool $immediate
         * @param int $ticket
         */
        $this->channel->basic_publish($AMQPMessage, $delayExchange, $routingKey);

        return [
            'routingKey' => $routingKey,
            'exchange' => $delayExchange,
            'queue' => $delayQueue,
        ];
    }
}
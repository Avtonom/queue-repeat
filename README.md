# queue-repeat
Queue Repeat Manager to RabbitMQ.

Page bundle: https://github.com/Avtonom/queue-repeat

if you use symfony https://github.com/Avtonom/delay-exponential-backoff-bundle

Use delay Exponential expression

install
```
    $ composer require "avtonom/queue-repeat"
```

Use simple:
```php
    /** \PhpAmqpLib\Message\AMQPMessage $msg **/
    $messageHeaders = $msg->get('application_headers')->getNativeData();
    $connection = $container->get('@old_sound_rabbit_mq.connection.default');
    
    $repeatManager = new QueueRepeat\QueueRepeatManager();
    $repeatManager->init($connection->channel(), $queue, $exchange);
    try {
        $repeatManager->resendMessage($messageHeaders, $routingKey, $data);
    } catch (QueueRepeatException $e) { }
```

Use full code:

config
```php
    /** \PhpAmqpLib\Message\AMQPMessage $msg **/
    $messageHeaders = $msg->get('application_headers')->getNativeData();
```


QueueProvider
```php
    /**
     * @return QueueRepeatManager
     */
    public function getRepeatManager()
    {
        return $this->repeatManager;
    }

    /**
     * @param QueueRepeatManager $repeatManager
     */
    public function setRepeatManager($repeatManager)
    {
        $repeatManager->init($this->channel, $this->queue, $this->exchange);
        $this->repeatManager = $repeatManager;
    }
```


use

```php
    /**
     * @param array $messageHeaders
     * @param string $routingKey
     * @param array $data
     * @param int $retryMax - 5 then it will be retried if the job processing fails
     * @param int $cap - 1000000 The retryDelay option allows you to progressively delay the job processing on successive retries.
     */
    $this->getQueueProvider()->setRepeatManager(new QueueRepeatManager());
    try {
        $this->getQueueProvider()->getRepeatManager()->resendMessage($messageHeaders, $routingKey, $data, $retryMax, $cap);
    } catch (QueueRepeatException $e) {
        $this->getLogger()->warning('QueueRepeatException: '. $e->getMessage());
    }
```

Read information https://habrahabr.ru/post/227225/

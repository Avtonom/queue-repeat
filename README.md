# queue-repeat
Queue Repeat Manager to RabbitMQ.

Page bundle: https://github.com/Avtonom/queue-repeat

if you use symfony https://github.com/Avtonom/delay-exponential-backoff-bundle

Use delay Exponential expression

install
```
    $ composer require "avtonom/queue-repeat"
```

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

     * @param array $messageHeaders
     * @param string $routingKey
     * @param array $data
     * @param int $retryMax
     * @param int $cap
     
```php
    $this->getQueueProvider()->setRepeatManager(new QueueRepeatManager());
    try {
        $this->getQueueProvider()->getRepeatManager()->resendMessage($messageHeaders, $routingKey, $data, $retryMax, $cap);
    } catch (QueueRepeatException $e) {
        $this->getLogger()->warning('QueueRepeatException: '. $e->getMessage());
    }
```

Read information https://habrahabr.ru/post/227225/

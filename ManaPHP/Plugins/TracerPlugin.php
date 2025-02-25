<?php
namespace ManaPHP\Plugins;

use ManaPHP\Event\EventArgs;
use ManaPHP\Plugin;

/**
 * Class TracerPlugin
 * @package ManaPHP\Plugins
 */
class TracerPlugin extends Plugin
{
    public function __construct()
    {
        $this->eventsManager->attachEvent('redis:connect', [$this, 'onRedisConnect']);
        $this->eventsManager->attachEvent('redis:calling', [$this, 'onRedisCalling']);
        $this->eventsManager->attachEvent('redis:called', [$this, 'onRedisCalled']);

        $this->eventsManager->attachEvent('db:connect', [$this, 'onDbConnect']);
        $this->eventsManager->attachEvent('db:queried', [$this, 'onDbQueried']);
        $this->eventsManager->attachEvent('db:executed', [$this, 'onDbExecuted']);
        $this->eventsManager->attachEvent('db:inserted', [$this, 'onDbInserted']);
        $this->eventsManager->attachEvent('db:begin', [$this, 'onDbBegin']);
        $this->eventsManager->attachEvent('db:rollback', [$this, 'onDbRollback']);
        $this->eventsManager->attachEvent('db:commit', [$this, 'onDbCommit']);
        $this->eventsManager->attachEvent('db:metadata', [$this, 'onDbMetadata']);
        $this->eventsManager->attachEvent('db:abnormal', [$this, 'onDbAbnormal']);

        $this->eventsManager->attachEvent('mailer:sending', [$this, 'onMailerSending']);

        $this->eventsManager->attachEvent('mongodb:connect', [$this, 'onMongodbConnect']);
        $this->eventsManager->attachEvent('mongodb:queried', [$this, 'onMongodbQueried']);
        $this->eventsManager->attachEvent('mongodb:inserted', [$this, 'onMongodbInserted']);
        $this->eventsManager->attachEvent('mongodb:updated', [$this, 'onMongodbUpdated']);
        $this->eventsManager->attachEvent('mongodb:deleted', [$this, 'onMongodbDeleted']);
        $this->eventsManager->attachEvent('mongodb:commanded', [$this, 'onMongodbCommanded']);
        $this->eventsManager->attachEvent('mongodb:bulkInserted', [$this, 'onMongodbBulkInserted']);
        $this->eventsManager->attachEvent('mongodb:bulkUpdated', [$this, 'onMongodbBulkUpdated']);
        $this->eventsManager->attachEvent('mongodb:upserted', [$this, 'onMongodbUpserted']);
        $this->eventsManager->attachEvent('mongodb:bulkUpserted', [$this, 'onMongodbBulkUpserted']);

        $this->eventsManager->attachEvent('httpClient:requesting', [$this, 'onHttpClientRequesting']);
        $this->eventsManager->attachEvent('httpClient:requested', [$this, 'onHttpClientRequested']);

        $this->eventsManager->attachEvent('wsClient:send', [$this, 'onWsClientSend']);
        $this->eventsManager->attachEvent('wsClient:receive', [$this, 'onWsClientReceive']);
    }

    public function onRedisConnect(EventArgs $eventArgs)
    {
        $this->logger->debug(['connect to `:uri`', 'uri' => $eventArgs->data['uri']], 'redis.connect');
    }

    public function onRedisCalling(EventArgs $eventArgs)
    {
        $name = $eventArgs->data['name'];
        $arguments = $eventArgs->data['arguments'];

        if (stripos(',blPop,brPop,brpoplpush,subscribe,psubscribe,', ",$name,") !== false) {
            $this->logger->debug(["\$redis->$name(:args) ... blocking",
                'args' => substr(json_stringify($arguments, JSON_PARTIAL_OUTPUT_ON_ERROR), 1, -1),
            ], 'redis.' . $name);
        }
    }

    public function onRedisCalled(EventArgs $eventArgs)
    {
        $name = $eventArgs->data['name'];
        $arguments = $eventArgs->data['arguments'];
        foreach ($arguments as $k => $v) {
            if (is_string($v) && strlen($v) > 64) {
                $arguments[$k] = substr($v, 0, 64) . '...';
            }
        }
        $arguments = json_stringify($arguments, JSON_PARTIAL_OUTPUT_ON_ERROR);
        $return = json_stringify($eventArgs->data['return'], JSON_PARTIAL_OUTPUT_ON_ERROR);

        $this->logger->debug(["\$redis->$name(:args) => :return",
            'args' => strlen($arguments) > 256 ? substr($arguments, 1, 256) . '...)' : substr($arguments, 1, -1),
            'return' => strlen($return) > 64 ? substr($return, 0, 64) . '...' : $return
        ], 'redis.' . $name);
    }

    public function onDbConnect(EventArgs $eventArgs)
    {
        $this->logger->debug(['connect to `:dsn`', 'dsn' => $eventArgs->data['dsn']], 'db.connect');
    }

    public function onDbExecuted(EventArgs $eventArgs)
    {
        $data = $eventArgs->data;

        $this->logger->info($data, 'db.' . $data['type']);
    }

    public function onDbQueried(EventArgs $eventArgs)
    {
        $this->logger->debug($eventArgs->data, 'db.query');
    }

    public function onDbInserted(EventArgs $eventArgs)
    {
        $this->logger->info($eventArgs->data, 'db.insert');
    }

    public function onDbBegin()
    {
        $this->logger->info('transaction begin', 'db.begin');
    }

    public function onDbRollback()
    {
        $this->logger->info('transaction rollback', 'db.rollback');
    }

    public function onDbCommit()
    {
        $this->logger->info('transaction commit', 'db.commit');
    }

    public function onDbMetadata(EventArgs $eventArgs)
    {
        $this->logger->debug($eventArgs->data, 'db.metadata');
    }

    public function onDbAbnormal()
    {
        $this->logger->error('transaction is not close correctly', 'db.abnormal');
    }

    public function onMailerSending(EventArgs $eventArgs)
    {
        /** @var \ManaPHP\Mailer\Message $message */
        $message = $eventArgs->data['message'];

        $this->logger->debug(['From: ', $message->getFrom()]);
        $this->logger->debug(['To: ', $message->getTo()]);
        $this->logger->debug(['Cc:', $message->getCc()]);
        $this->logger->debug(['Bcc: ', $message->getBcc()]);
        $this->logger->debug(['Subject: ', $message->getSubject()]);
    }

    public function onMongodbConnect(EventArgs $eventArgs)
    {
        $this->logger->debug(['connect to `:dsn`', $eventArgs->data], 'mongodb.connect');
    }

    public function onMongodbInserted(EventArgs $eventArgs)
    {
        $this->logger->info($eventArgs->data, 'mongodb.insert');
    }

    public function onMongodbBulkInserted(EventArgs $eventArgs)
    {
        $this->logger->info($eventArgs->data, 'mongodb.bulk.insert');
    }

    public function onMongodbUpdated(EventArgs $eventArgs)
    {
        $this->logger->info($eventArgs->data, 'mongodb.update');
    }

    public function onMongodbUpserted(EventArgs $eventArgs)
    {
        $this->logger->info($eventArgs->data, 'mongodb.upsert');
    }

    public function onMongodbBulkUpserted(EventArgs $eventArgs)
    {
        $this->logger->info($eventArgs->data, 'mongodb.bulk.upsert');
    }

    public function onMongodbDeleted(EventArgs $eventArgs)
    {
        $this->logger->info($eventArgs->data, 'mongodb.delete');
    }

    public function onMongodbQueried(EventArgs $eventArgs)
    {
        $this->logger->debug($eventArgs->data, 'mongodb.query');
    }

    public function onMongodbCommanded(EventArgs $eventArgs)
    {
        $data = $eventArgs->data;

        $command_name = key($data['command']);
        if (strpos('ping,aggregate,count,distinct,group,mapReduce,geoNear,geoSearch,find,' .
                'authenticate,listDatabases,listCollections,listIndexes', $command_name) !== false) {
            $this->logger->debug($data, 'mongodb.command.' . $command_name);
        } else {
            $this->logger->info($data, 'mongodb.command.' . $command_name);
        }
    }

    public function onMongodbBulkUpdated(EventArgs $eventArgs)
    {
        $this->logger->info($eventArgs->data, 'mongodb.bulk.update');
    }

    public function onHttpClientRequesting(EventArgs $eventArgs)
    {
        $this->logger->debug($eventArgs->data, 'httpClient.request');
    }

    public function onHttpClientRequested(EventArgs $eventArgs)
    {
        $this->logger->debug($eventArgs->data, 'httpClient.response');
    }

    public function onWsClientSend(EventArgs $eventArgs)
    {
        $this->logger->debug($eventArgs->data, 'wsClient.send');
    }

    public function onWsClientReceive(EventArgs $eventArgs)
    {
        $this->logger->debug($eventArgs->data, 'wsClient.receive');
    }
}
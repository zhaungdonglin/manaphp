<?php

namespace ManaPHP;

use ManaPHP\Mongodb\Exception as MongodbException;
use MongoDB\Driver\Exception\RuntimeException;

/**
 * Class Mongodb
 * @package ManaPHP
 * @property-read \ManaPHP\DiInterface $di
 */
class Mongodb extends Component implements MongodbInterface
{
    /**
     * @var string
     */
    protected $_dsn;

    /**
     * @var string
     */
    protected $_default_db;

    /**
     * Mongodb constructor.
     *
     * @param string $dsn
     */
    public function __construct($dsn = 'mongodb://127.0.0.1:27017/')
    {
        $this->_dsn = $dsn;

        $path = parse_url($dsn, PHP_URL_PATH);
        $this->_default_db = ($path !== '/' && $path !== null) ? (string)substr($path, 1) : null;

        $this->poolManager->add($this, $this->di->get('ManaPHP\Mongodb\Connection', [$this->_dsn]));
    }

    public function __destruct()
    {
        $this->poolManager->remove($this);
    }

    /**
     * @return string|null
     */
    public function getDefaultDb()
    {
        return $this->_default_db;
    }

    /**
     * @param string $source
     * @param array  $document
     *
     * @return int
     */
    public function insert($source, $document)
    {
        $namespace = strpos($source, '.') !== false ? $source : ($this->_default_db . '.' . $source);

        $this->eventsManager->fireEvent('mongodb:inserting', $this, ['namespace' => $namespace]);

        /** @var \ManaPHP\Mongodb\ConnectionInterface $connection */
        $connection = $this->poolManager->pop($this);
        try {
            $count = $connection->insert($namespace, $document);
        } finally {
            $this->poolManager->push($this, $connection);
        }
        $this->eventsManager->fireEvent('mongodb:inserted', $this, compact('count', 'namespace', 'document'));

        return $count;
    }

    /**
     * @param string  $source
     * @param array[] $documents
     *
     * @return int
     */
    public function bulkInsert($source, $documents)
    {
        $namespace = strpos($source, '.') !== false ? $source : ($this->_default_db . '.' . $source);

        $this->eventsManager->fireEvent('mongodb:bulkWriting', $this, ['namespace' => $namespace]);
        $this->eventsManager->fireEvent('mongodb:bulkInserting', $this, ['namespace' => $namespace]);

        /** @var \ManaPHP\Mongodb\ConnectionInterface $connection */
        $connection = $this->poolManager->pop($this);
        try {
            $count = $connection->bulkInsert($namespace, $documents);
        } finally {
            $this->poolManager->push($this, $connection);
        }
        $data = compact('namespace', 'documents', 'count');
        $this->eventsManager->fireEvent('mongodb:bulkInserted', $this, $data);
        $this->eventsManager->fireEvent('mongodb:bulkWritten', $this, $data);

        return $count;
    }

    /**
     * @param string $source
     * @param array  $document
     * @param array  $filter
     *
     * @return int
     */
    public function update($source, $document, $filter)
    {
        $namespace = strpos($source, '.') !== false ? $source : ($this->_default_db . '.' . $source);

        $this->eventsManager->fireEvent('mongodb:updating', $this, ['namespace' => $namespace]);

        /** @var \ManaPHP\Mongodb\ConnectionInterface $connection */
        $connection = $this->poolManager->pop($this);
        try {
            $count = $connection->update($namespace, $document, $filter);
        } finally {
            $this->poolManager->push($this, $connection);
        }
        $this->eventsManager->fireEvent('mongodb:updated', $this, compact('namespace', 'document', 'filter', 'count'));
        return $count;
    }

    /**
     * @param string $source
     * @param array  $documents
     * @param string $primaryKey
     *
     * @return int
     * @throws \ManaPHP\Mongodb\Exception
     */
    public function bulkUpdate($source, $documents, $primaryKey)
    {
        $namespace = strpos($source, '.') !== false ? $source : ($this->_default_db . '.' . $source);

        $this->eventsManager->fireEvent('mongodb:bulkWriting', $this, ['namespace' => $namespace]);
        $this->eventsManager->fireEvent('mongodb:bulkUpdating', $this, ['namespace' => $namespace]);

        /** @var \ManaPHP\Mongodb\ConnectionInterface $connection */
        $connection = $this->poolManager->pop($this);
        try {
            $count = $connection->bulkUpdate($namespace, $documents, $primaryKey);
        } finally {
            $this->poolManager->push($this, $connection);
        }

        $data = compact('namespace', 'documents', 'primaryKey', 'count');
        $this->eventsManager->fireEvent('mongodb:bulkUpdated', $this, $data);
        $this->eventsManager->fireEvent('mongodb:bulkWritten', $this, $data);

        return $count;
    }

    /**
     * @param string $source
     * @param array  $document
     * @param string $primaryKey
     *
     * @return int
     * @throws \ManaPHP\Mongodb\Exception
     */
    public function upsert($source, $document, $primaryKey)
    {
        $namespace = strpos($source, '.') !== false ? $source : ($this->_default_db . '.' . $source);

        $this->eventsManager->fireEvent('mongodb:upserting', $this, ['namespace' => $namespace]);

        /** @var \ManaPHP\Mongodb\ConnectionInterface $connection */
        $connection = $this->poolManager->pop($this);
        try {
            $count = $connection->upsert($namespace, $document, $primaryKey);
        } finally {
            $this->poolManager->push($this, $connection);
        }
        $this->eventsManager->fireEvent('mongodb:upserted', $this, compact('count', 'namespace', 'document'));

        return $count;
    }

    /**
     * @param string $source
     * @param array  $documents
     * @param string $primaryKey
     *
     * @return int
     * @throws \ManaPHP\Mongodb\Exception
     */
    public function bulkUpsert($source, $documents, $primaryKey)
    {
        $namespace = strpos($source, '.') !== false ? $source : ($this->_default_db . '.' . $source);

        $this->eventsManager->fireEvent('mongodb:bulkWriting', $this, ['namespace' => $namespace]);
        $this->eventsManager->fireEvent('mongodb:bulkUpserting', $this, ['namespace' => $namespace]);

        /** @var \ManaPHP\Mongodb\ConnectionInterface $connection */
        $connection = $this->poolManager->pop($this);
        try {
            $count = $connection->bulkUpsert($namespace, $documents, $primaryKey);
        } finally {
            $this->poolManager->push($this, $connection);
        }
        $this->eventsManager->fireEvent('mongodb:bulkUpserted', $this);
        $this->eventsManager->fireEvent('mongodb:bulkWritten', $this);

        return $count;
    }

    /**
     * @param string $source
     * @param array  $filter
     *
     * @return int
     * @throws \ManaPHP\Mongodb\Exception
     */
    public function delete($source, $filter)
    {
        $namespace = strpos($source, '.') !== false ? $source : ($this->_default_db . '.' . $source);

        $this->eventsManager->fireEvent('mongodb:deleting', $this, ['namespace' => $namespace]);

        /** @var \ManaPHP\Mongodb\ConnectionInterface $connection */
        $connection = $this->poolManager->pop($this);
        try {
            $count = $connection->delete($namespace, $filter);
        } finally {
            $this->poolManager->push($this, $connection);
        }
        $this->eventsManager->fireEvent('mongodb:deleted', $this, compact('namespace', 'filter', 'count'));

        return $count;
    }

    /**
     * @param string   $source
     * @param array    $filter
     * @param array    $options
     * @param bool|int $secondaryPreferred
     *
     * @return array[]
     */
    public function fetchAll($source, $filter = [], $options = [], $secondaryPreferred = true)
    {
        $namespace = strpos($source, '.') !== false ? $source : ($this->_default_db . '.' . $source);

        $this->eventsManager->fireEvent('mongodb:querying', $this, compact('namespace', 'filter', 'options'));

        /** @var \ManaPHP\Mongodb\ConnectionInterface $connection */
        $connection = $this->poolManager->pop($this);
        try {
            $start_time = microtime(true);
            $result = $connection->fetchAll($namespace, $filter, $options, $secondaryPreferred);
            $elapsed = round(microtime(true) - $start_time, 3);
        } finally {
            $this->poolManager->push($this, $connection);
        }

        $this->eventsManager->fireEvent('mongodb:queried', $this, compact('namespace', 'filter', 'options', 'result', 'elapsed'));

        return $result;
    }

    /**
     * @param array  $command
     * @param string $db
     *
     * @return array[]
     */
    public function command($command, $db = null)
    {
        if (!$db) {
            $db = $this->_default_db;
        }

        $this->eventsManager->fireEvent('mongodb:commanding', $this, compact('db', 'command'));

        /** @var \ManaPHP\Mongodb\ConnectionInterface $connection */
        $connection = $this->poolManager->pop($this);
        try {
            $start_time = microtime(true);
            $result = $connection->command($command, $db);
            $elapsed = round(microtime(true) - $start_time, 3);
        } finally {
            $this->poolManager->push($this, $connection);
        }

        $count = count($result);
        $this->eventsManager->fireEvent('mongodb:commanded', $this, compact('db', 'command', 'result', 'count', 'elapsed'));

        return $result;
    }

    /**
     * @param string $source
     * @param array  $pipeline
     * @param array  $options
     *
     * @return array
     * @throws \ManaPHP\Mongodb\Exception
     */
    public function aggregate($source, $pipeline, $options = [])
    {
        if ($pos = strpos($source, '.')) {
            $db = substr($source, 0, $pos);
            $collection = substr($source, $pos + 1);
        } else {
            $db = $this->_default_db;
            $collection = $source;
        }

        try {
            $command = ['aggregate' => $collection, 'pipeline' => $pipeline];
            if ($options) {
                $command = array_merge($command, $options);
            }
            if (!isset($command['cursor'])) {
                $command['cursor'] = ['batchSize' => 1000];
            }
            return $this->command($command, $db);
        } catch (RuntimeException $e) {
            throw new MongodbException([
                '`:aggregate` aggregate for `:collection` collection failed: :msg',
                'aggregate' => json_stringify($pipeline),
                'collection' => $source,
                'msg' => $e->getMessage()
            ]);
        }
    }

    /**
     * @param string $source
     *
     * @return bool
     */
    public function truncate($source)
    {
        if ($pos = strpos($source, '.')) {
            $db = substr($source, 0, $pos);
            $collection = substr($source, $pos + 1);
        } else {
            $db = $this->_default_db;
            $collection = $source;
        }

        try {
            $this->command(['drop' => $collection], $db);
            return true;
        } catch (RuntimeException $e) {
            /**
             * https://github.com/mongodb/mongo/blob/master/src/mongo/base/error_codes.err
             * error_code("NamespaceNotFound", 26)
             */
            if ($e->getCode() === 26) {
                return true;
            } else {
                throw $e;
            }
        }
    }

    /**
     * @return array
     */
    public function listDatabases()
    {
        $databases = [];
        $result = $this->command(['listDatabases' => 1], 'admin');
        foreach ((array)$result[0]['databases'] as $database) {
            $databases[] = $database['name'];
        }

        return $databases;
    }

    /**
     * @param string $db
     *
     * @return array
     */
    public function listCollections($db = null)
    {
        $collections = [];
        $result = $this->command(['listCollections' => 1], $db);
        foreach ($result as $collection) {
            $collections[] = $collection['name'];
        }

        return $collections;
    }

    /**
     * @param string $collection
     *
     * @return \ManaPHP\Mongodb\Query
     */
    public function query($collection = null)
    {
        return $this->_di->get('ManaPHP\Mongodb\Query', [$this])->from($collection);
    }
}
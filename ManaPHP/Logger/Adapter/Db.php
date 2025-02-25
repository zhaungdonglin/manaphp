<?php

namespace ManaPHP\Logger\Adapter;

use Exception;
use ManaPHP\Logger;

/**
 * Class ManaPHP\Logger\Adapter\Db
 *
 * @package logger
 */
class Db extends Logger
{
    /**
     * @var string
     */
    protected $_db = 'db';

    /**
     * @var string
     */
    protected $_table = 'manaphp_log';

    /**
     * Db constructor.
     *
     * @param string|array $options
     */
    public function __construct($options = null)
    {
        parent::__construct($options);

        if (isset($options['db'])) {
            $this->_db = $options['db'];
        }

        if (isset($options['table'])) {
            $this->_table = $options['table'];
        }
    }

    /**
     * @param \ManaPHP\Logger\Log $log
     *
     * @return void
     */
    public function append($log)
    {
        /** @var \ManaPHP\DbInterface $db */
        $db = $this->_di->getShared($this->_db);

        $level = $this->logger->getLevel();
        $this->logger->setLevel(Logger::LEVEL_FATAL);
        try {
            $db->insert($this->_table, [
                'host' => $log->host,
                'client_ip' => $log->client_ip,
                'request_id' => $log->request_id,
                'category' => $log->category,
                'level' => $log->level,
                'file' => $log->file,
                'line' => $log->line,
                'message' => $log->message,
                'timestamp' => $log->timestamp - (int)$log->timestamp,
                'created_time' => (int)$log->timestamp]);
        } catch (Exception $e) {
            null;
        }
        $this->logger->setLevel($level);
    }
}
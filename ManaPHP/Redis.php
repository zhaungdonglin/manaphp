<?php
namespace ManaPHP;

use ManaPHP\Coroutine\Context\Inseparable;

class RedisContext implements Inseparable
{
    /**
     * @var \ManaPHP\Redis\Connection
     */
    public $connection;
}

/**
 * Class Redis
 * @package ManaPHP
 * @property-read \ManaPHP\RedisContext $_context
 */
class Redis extends Component
{
    /**
     * @var string
     */
    protected $_uri;

    /**
     * @var int
     */
    protected $_pool_size;

    /**
     * @var float
     */
    protected $_timeout = 1.0;

    /**
     * Redis constructor.
     *
     * @param string|\ManaPHP\Redis\Connection $uri
     */
    public function __construct($uri = 'redis://127.0.0.1/1?timeout=3&retry_interval=0&auth=&persistent=0')
    {
        if (is_string($uri)) {
            $this->_uri = $uri;
            $pool_size = preg_match('#pool_size=(\d+)#', $uri, $matches) ? $matches[1] : 4;
            $connection = ['class' => 'ManaPHP\Redis\Connection', $this->_uri];
        } else {
            $pool_size = 1;
            $connection = $uri;
            $this->_uri = $uri->getUri();
        }

        if (strpos($this->_uri, 'timeout=') !== false && preg_match('#timeout=([\d.]+)#', $this->_uri, $matches) === 1) {
            $this->_timeout = (float)$matches[1];
        }

        $this->_pool_size = $pool_size;

        $this->poolManager->add($this, $connection, $pool_size);
    }

    public function __destruct()
    {
        $this->poolManager->remove($this);
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return bool|mixed
     */
    public function __call($name, $arguments)
    {
        $context = $this->_context;

        $this->fireEvent('redis:calling', ['name' => $name, 'arguments' => $arguments]);

        if ($context->connection) {
            $connection = $context->connection;
        } else {
            $connection = $this->poolManager->pop($this, $this->_timeout);
        }

        try {
            $r = $connection->call($name, $arguments);
        } finally {
            if (!$context->connection) {
                $this->poolManager->push($this, $connection);
            }
        }

        $this->fireEvent('redis:called', ['name' => $name, 'arguments' => $arguments, 'return' => $r]);

        return $r;
    }
}
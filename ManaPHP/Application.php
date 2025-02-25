<?php

namespace ManaPHP;

use ManaPHP\Aop\Unaspectable;
use ManaPHP\Cli\Factory as CliFactory;
use ManaPHP\Mvc\Factory as MvcFactory;
use ReflectionClass;
use Swoole\Runtime;

/**
 * Class ManaPHP\Application
 *
 * @package application
 *
 * @property-read \ManaPHP\DotenvInterface        $dotenv
 * @property-read \ManaPHP\ErrorHandlerInterface  $errorHandler
 * @property-read \ManaPHP\AuthorizationInterface $authorization
 * @property-read \ManaPHP\Http\RequestInterface  $request
 */
class Application extends Component implements ApplicationInterface, Unaspectable
{
    /**
     * @var string
     */
    protected $_class_file;

    /**
     * @var string
     */
    protected $_root_dir;

    /**
     * Application constructor.
     *
     * @param \ManaPHP\Loader $loader
     */
    public function __construct($loader = null)
    {
        $class = static::class;
        $this->_class_file = (new ReflectionClass($class))->getFileName();

        ini_set('html_errors', 'off');
        ini_set('default_socket_timeout', -1);

        $GLOBALS['DI'] = $this->getDi();

        if (!defined('MANAPHP_COROUTINE_ENABLED')) {
            define('MANAPHP_COROUTINE_ENABLED', PHP_SAPI === 'cli' && extension_loaded('swoole'));
        }

        if (MANAPHP_COROUTINE_ENABLED) {
            Runtime::enableCoroutine(true);
        }

        $this->_di->setShared('loader', $loader ?: new Loader());
        $this->_di->setShared('app', $this);

        $rootDir = $this->getRootDir();
        $appDir = $rootDir . '/app';
        $appNamespace = 'App';
        $publicDir = $_SERVER['DOCUMENT_ROOT'] !== '' ? $_SERVER['DOCUMENT_ROOT'] : $rootDir . '/public';

        if (strpos($class, 'ManaPHP\\') !== 0) {
            $appDir = dirname($this->_class_file);
            $appNamespace = substr($class, 0, strrpos($class, '\\'));
            $publicDir = $rootDir . '/public';
        }

        $this->alias->set('@public', $publicDir);
        $this->alias->set('@app', $appDir);
        $this->loader->registerNamespaces([$appNamespace => $appDir]);

        $this->alias->set('@views', $appDir . '/Views');

        $this->alias->set('@root', $rootDir);
        $this->alias->set('@data', $rootDir . '/data');
        $this->alias->set('@tmp', $rootDir . '/tmp');
        $this->alias->set('@resources', $rootDir . '/Resources');
        $this->alias->set('@config', $rootDir . '/config');

        $web = '';
        if (isset($_SERVER['SCRIPT_NAME']) && ($pos = strrpos($_SERVER['SCRIPT_NAME'], '/')) > 0) {
            $web = substr($_SERVER['SCRIPT_NAME'], 0, $pos);
            if (substr_compare($web, '/public', -7) === 0) {
                $web = substr($web, 0, -7);
            }
        }
        $this->alias->set('@web', $web);
        $this->alias->set('@asset', $web);

        $this->loader->registerFiles('@manaphp/helpers.php');
    }

    /**
     * @return string
     */
    public function getRootDir()
    {
        if (!$this->_root_dir) {
            if (strpos(static::class, 'ManaPHP\\') !== 0) {
                $this->_root_dir = dirname($this->_class_file, 2);
            } elseif ($_SERVER['DOCUMENT_ROOT'] !== '' && $_SERVER['DOCUMENT_ROOT'] === dirname($_SERVER['SCRIPT_FILENAME'])) {
                $this->_root_dir = dirname($_SERVER['DOCUMENT_ROOT']);
            } else {
                $rootDir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
                if (is_file($rootDir . '/index.php')) {
                    $rootDir = dirname($rootDir);
                }
                $this->_root_dir = $rootDir;
            }
        }

        return $this->_root_dir;
    }

    /**
     * @param string $rootDir
     *
     * @return static
     */
    public function setRootDir($rootDir)
    {
        $this->_root_dir = $rootDir;
        return $this;
    }

    public function getDi()
    {
        if (!$this->_di) {
            $this->_di = $_SERVER['DOCUMENT_ROOT'] !== '' ? new MvcFactory() : new CliFactory();
        }
        return $this->_di;
    }

    /**
     * @param array $listeners
     */
    protected function _loadListeners($listeners)
    {
        foreach ($listeners as $listener) {
            if ($listener === '*') {
                foreach ($this->filesystem->glob('@app/Areas/*/Listeners/*Listener.php') as $item) {
                    $item = str_replace($this->alias->get('@app'), 'App', $item);
                    $item = substr(str_replace('/', '\\', $item), 0, -4);
                    $this->eventsManager->addListener($item);
                }

                foreach ($this->filesystem->glob('@app/Listeners/*Listener.php') as $item) {
                    $item = str_replace($this->alias->get('@app'), 'App', $item);
                    $item = substr(str_replace('/', '\\', $item), 0, -4);
                    $this->eventsManager->addListener($item);
                }
            } else {
                $this->eventsManager->addListener($listener);
            }
        }
    }

    /**
     * @param array $plugins
     */
    protected function _loadPlugins($plugins)
    {
        $app_plugins = [];
        foreach ($this->filesystem->glob('@app/Plugins/*Plugin.php') as $item) {
            $app_plugins[basename($item, '.php')] = 1;
        }

        foreach ($plugins as $k => $v) {
            $plugin = is_string($k) ? $k : $v;
            if (($pos = strrpos($plugin, 'Plugin')) === false || $pos !== strlen($plugin) - 6) {
                $plugin .= 'Plugin';
            }

            if ($plugin[0] === '!') {
                unset($app_plugins[ucfirst(substr($plugin, 1))]);
                continue;
            }

            $plugin = ucfirst($plugin);

            $pluginClassName = isset($app_plugins[$plugin]) ? "App\\Plugins\\$plugin" : "ManaPHP\Plugins\\$plugin";
            unset($app_plugins[$plugin]);

            $plugin = lcfirst($plugin);
            $this->_di->setShared($plugin, is_int($k) ? $pluginClassName : array_merge($v, ['class' => $pluginClassName]))->getShared($plugin);
        }

        foreach ($app_plugins as $plugin => $_) {
            $pluginClassName = "App\\Plugins\\$plugin";
            $plugin = lcfirst($plugin);
            $this->_di->setShared($plugin, $pluginClassName)->getShared($plugin);
        }
    }

    /**
     * @param array $components
     */
    protected function _loadComponents($components)
    {
        foreach ($components as $component => $definition) {
            if (is_int($component)) {
                $component = lcfirst(($pos = strrpos($definition, '\\')) ? substr($definition, $pos + 1) : $definition);
                $this->_di->setShared($component, $definition);
            } elseif ($definition === null) {
                $this->_di->remove($component);
            } elseif ($component[0] !== '!' || $this->_di->has($component = substr($component, 1))) {
                $this->_di->setShared($component, $definition);
            }
        }
    }

    /**
     * @param array $services
     */
    protected function _loadServices($services)
    {
        $items = [];
        foreach (@scandir($this->alias->resolve('@app/Services')) ?: [] as $file) {
            $items[basename($file, '.php')] = 1;
        }

        foreach ($services as $service => $params) {
            if (is_string($params)) {
                $params = ['endpoint' => $params];
            }

            $class = 'App\Services\\' . ucfirst($service);
            $params['interface'] = $class . 'Interface';
            if (!isset($items[$class])) {
                $class = 'ManaPHP\Service';
            }

            $this->_di->setShared($service, ['class' => $class, $params]);
            unset($items[$class]);
        }

        foreach ($items as $item => $_) {
            if (strpos($item, 'Interface') === false && strpos($item, 'Service') !== false) {
                $this->_di->setShared(lcfirst($item), 'App\\Services\\' . $item);
            }
        }
    }

    protected function _loadAspects()
    {
        foreach ($this->filesystem->glob('@app/Aspects/*Aspect.php') as $item) {
            $class = 'App\Aspects\\' . basename($item, '.php');
            /** @var \ManaPHP\Aop\Aspect $aspect */
            $aspect = new $class();
            $aspect->register();
        }
    }

    public function registerServices()
    {
        $configure = $this->configure;

        if ($configure->timezone) {
            date_default_timezone_set($configure->timezone);
        }
        $this->_di->setShared('crypt', [$configure->master_key]);

        foreach ($configure->aliases as $alias => $path) {
            $this->_di->alias->set($alias, $path);
        }

        $app_dir = scandir($this->alias->resolve('@app'));

        if (in_array('Router.php', $app_dir, true)) {
            $this->_di->setShared('router', 'App\\Router');
        }

        if ($configure->components) {
            $this->_loadComponents($configure->components);
        }

        if (in_array('Aspects', $app_dir, true)) {
            $this->_loadAspects();
        }

        $this->_loadServices($configure->services);

        if ($configure->plugins || in_array('Plugins', $app_dir, true)) {
            $this->_loadPlugins($configure->plugins);
        }

        if ($configure->listeners) {
            $this->_loadListeners($configure->listeners);
        }
    }

    /**
     * @param \Throwable $exception
     */
    public function handleException($exception)
    {
        $this->errorHandler->handle($exception);
    }

    public function main()
    {
        if ($this->filesystem->fileExists('@root/.env')) {
            $this->dotenv->load();
        }

        if ($this->filesystem->fileExists('@config/app.php')) {
            $this->configure->load();
        }

        $this->registerServices();

        if ($_SERVER['DOCUMENT_ROOT'] !== '') {
            $this->eventsManager->fireEvent('request:begin', $this);
        }
    }
}
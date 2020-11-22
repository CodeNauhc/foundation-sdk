<?php


namespace Hanson\Foundation;


use ArrayAccess;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FilesystemCache;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Pimple\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Foundation
 * @property-read Http $http
 * @package Hanson\Foundation
 */
class Foundation extends Container
{

    /**
     * an array of service providers.
     *
     * @var
     */
    protected $providers = [];

    protected $config;

    public function __construct($config)
    {
        parent::__construct();

        $this->setConfig($config);

        if (!!$this->getConfig('debug')) {
            error_reporting(E_ALL);
        }

        $this->registerProviders();
        $this->registerBase();
        $this->initializeLogger();
    }

    /**
     * Register basic providers.
     */
    private function registerBase()
    {
        $this['request'] = function () {
            return Request::createFromGlobals();
        };

        $this['http'] = function () {
            return new Http($this);
        };

        if (($cache = $this->getConfig('cache')) && $cache instanceof Cache) {
            $this['cache'] = $this->getConfig()['cache'];
        } else {
            $this['cache'] = function () {
                return new FilesystemCache(sys_get_temp_dir());
            };
        }
    }

    /**
     * Initialize logger.
     */
    private function initializeLogger()
    {
        if (Log::hasLogger()) {
            return;
        }

        $logger = new Logger($this->getConfig('log.name', 'foundation'));

        if (!$this->getConfig('debug') || defined('PHPUNIT_RUNNING')) {
            $logger->pushHandler(new NullHandler());
        } elseif (($this->getConfig('log.handler')) instanceof HandlerInterface) {
            $logger->pushHandler($this->getConfig()['log']['handler']);
        } elseif ($logFile = $this->getConfig('log.file')) {
            $logger->pushHandler(new StreamHandler(
                $logFile,
                $this->getConfig('log.level', Logger::WARNING),
                true,
                $this->getConfig('log.permission')
            ));
        }

        Log::setLogger($logger);
    }

    /**
     * Register providers.
     */
    protected function registerProviders()
    {
        foreach ($this->providers as $provider) {
            $this->register(new $provider());
        }
    }

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * copy from Arr::get()
     * @see https://github.com/illuminate/support/blob/feab1d1495fd6d38970bd6c83586ba2ace8f299a/Arr.php#L274
     * @param null $key
     * @param null $default
     * @return mixed|null
     */
    public function getConfig($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->config;
        }
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }
        $array = null;
        foreach (explode('.', $key) as $segment) {
            if ((!is_array($this->config) || !array_key_exists($segment, $this->config)) &&
                (!$this->config instanceof ArrayAccess || !$this->config->offsetExists($segment))) {
                return $default instanceof \Closure ? $default() : $default;
            }
            $array = $this->config[$segment];
        }
        return $array;
    }

    /**
     * Magic get access.
     *
     * @param  string  $id
     *
     * @return mixed
     */
    public function __get($id)
    {
        return $this->offsetGet($id);
    }

    /**
     * Magic set access.
     *
     * @param  string  $id
     * @param  mixed  $value
     */
    public function __set($id, $value)
    {
        $this->offsetSet($id, $value);
    }
}

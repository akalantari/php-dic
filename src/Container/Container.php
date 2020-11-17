<?php
namespace AKalantari\Container;

/**
 * Class Container
 * @package AKalantari
 *
 * @property array $_services
 * @property array $_parameters
 * @property array $_serviceStore
 */
class Container
{
    private $_services;
    private $_parameters;
    private $_serviceStore;

    public function __construct(array $services=[], array $parameters=[])
    {
        $this->_services = $services;
        $this->_parameters = $parameters;
        $this->_serviceStore = [];
    }

    /**
     * @param string $name
     * @return \stdClass
     * @throws \Exception
     */
    public function &get(string $name): \stdClass
    {
        // Check the availability for the asked service
        if( $this->has($name) ) {
            throw new \Exception("Class Not Found In The Container");
        }

        if( !isset($this->_serviceStore[$name]) ) {
            $this->_serviceStore[$name] = $this->_createService($name);
        }

        return $this->_serviceStore[$name];
    }

    public function has(string $name)
    {
        return isset($this->_services[$name]);
    }

    public function registerService(string $name, $class): void
    {
        if( isset($this->_services[$name]) ) {
            throw new \Exception("Service {$name} already registered");
        }

        $this->_services[$name] = $class;
    }

    protected function _createService(string $name)
    {
        $entry = $this->_services[$name];

        if ( !isset($entry) ) {
            throw new \Exception($name.' service entry must be of type class or string name of class');
        }

        if( is_string($entry) ) {
            $entry = [
                'class' => $entry,
                'arguments' => [],
                'initialize' => []
            ];
        }

        $arguments = $entry['arguments'] ?? [];

        $reflector = new \ReflectionClass($entry['class']);
        $service = $reflector->newInstanceArgs($arguments);

        if (isset($entry['initialize'])) {
            if( !is_array($entry['initialize']) ) {
                throw new \Exception("Initialize argument must be an array");
            }
            $this->_initializeService($service, $entry['initialize']);
        }

        return $service;
    }

    protected function _initializeService(&$service, callable $callDefinitions)
    {
        foreach ($callDefinitions as $callDefinition) {
            if( is_callable($callDefinition) ) {
                $callDefinition($service);
            } elseif ( is_string($callDefinition) ) {
                if( !method_exists($service, $callDefinition) ) {
                    throw new \Exception("Method {$callDefinition} does not exist in the requested class");
                }
                $service->{$callDefinition}();
            } elseif ( is_array($callDefinition) ) {
                if( empty($callDefinition['method']) || !method_exists($service, $callDefinition['method']) ) {
                    throw new \Exception("Method {$callDefinition} does not exist in the requested class");
                }
                $arguments = $callDefinition['arguments'] ?? [];
                call_user_func_array([$service, $callDefinition['method']], $arguments);
            }

            throw new \Exception("Initialize failed");
        }
    }
}
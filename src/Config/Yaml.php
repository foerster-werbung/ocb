<?php

namespace FoersterWerbung\Bootstrapper\October\Config;

/**
 * Class Yaml
 * @package FoersterWerbung\Bootstrapper\October\Config
 */
class Yaml implements Config, \ArrayAccess
{
    /**
     * @var mixed
     */
    protected $config;

    /**
     * Yaml constructor.
     *
     * @param             $file
     *
     * @throws \RuntimeException
     */
    public function __construct($file)
    {
        try {
            $this->config = yaml_parse_file($file);
        } catch (\Exception $e) {
            throw new \RuntimeException('Unable to parse the YAML string: %s', $e->getMessage());
        }
    }

    /**
     * @param $name
     *
     * @return mixed
     * @throws \RuntimeException
     */
    public function __get($name)
    {
        return isset($this->config[$name]) ? $this->config[$name] : null;
    }

    public function offsetExists($offset)
    {
        return isset($this->config[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->config[$offset];

    }

    public function offsetSet($offset, $value)
    {
        // TODO: Implement offsetSet() method.
    }

    public function offsetUnset($offset)
    {
        // TODO: Implement offsetUnset() method.
    }
}
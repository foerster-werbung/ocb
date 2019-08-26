<?php

namespace FoersterWerbung\Bootstrapper\October\Util;

use FoersterWerbung\Bootstrapper\October\Config\Yaml;
use RuntimeException;

/**
 * Config maker trait
 */
trait ConfigMaker
{
    use ManageDirectory;

    /**
     * @var
     */
    public $config;

    protected function makeConfig()
    {
        $configFile = $this->pwd() . 'october.yaml';
        if ( ! file_exists($configFile)) {
            throw new RuntimeException("<comment>october.yaml not found. Run october init first.</comment>", 1);
        }

        $this->config = new Yaml($configFile);
    }
}

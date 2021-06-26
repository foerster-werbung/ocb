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
            $this->config = [];
            return ;
        }

        $this->config = new Yaml($configFile);
    }
}

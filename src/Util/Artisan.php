<?php

namespace FoersterWerbung\Bootstrapper\October\Util;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Class Artisan
 * @package FoersterWerbung\Bootstrapper\October\Util
 */
class Artisan
{
    use CliIO;

    /**
     * @var string
     */
    protected $php;

    public function __construct(string $php = 'php')
    {
        $this->setPhp($php);
    }

    /**
     * Set PHP version to be used in console commands
     */
    public function setPhp(string $php = 'php')
    {
        $this->php = $php;
    }

    public function octoberInstall() {
        return $this->call('october:install', ['--no-interaction']);
    }

    public function octoberBuild() {
        return $this->call('october:build', ['--no-interaction']);
    }

    public function themeUse($theme) {
        return $this->call('theme:use', [$theme]);
    }

    public function themeInstall($theme) {
        return $this->call('theme:install', [$theme]);
    }

    public function pluginInstall($vendor, $plugin) {
        return $this->call('plugin:install', ["{$vendor}.{$plugin}"]);
    }

    public function call(string $command, array $params = [])
    {
        $proc = new Process(array_merge([$this->php, "artisan", $command], $params));
        $proc->enableOutput();
        $proc->setTimeout(3600);
        $exitCode = $proc->start();

        $this->write("php artisan $command " . implode(' ', $params));
        $proc->wait(function ($type, $buffer) {
            $this->write("$buffer", Process::ERR === $type ? 'error' : 'info');
        });

        if ($proc->getExitCode() !== $this->exitCodeOk) {
            throw new RuntimeException(
                sprintf("Error running \"{$this->php} artisan {$command}\" command: %s", $proc->getOutput())
            );
        }

        return $exitCode;
    }
}

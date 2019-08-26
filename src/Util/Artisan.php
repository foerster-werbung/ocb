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

    public function call(string $command)
    {
        $proc = new Process($this->php . " artisan " . $command);
        $proc->enableOutput();
        $proc->setTimeout(3600);
        $exitCode = $proc->start();

        $this->write("php artisan $command");
        $proc->wait(function ($type, $buffer) {
            $this->write("$buffer", Process::ERR === $type ? 'error' : 'info');
        });

        if ($proc->getExitCode() !== $this->exitCodeOk) {
            throw new RuntimeException(
                sprintf("Error running \"{$this->php} artisan {$command}\" command: %s", $proc->getOutput())
            );
        }
    }
}

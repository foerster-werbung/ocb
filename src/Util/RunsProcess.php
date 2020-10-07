<?php

namespace FoersterWerbung\Bootstrapper\October\Util;

use LogicException;
use RuntimeException;
use Symfony\Component\Process\Process;

trait RunsProcess
{

    /**
     * Runs a process and checks it's result.
     * Prints an error message if necessary.
     *
     * @param $command
     * @param $errorMessage
     *
     * @return bool
     * @throws RuntimeException
     * @throws LogicException
     */
    protected function runProcess($command, $errorMessage, $timeout = 30)
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->enableOutput();
        $process->start();

        $this->output->writeln("<comment>$command</comment>");
        $process->wait(function ($type, $buffer) {
            $wrap = Process::ERR === $type ? 'error' : 'comment';
            $this->output->write("<$wrap>$buffer</$wrap>");
        });

        return $this->checkProcessResult($process->getExitCode(), $errorMessage, $process->getOutput());
    }

    /**
     * Checks the result of a process.
     *
     * @param $exitCode
     * @param $message
     *
     * @return bool
     */
    protected function checkProcessResult($exitCode, $message, $output)
    {
        if ($exitCode !== 0) {
            $this->output->writeln('<error>' . $message . ($output ? ': ' : '') .  '</error>');
            $this->output->writln('-> '.$output);
            return false;
        }

        return true;
    }
}
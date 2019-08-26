<?php

namespace FoersterWerbung\Bootstrapper\October\Manager;

use FoersterWerbung\Bootstrapper\October\Util\Artisan;
use FoersterWerbung\Bootstrapper\October\Util\CliIO;
use FoersterWerbung\Bootstrapper\October\Util\Composer;
use FoersterWerbung\Bootstrapper\October\Util\ManageDirectory;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Plugin manager base class
 */
class BaseManager
{
    use CliIO {
        setOutput as cliSetOutput;
    }

    use ManageDirectory;

    /**
     * @var Artisan
     */
    protected $artisan;

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var string
     */
    protected $php;

    public function __construct()
    {
        $this->artisan = new Artisan();

        $this->composer = new Composer();

        $this->setPhp();
    }

    /**
     * Set PHP version to be used in console commands
     */
    public function setPhp(string $php = 'php')
    {
        $this->php = $php;
        $this->artisan->setPhp($php);
    }

    public function setOutput(OutputInterface $output)
    {
        $this->artisan->setOutput($output);
        $this->composer->setOutput($output);
        $this->cliSetOutput($output);
    }


}

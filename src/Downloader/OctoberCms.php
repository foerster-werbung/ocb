<?php

namespace FoersterWerbung\Bootstrapper\October\Downloader;


use FoersterWerbung\Bootstrapper\October\Manager\BaseManager;
use FoersterWerbung\Bootstrapper\October\Util\Composer;
use GuzzleHttp\Client;
use FoersterWerbung\Bootstrapper\October\Util\CliIO;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class OctoberCms extends BaseManager
{
    protected $files = [
        'bootstrap' => '',
        'config' => '',
        'modules' => '',
        'plugins' => '',
        'storage' => '',
        'tests' => '',
        'themes' => '',
        '.htaccess' => '.htaccess',
        'artisan' => 'artisan',
        'composer.json' => 'composer.json',
        'index.php' => 'index.php',
        'server.php' => 'server.php',
    ];

    protected $installerFile;
    protected $ocmsInstallDir = '/tmp/ocms';

    /**
     * Downloads and extracts October CMS.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->installerFile = getcwd() . DS . 'installer.php';
    }

    /**
     * Download latest October CMS.
     *
     * @param bool $force
     *
     * @return $this
     * @throws RuntimeException
     * @throws LogicException
     */
    public function download($force = false)
    {
        $this->write('-> Checking if October CMS download is required...');

        if ($this->alreadyInstalled($force)) {
            throw new \LogicException('-> October is already installed. Use --force to reinstall.');
        }

        $this
            ->cleanupProject()
            ->createProject()
            ->copyProjectFiles()
            ->cleanupProject();

        return $this;
    }

    protected function cleanupProject()
    {
        if (is_dir($this->ocmsInstallDir)) {
            $this->write("-> Deleting ocms copy in '$this->ocmsInstallDir'");
            (new Process(sprintf('rm -rf %s', $this->ocmsInstallDir)))->run();
        }

        return $this;
    }

    protected function createProject()
    {
        $this->write("-> Create fresh ocms copy in ".$this->ocmsInstallDir);
        $this->composer->createProject($this->ocmsInstallDir);

        return $this;
    }

    protected function copyProjectFiles()
    {
        foreach ($this->files as  $src => $dst) {
            $src = $this->ocmsInstallDir . DS . $src;
            $dst = $this->pwd() . $dst;
            $this->write("-> copying ".$src." -> ".$dst);

            (new Process(sprintf('cp -rn %s %s', $src, $dst)))->run();
        }

        return $this;

    }

    /**
     * Since we don't want any unstable updates we fix
     * the libraries to the master branch.
     *
     * @return $this
     */
    protected function setMaster()
    {
        $json = getcwd() . DS . 'composer.json';

        $this->write("-> Changing October CMS dependencies to dev-master");

        $contents = file_get_contents($json);

        $contents = preg_replace_callback(
            '/october\/(?:rain|system|backend|cms)":\s"([^"]+)"/m',
            function ($treffer) {
                $replacedDependency = str_replace($treffer[1], 'dev-master', $treffer[0]);
                $this->write("--> $replacedDependency");
                return $replacedDependency;
            },
            $contents
        );

        file_put_contents($json, $contents);

        return $this;
    }

    /**
     * @param $force
     *
     * @return bool
     */
    protected function alreadyInstalled($force)
    {
        if ($force) return false;

        foreach ($this->files as $file => $target) {
            $realFile = getcwd() . DS . $file;
            if (!is_dir($realFile) && !is_file($realFile)) {
                $this->write("-> Missing file or dir '/$file'");
                return false;
            }
        }

        return true;
    }

}

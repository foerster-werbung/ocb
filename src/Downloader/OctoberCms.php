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
    protected $zipFile;
    protected $installerFile;
    protected $htaccessFileSrc = "https://raw.githubusercontent.com/octobercms/october/1.1/.htaccess";
    protected $ocmsInstallDir = '.ocms';

    /**
     * Downloads and extracts October CMS.
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->zipFile = $this->makeFilename();
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
        if ($this->alreadyInstalled($force)) {
            throw new \LogicException('-> October is already installed. Use --force to reinstall.');
        }


        /*
        $this->fetchZip()
             ->extract()
             ->fetchHtaccess()
             ->cleanUp()
             ->setMaster();
        */

        /*
        $this->downloadInstaller()
            ->install()
            ->fetchHtaccess()
            ->cleanUpInstaller();
        */
        $this->composerInstaller();
        return $this;
    }

    protected function downloadInstaller()
    {
        $url = 'https://octobercms.com/api/installer';
        $this->write("-> Downloading $url");
        $response = (new Client)->get($url);
        file_put_contents($this->installerFile, $response->getBody());


        return $this;
    }

    protected function composerInstaller()
    {
        $this
            ->cleanupProject()
            ->createProject()
            ->copyProjectFiles()
            ->cleanupProject();


        return $this;
    }

    protected function cleanupProject()
    {
        $this->write("-> Deleting ocms copy in ".$this->ocmsInstallDir);
        (new Process(sprintf('rm -rf %s', $this->ocmsInstallDir)))->run();
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
        $files = [
            'bootstrap',
            'config',
            'modules',
            'plugins',
            'storage',
            'tests',
            'themes',
            '.htaccess',
            'artisan',
            '.gitignore',
            'composer.json',
            'index.php',
            'server.php'
        ];

        foreach ($files as  $file) {
            $src = $this->ocmsInstallDir . DS . $file;
            $dst = $file;
            $this->write("-> copying ".$src." -> ".$dst);

            (new Process(sprintf('cp -rn %s %s', $src, $dst)))->run();
        }

        return $this;

    }

    protected function install()
    {
        $this->write("-> Execute installer 'php " . $this->installerFile ."'");
        (new Process(sprintf($this->php.' -f %s', $this->installerFile)))->run();

        return $this;
    }

    protected function cleanUpInstaller()
    {
        $this->write("-> Cleanup installation file ". $this->installerFile);
        (new Process(sprintf('rm -f %s', $this->installerFile)))->run();
        return $this;
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @return $this
     * @throws RuntimeException
     * @throws LogicException
     */
    protected function fetchZip()
    {
        $url = 'https://github.com/octobercms/october/archive/master.zip';
        $this->write("-> Downloading $url");
        $response = (new Client)->get($url);
        file_put_contents($this->zipFile, $response->getBody());

        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @return $this
     */
    protected function extract()
    {

        $dst = getcwd();
        $this->write("-> Extracting zip to $dst");

        $archive = new ZipArchive;
        $archive->open($this->zipFile);
        $archive->extractTo($dst);
        $archive->close();

        return $this;
    }

    /**
     * Download the latest .htaccess file from GitHub separately
     * since ZipArchive does not support extracting hidden files.
     *
     * @return $this
     */
    protected function fetchHtaccess()
    {
        $this->write("-> Downloading .htaccess file from ".$this->htaccessFileSrc);
        $contents = file_get_contents($this->htaccessFileSrc);
        file_put_contents(getcwd() . DS . '.htaccess', $contents);

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
     * Remove the Zip file, move folder contents one level up.
     *
     * @return $this
     * @throws RuntimeException
     * @throws LogicException
     */
    protected function cleanUp()
    {
        @chmod($this->zipFile, 0777);
        @unlink($this->zipFile);

        $directory = getcwd();
        $source    = $directory . DS . 'october-master';

        (new Process(sprintf('mv %s %s', $source . '/*', $directory)))->run();
        (new Process(sprintf('rm -rf %s', $source)))->run();

        if (is_dir($source)) {
            echo "<comment>Install directory could not be removed. Delete ${source} manually</comment>";
        }

        return $this;
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */
    protected function makeFilename()
    {
        return getcwd() . DS . 'october_' . md5(time() . uniqid('oc-', true)) . '.zip';
    }

    /**
     * @param $force
     *
     * @return bool
     */
    protected function alreadyInstalled($force)
    {
        return ! $force && is_dir(getcwd() . DS . 'bootstrap') && is_dir(getcwd() . DS . 'modules');
    }

}

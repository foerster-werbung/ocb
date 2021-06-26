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
        'plugins' => '',
        'storage' => '',
        'themes' => '',
        'artisan' => 'artisan',
        'composer.json' => 'composer.json',
        'index.php' => 'index.php',
        'server.php' => 'server.php',
    ];

    protected $installerFile;
    protected $ocmsInstallDir = '/tmp/ocms';

    protected $branch = "1.1.x-dev";

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
     * @return 0: unknown, 1: redownload and install, 2: files are missing, 3: everything is fine
     *
     */
    public function checkInstallation() {

        // Check if composer file exists
        $composerJson = getcwd() . DS . "composer.json";
        if (!is_file($composerJson)) {
            $this->write("-> Missing file composer.json'");
            return 1;
        }

        // Check for octobercms gateway
        $composerJsonContent = json_decode(file_get_contents($composerJson), true);
        if(!isset($composerJsonContent['repositories']['octobercms'])) {
            $this->write("-> octobercms repository is missing in composer.json");
            return 1;
        }
        $this->write("-> composer.json checked");

        // Checking Auth file
        $authJsonFile = getcwd() . DS . "auth.json";
        if (!is_file($authJsonFile)) {
            $this->write("-> auth.json file is missing'");
            return 1;
        }

        $authJson = json_decode(file_get_contents($authJsonFile), true);
        if (!isset($authJson['http-basic']['gateway.octobercms.com']['username']) ||
            !isset($authJson['http-basic']['gateway.octobercms.com']['password'])) {
            $this->write("-> auth.json missing gateway 'gateway.octobercms.com' username or password");
            return 1;
        }
        $this->write("-> auth.json checked");


        foreach ($this->files as $file => $target) {
            $realFile = getcwd() . DS . $file;
            if (!is_dir($realFile) && !is_file($realFile)) {
                $this->write("-> Missing file or dir '/$file'");
                return 2;
            }
        }
        $this->write("-> folders checked");


        return 3;

    }
    /**
     * Download latest October CMS.
     *
     * @return $this
     * @throws RuntimeException
     * @throws LogicException
     */
    public function download()
    {
        $this
            ->cleanupProject()
            ->createProject()
            ->copyProjectFiles()
            //->setMaster()
            ->cleanupProject();

        return $this;
    }

    public function cleanupProject()
    {
        if (is_dir($this->ocmsInstallDir)) {
            $this->write("-> Deleting ocms copy in '$this->ocmsInstallDir'");
            (new Process(sprintf('rm -rf %s', $this->ocmsInstallDir)))->run();
        }

        return $this;
    }

    public function clearProject() {

        $modulesDir = $this->pwd() . DS . "modules";
        if (is_dir($modulesDir)) {
            (new Process(sprintf('rm -rf %s', $modulesDir)))->run();
        }

        $vendorDir = $this->pwd() . DS . "vendor";
        if (is_dir($vendorDir)) {
            (new Process(sprintf('rm -rf %s', $vendorDir)))->run();
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
                $replacedDependency = str_replace($treffer[1], $this->branch, $treffer[0]);
                $this->write("--> $replacedDependency");
                return $replacedDependency;
            },
            $contents
        );

        file_put_contents($json, $contents);

        return $this;
    }

}

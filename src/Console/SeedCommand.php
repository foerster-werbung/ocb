<?php

namespace FoersterWerbung\Bootstrapper\October\Console;

use InvalidArgumentException;
use LogicException;
use FoersterWerbung\Bootstrapper\October\Config\Setup;
use FoersterWerbung\Bootstrapper\October\Deployment\DeploymentFactory;
use FoersterWerbung\Bootstrapper\October\Downloader\OctoberCms;
use FoersterWerbung\Bootstrapper\October\Exceptions\DeploymentExistsException;
use FoersterWerbung\Bootstrapper\October\Exceptions\ThemeExistsException;
use FoersterWerbung\Bootstrapper\October\Manager\PluginManager;
use FoersterWerbung\Bootstrapper\October\Manager\ThemeManager;
use FoersterWerbung\Bootstrapper\October\Util\Artisan;
use FoersterWerbung\Bootstrapper\October\Util\CliIO;
use FoersterWerbung\Bootstrapper\October\Util\Composer;
use FoersterWerbung\Bootstrapper\October\Util\ConfigMaker;
use FoersterWerbung\Bootstrapper\October\Util\Gitignore;
use FoersterWerbung\Bootstrapper\October\Util\UsesTemplate;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class InstallCommand
 * @package FoersterWerbung\Bootstrapper\October\Console
 */
class SeedCommand extends Command
{
    use ConfigMaker, UsesTemplate, CliIO;

    /**
     * @inheritdoc
     */
    public function __construct($name = null)
    {

        parent::__construct($name);
    }


    /**
     * Configure the command options.
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName('seed')
            ->setDescription('Applying scripts and app data.');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return mixed
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->makeConfig();
        $this->setOutput($output);

        if (!isset($this->config['seed'])) {
            $this->write('-> Seeding is not configured, skipping', 'comment');
            return false;
        }

        if (isset($this->config['seed']['database'])) {
            $this->write('-> Seeding DB...');
            $this->seedDatabase();
        }

        if (isset($this->config['seed']['storage'])) {
            $this->write('-> Seeding storage');
            $this->seedStorage();
        }

        $this->write('-> Application seeded', 'comment');

        return 0;
    }

    /**
     * Seeds the database
     * only MySQL is supported right now
     */
    public function seedDatabase()
    {

        $DB_CONNECTION = $this->config->database['connection'];


        switch ($DB_CONNECTION) {

            case 'mysql':
                $this->seedMysql();
                break;
            default:
                $this->write("-> Unsupported database seeding {$DB_CONNECTION}", 'warning');
        }
    }

    public function afterSeeding($db) {
        $query = "SET foreign_key_checks = 1;";
        $stmt = $db->prepare($query);
        if ($stmt->execute()) {
            $this->write("--> FK-Check enabled", "info");
        }

        return $this;
    }
    /**
     * Seeds the MySQL database, requires mysql binary
     */
    public function seedMysql()
    {
        $DB_HOST = str_replace("localhost", "127.0.0.1", $this->config->database['host']);
        $DB_PORT = $this->config->database['port'];
        $DB_DATABASE = $this->config->database['database'];
        $DB_USERNAME = $this->config->database['username'];
        $DB_PASSWORD = $this->config->database['password'];

        $directory = getcwd();

        $seedOrigin = $this->config->seed['database'];
        if (DS == '\\') {
            $seedOrigin = $directory . DS . str_replace("/", DS, $seedOrigin);
        } else {
            $seedOrigin = $directory . DS . $seedOrigin;
        }

        $this->write("-> Connecting to database server $DB_HOST:$DB_PORT with $DB_USERNAME@$DB_DATABASE");
        # MySQL with PDO_MYSQL
        $db = new PDO("mysql:host=$DB_HOST;dbname=$DB_DATABASE;port=$DB_PORT;charset=utf8", $DB_USERNAME, $DB_PASSWORD);

        $query = "SET foreign_key_checks = 0;";
        $stmt = $db->prepare($query);
        if ($stmt->execute()) {
            $this->write("--> FK-Check disabled", "info");
        }

        if(is_file($seedOrigin)) {
            $this->seedMysqlFile($db, $seedOrigin);
            return $this->afterSeeding($db);
        }

        if(!is_dir($seedOrigin)) {
            $this->write("-> $seedOrigin is neither a file nor a directory, skipping seeding", "error");
            return $this->afterSeeding($db);
        };

        if ($handle = opendir($seedOrigin)) {

            while (false !== ($entry = readdir($handle))) {
                if (!$this->isSqlFile($entry)) {
                    continue;
                }

                $this->seedMysqlFile($db, $seedOrigin . DS . $entry);
            }

            closedir($handle);
        } else {
            $this->write("-> Unable to read seeding directory");
        }

        $this->afterSeeding($db);

        $this->write("-> Database has been seeded");

        return $this;
    }

    protected function isSqlFile(string $file) {

        return (strtolower(substr($file, -4)) === ".sql");
    }

    protected function seedMysqlFile(PDO $db, $file) {

        $this->write("-> Seeding file $file");
        $query = $this->file_get_contents_utf8($file);
        $stmt = $db->prepare($query);
        if ($stmt->execute())
            $this->write("--> File has been seeded successfully");
        else {

            $this->write("--> Error {$db->errorCode()}, $file could not be seeded:", "error");
            $this->write("---> {$db->errorInfo()}");
        }


        return 0;
    }

    function file_get_contents_utf8($fn) {
        $content = file_get_contents($fn);
        return mb_convert_encoding($content, 'UTF-8',
            mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
    }

    /**
     * Seeds the storage folder
     */
    public function seedStorage()
    {
        $directory = getcwd();
        $storageFolder = $this->config->seed["storage"];

        if(!$storageFolder) {
            $this->write("--> No storage folder given", "comment");
            return;
        }
        $src = $directory . DS . $storageFolder;
        if (DS == '\\') {
            $src = $directory . DS . str_replace("/", DS, $storageFolder);
        }

        if (!is_dir($src)) {
            $this->write("--> Can not find storage folder '".$storageFolder. "', skipping storage seeding.", "comment");
            return;
        }
        $dst = $directory . DS . 'storage';

        $this->write("--> Copying {$src} -> {$dst}");
        $this->recursive_copy($src, $dst);
    }

    /** 
     * This function copy $source directory and all files 
     * and sub directories to $destination folder
     */
    function recursive_copy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . DS . $file)) {
                    $this->recursive_copy($src . DS . $file, $dst . DS . $file);
                } else {
                    copy($src . DS . $file, $dst . DS . $file);
                }
            }
        }
        closedir($dir);
    }

}

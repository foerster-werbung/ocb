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
class InstallCommand extends Command
{
    use ConfigMaker, UsesTemplate, CliIO;

    /**
     * @var Gitignore
     */
    protected $gitignore;

    /**
     * @var bool
     */
    protected $firstRun;

    /**
     * @var bool
     */
    protected $force;

    /**
     * @var PluginManager
     */
    protected $pluginManager;

    /**
     * @var ThemeManager
     */
    protected $themeManager;

    /**
     * @var OctoberCms
     */
    protected $ocms;

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

    /**
     * @inheritdoc
     */
    public function __construct($name = null)
    {
        $this->pluginManager = new PluginManager();
        $this->themeManager  = new ThemeManager();
        $this->artisan       = new Artisan();
        $this->composer      = new Composer();
        $this->ocms          = new OctoberCms();

        $this->setPhp();

        parent::__construct($name);
    }

    /**
     * Set PHP version to be used in console commands
     */
    public function setPhp($php = 'php')
    {
        //IDEA: simple observer for changing the php version
        $this->php = $php;
        $this->artisan->setPhp($php);
        $this->pluginManager->setPhp($php);
        $this->themeManager->setPhp($php);
        $this->ocms->setPhp($php);
    }

    /**
     * Set output for all components
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
        $this->pluginManager->setOutput($output);
        $this->themeManager->setOutput($output);
        $this->composer->setOutput($output);
        $this->ocms->setOutput($output);
        $this->artisan->setOutput($output);
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
            ->setName('install')
            ->setDescription('Install October CMS.')
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Make the installer behave as if it is run for the first time. Existing files may get overwritten.'
            )
            ->addOption(
                'php',
                null,
                InputOption::VALUE_OPTIONAL,
                'Specify the path to a custom PHP binary',
                'php'
            );
    }

    /**
     * Execute the command.
     *
     * @param InputInterface  $input
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

        $this->force = $input->getOption('force');

        if ( ! empty($php = $input->getOption('php'))) {
            $this->setPhp($php);
        }

        $this->gitignore = new Gitignore($this->getGitignore());

        $status = $this->ocms->checkInstallation();

        if ($status < 2) {
            $this->ocms->clearProject()
                ->download();

            $this->write('Installing octobercms installer...');
            $this->composer->install();

            $this->write('Setting up config files...');
            $this->writeConfig($this->force);

            $this->prepareDatabase();

            $this->write('Install Octobercms...');
            $this->artisan->octoberBuild();

            $this->firstRun = true;
        } elseif  ($status === 2) {
            $this->ocms->download();

            $this->write('Installing octobercms installer...');
            $this->composer->install();
        } else {
            $this->write('Installing octobercms...');
            $this->composer->install();
        }

        $this->write('Migrating database...');
        $this->artisan->call('october:migrate');

        if (isset($this->config['cms']['theme'])) {
            $themeDeclaration = $this->config['cms']['theme'];
            $this->write('Installing Theme...');
            try {
                $this->themeManager->install($themeDeclaration);
            } catch (ThemeExistsException $e) {
                $this->write($e->getMessage(), 'comment');
            } catch (Throwable $e) {
                $this->write('Failed to install theme: ' . $e->getMessage(), 'error');
            }
        }  {
            $this->write('No theme to install', 'comment');
        }

        if (isset($this->config['plugins'])) {
            $pluginsDeclarations = $this->config['plugins'];
            $this->write('Installing plugins of october.yaml...');
            $this->installPlugins($pluginsDeclarations);
        } else {
            $this->write('No plugins to install');
        }

        if (isset($this->config['git']['plugins'])) {
            $deployment = $this->config['git']['plugins'];
            $this->write("Setting up ${deployment} deployment.");
            try {
                $deploymentObj = DeploymentFactory::createDeployment($deployment);
                $deploymentObj->install($this->force);
            } catch (DeploymentExistsException $e) {
                $this->write($e->getMessage(), 'comment');
            } catch (Throwable $e) {
                $this->write($e->getMessage(), 'error');

                return false;
            }
        } else {
            $this->write('No deployments to install');
        }

        $this->write('Creating .gitignore...');
        $this->gitignore->write();

        if ($this->firstRun) {
            $this->write('Removing demo data...');
            $this->artisan->call('october:fresh');

            $this->write('Migrating database (after updates)...');
            $this->artisan->call('october:migrate');

            $this->write('Creating README...');
            $this->copyReadme();

            $this->write('Cleaning up...');
            $this->cleanup();
        }

        $this->write('Clearing cache...');
        $this->artisan->call('clear-compiled');
        $this->artisan->call('cache:clear');

        $this->write('Application ready! Build something amazing.', 'comment');

        return 0;
    }

    /**
     * Handle installing plugins and updating them if possible
     *
     * @param array $pluginsDeclarations
     *
     * @return void
     */
    public function installPlugins($pluginsDeclarations)
    {
        foreach ($pluginsDeclarations as $pluginDeclaration) {
            $pluginInstalled = $this->pluginManager->isInstalled($pluginDeclaration);
            $installPlugin   = ! $pluginInstalled;

            list($update, $vendor, $plugin, $remote, $branch) = $this->pluginManager->parseDeclaration($pluginDeclaration);

            if ($pluginInstalled && ($update || ! $this->gitignore->hasPluginHeader($vendor, $plugin))) {
                if ($pluginInstalled && $remote) {
                    $this->write("-> Removing ${vendor}.${plugin} directory to re-download the newest version...",
                        'comment');

                    $this->pluginManager->removeDir($pluginDeclaration);
                    $installPlugin = true;
                } else {
                    $this->write("-> Skipping re-download of ${vendor}.${plugin}", 'comment');
                    $installPlugin = false;
                }
            }

            if ($installPlugin) {
                try {
                    $this->pluginManager->install($pluginDeclaration);
                } catch (Throwable $e) {
                    $this->write($e->getMessage(), 'error');
                    continue;
                }
            }

            if ($update === false && $remote) {
                $this->gitignore->addPlugin($vendor, $plugin);
            }
        }

        $this->write('Migrating plugin tables...');
        $this->artisan->call('october:migrate');
    }

    /**
     * Create the .env and config files.
     *
     * @param bool $force
     */
    protected function writeConfig($force = false)
    {
        $setup = new Setup($this->config, $this->output, $this->php, $this->artisan);
        $setup->config();

        if ($force || !file_exists($this->pwd() . '/.env' )) {
            $setup->env();
        }
        if (!file_exists($this->pwd() . '/auth.json' )) {
            $this->licenseKey();
        }
    }

    protected function licenseKey() {
        $this->artisan->call('project:set', [$this->config->october['licenseKey']]);
    }

    /**
     * Get the .gitignore or create it using template.
     *
     * @return string
     */
    protected function getGitignore()
    {
        $target = $this->path('.gitignore');

        if ($this->fileExists($target)) {
            return $target;
        }

        $templateName = 'gitignore';

        if ($this->config->git['bareRepo']) {
            $templateName .= '.bare';
        }

        $template = $this->getTemplate($templateName);

        $this->copy($template, $target);

        return $target;
    }

    /**
     * Copy the README template.
     *
     * @return void
     */
    protected function copyReadme()
    {
        $template = $this->getTemplate('README.md');
        $this->copy($template, 'README.md');
    }

    protected function cleanup()
    {
        if ( ! $this->firstRun) {
            return;
        }

        $remove = ['CONTRIBUTING.md', 'CHANGELOG.md', 'ISSUE_TEMPLATE.md'];
        foreach ($remove as $file) {
            $this->unlink(($this->path($file)));
        }
    }

    /**
     * Prepare database before migrations.
     */
    public function prepareDatabase()
    {
        // If SQLite database does not exist, create it
        if ($this->config->database['connection'] === 'sqlite') {
            $path = $this->config->database['database'];
            if ( ! $this->fileExists($path) && is_dir(dirname($path))) {
                $this->write("Creating $path ...");
                touch($path);
            }
        }
    }

    private function writeCmsDevConfig()
    {
        $cms = $this->getTemplate('dev.cms.php');
        $this->copy($cms, 'config/dev/cms.php');
    }
}

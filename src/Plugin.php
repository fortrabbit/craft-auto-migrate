<?php

namespace fortrabbit\CraftAutoMigrate;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use craft\console\Application;
use yii\console\Exception;

/**
 * Class Plugin
 *
 * @package fortrabbit\CraftAutoMigrate
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{

    const CRAFT_VERSION_WITH_PROJECT_CONFIG_SUPPORT = '3.1.0';

    /**
     * @var Composer
     */
    protected $composer;
    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var \craft\console\Application
     */
    protected $craft;

    /**
     * Register Composer events
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'runCommands'
        ];
    }

    /**
     * Initialize Composer plugin
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io       = $io;
    }

    /**
     * Script runner
     *
     * Runs migrate/all only if Craft is installed
     * Runs project-config/sync if enabled in config/general.php
     */
    public function runCommands()
    {
        $this->bootstrapCraft();

        if (!$this->craft instanceof Application) {
            return false;
        }

        if (!$this->craft->getIsInstalled()) {
            $this->io->writeError("Craft is not installed yet. No need to run scripts.");
            return true;
        }

        $this->io->write(PHP_EOL . "▶ <info>Craft auto migrate</info> [START]");

        // migrate/all
        try {
            $this->craft->runAction('migrate/all', ['interactive' => 0]);
        } catch (Exception $exception) {
            $this->io->writeError("Craft auto migrate [migrate/all ERROR]");
            return false;
        }

        // project-config/sync
        if ($this->useProjectConfigFile()) {
            try {
                $this->craft->runAction('project-config/sync', ['interactive' => 0]);
            } catch (Exception $exception) {
                $this->io->writeError("Craft auto migrate [project-config/sync ERROR]");
                return false;
            }
        }

        $this->io->write("▶ <info>Craft auto migrate</info> [END]" . PHP_EOL);

        return true;

    }

    /**
     * @return bool
     */
    protected function bootstrapCraft()
    {
        // Prevent multiple execution
        if (defined('CRAFT_BASE_PATH')) {
            return false;
        }

        // Detect the project root
        $root = $_SERVER["PWD"] ?? __DIR__;
        while (!file_exists($root . '/craft')) {
            $root .= '/..';
            if (substr_count($root, '/..') > 5) {
                $this->io->writeError('Unable to find the project root: craft binary is missing.');
                return false;
            }
        }

        // Craft constants
        define('CRAFT_VENDOR_PATH', $root . '/vendor');
        define('CRAFT_BASE_PATH', $root);
        define('YII_DEBUG', false);

        if (!file_exists($root . '/vendor/autoload.php')) {
            return false;
        }

        require_once $root . '/vendor/autoload.php';

        // Load .env
        if (file_exists($root . '/.env')) {
            $dotenv = (method_exists('\Dotenv\Dotenv', 'create'))
                ? \Dotenv\Dotenv::create($root)
                : new \Dotenv\Dotenv($root);
            $dotenv->load();
        }

        if (getenv('DISABLE_CRAFT_AUTOMIGRATE') == 1) {
            $this->io->writeError('Craft auto migrate disabled by ENV var: DISABLE_CRAFT_AUTOMIGRATE');
            return false;
        }

        // Bootstrap Craft
        $this->craft = require $root . '/vendor/craftcms/cms/bootstrap/console.php';

        return true;
    }

    /**
     * @return bool
     */
    protected function useProjectConfigFile(): bool
    {
        if (!$this->craft instanceof Application) {
            return false;
        }
        if (version_compare($this->craft->getVersion(), self::CRAFT_VERSION_WITH_PROJECT_CONFIG_SUPPORT) < 0) {
            return false;
        }
        if (!$this->craft->getConfig()->getGeneral()->hasProperty('useProjectConfigFile')) {
            return false;
        }
        if ($this->craft->getConfig()->getGeneral()->useProjectConfigFile) {
            return true;
        }

        return false;
    }

}

<?php

namespace fortrabbit\CraftAutoMigrate;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use craft\console\Application;
use yii\console\Exception;
use Dotenv\Dotenv;

/**
 * Class Plugin
 *
 * @package fortrabbit\CraftAutoMigrate
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{

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
     * Apply plugin modifications to Composer
     *
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io       = $io;
    }



    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'runMigration',
            ScriptEvents::POST_UPDATE_CMD  => 'runMigration'
        ];
    }

    /**
     * Run the migration only if Craft is installed
     */
    public function runMigration()
    {
        $this->bootstrapCraft();

        if (!$this->craft instanceof Application) {
            return false;
        }

        if (!$this->craft->getIsInstalled()) {
            $this->io->writeError("Craft is not installed yet. No need to run migrations.");
            return true;
        }

        $this->io->write("Auto-migration [start]");

        try {
            $this->craft->runAction('migrate/all', ['interactive' => 0]);
        } catch (Exception $exception) {
            $this->io->writeError("Auto-migration [error]");
            return false;
        }

        $this->io->write("Auto-migration [end]");
        return true;

    }

    /**
     * @return bool
     */
    protected function bootstrapCraft()
    {
        // Detect the project root
        $root = $_SERVER["PWD"] ?? __DIR__;
        while (!file_exists($root . '/craft')) {
            $root .= '/..';
            if (substr_count($root, '/..') > 5) {
                $this->io->writeError('Unable to find the project root: craft binary is missing.');
                return false;
            }
        }

        define('CRAFT_VENDOR_PATH', $root . '/vendor');
        define('CRAFT_BASE_PATH', $root);
        define('YII_DEBUG', false);

        if (!file_exists($root . '/vendor/autoload.php')) {
            return false;
        }

        require_once $root . '/vendor/autoload.php';

        // dotenv?
        if (file_exists($root . '/.env')) {
            $dotenv = new Dotenv($root);
            $dotenv->load();
        }

        // Bootstrap Craft
        $this->craft = require $root . '/vendor/craftcms/cms/bootstrap/console.php';

        return true;
    }

}

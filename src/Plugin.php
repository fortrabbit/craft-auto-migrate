<?php

namespace fortrabbit\CraftAutoMigrate;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Util\ProcessExecutor;

/**
 * Class Plugin
 *
 * @package fortrabbit\CraftAutoMigrate
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{

    const CRAFT_VERSION_WITH_PROJECT_CONFIG_SUPPORT = '3.1.0';

    const CRAFT_NOT_INSTALLED_MESSAGE = 'Craft isn’t installed yet!';

    /**
     * @var Composer
     */
    protected $composer;
    /**
     * @var IOInterface
     */
    protected $io;

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
        if (getenv('DISABLE_CRAFT_AUTOMIGRATE') == 1) {
            $this->io->writeError('Craft auto migrate disabled by ENV var: DISABLE_CRAFT_AUTOMIGRATE');
            return true;
        }

        if (!$this->isCraftInstalled()) {
            $this->io->writeError('Craft is not installed yet. Skipping migration.');
            return true;
        }

        $this->io->write(PHP_EOL . "▶ <info>Craft auto migrate</info> [START]");

        $cmd = new CraftCommand(
            ["migrate/all"],
            new ProcessExecutor($this->io)
        );

        if ($cmd->run()) {
            $this->io->write($cmd->getOutput());
        } else {
            $this->io->writeError(PHP_EOL . "▶ <info>Craft auto migrate</info> [migrate/all ERROR]");
            $this->io->write($cmd->getErrorOutput());
            return false;
        }


        if ($this->hasProjectConfigFile()) {

            $cmd = new CraftCommand(
                ["project-config/sync"],
                new ProcessExecutor($this->io)
            );

            if ($cmd->run()) {
                $this->io->write($cmd->getOutput());
            } else {
                $this->io->writeError(PHP_EOL . "▶ <info>Craft auto migrate</info> [project-config/sync ERROR]");
                $this->io->write($cmd->getErrorOutput());
                return false;
            }
        }

        $this->io->write("▶ <info>Craft auto migrate</info> [END]" . PHP_EOL);

        return true;
    }


    /**
     * Checks if Craft is installed by showing a help command
     *
     * @return bool
     */
    protected function isCraftInstalled()
    {
        $command = new CraftCommand(["migrate/all", "--help", "--color=0"], new ProcessExecutor($this->io));

        if (true !== $command->run()) {
            return false;
        }

        if (stristr($command->getOutput(), self::CRAFT_NOT_INSTALLED_MESSAGE)) {
            return false;
        }

        return true;
    }


    /**
     * @return bool
     */
    protected function hasProjectConfigFile(): bool
    {
        $projectRoot  = realpath(dirname(Factory::getComposerFile()));
        $pathToConfig = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'project.yaml';

        return (file_exists($pathToConfig)) ? true : false;
    }

}

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
    const CRAFT_NOT_INSTALLED_MESSAGE = 'Craft isn’t installed yet!';

    protected Composer $composer;

    protected IOInterface $io;

    /**
     * Register Composer events
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'runCommands'
        ];
    }

    /**
     * Initialize Composer plugin
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Script runner
     *
     * Runs migrate/all only if Craft is installed
     * Runs project-config/apply if enabled in config/general.php
     */
    public function runCommands(): bool
    {
        if (getenv('DISABLE_CRAFT_AUTOMIGRATE') == 1) {
            $this->io->write(
                PHP_EOL . "▶ <info>Craft auto migrate</info> disabled by ENV var: DISABLE_CRAFT_AUTOMIGRATE"
            );
            return true;
        }

        if (!$this->isCraftInstalled()) {
            $this->io->writeError(PHP_EOL . "▶ <info>Craft is not installed yet.</info> Skip migration.");
            return true;
        }

        $this->io->write(PHP_EOL . "▶ <info>Craft auto migrate</info> [START]");
        $cmd = new CraftCommand(["migrate/all"], new ProcessExecutor($this->io));

        if ($cmd->run()) {
            $this->io->write(PHP_EOL . "▶ <info>Craft auto migrate</info> [migrate/all]");
            $this->io->write(PHP_EOL . $cmd->getOutput());
        } else {
            $this->io->writeError(PHP_EOL . "▶ <info>Craft auto migrate</info> [migrate/all ERROR]");
            $this->io->writeError(PHP_EOL . $cmd->getErrorOutput());
            return false;
        }


        if ($this->hasProjectConfigFile()) {
            $args = ["project-config/apply"];

            if (getenv('PROJECT_CONFIG_FORCE_APPLY') == 1) {
                $args[] = '--force';
            }

            $cmd = new CraftCommand($args, new ProcessExecutor($this->io));

            if ($cmd->run()) {
                $this->io->write(PHP_EOL . "▶ <info>Craft auto migrate</info> [project-config/apply]");
                $this->io->write(PHP_EOL . $cmd->getOutput());
            } else {
                $this->io->writeError(PHP_EOL . "▶ <info>Craft auto migrate</info> [project-config/apply ERROR]");
                $this->io->writeError(PHP_EOL . $cmd->getErrorOutput());
                return false;
            }
        }

        $this->io->write(PHP_EOL . "▶ <info>Craft auto migrate</info> [END]" . PHP_EOL);

        return true;
    }


    /**
     * Checks if Craft is installed by showing a help command
     */
    protected function isCraftInstalled(): bool
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
        $projectRoot = realpath(dirname(Factory::getComposerFile()));
        $pathToConfigFile = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'project.yaml';
        $pathToConfigDir = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'project';

        return file_exists($pathToConfigFile) || is_dir($pathToConfigDir);
    }

    /**
     * @inheritDoc
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        // nothing to do
    }

    /**
     * @inheritDoc
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
        // nothing to do
    }
}

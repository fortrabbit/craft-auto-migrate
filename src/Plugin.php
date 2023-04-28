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
     * Runs up command if Craft is installed
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

        if ($this->hasProjectConfig()) {
            $args = ["up"];

            if (getenv('PROJECT_CONFIG_FORCE_APPLY') == 1) {
                $args[] = '--force';
            }

            $cmd = new CraftCommand($args, new ProcessExecutor($this->io));

            if ($cmd->run()) {
                $this->io->write(PHP_EOL . "▶ <info>Craft auto migrate</info> [up]");
                $this->io->write(PHP_EOL . $cmd->getOutput());

                // Remove project.yaml during deployment (non-interactive mode)
                if ($this->shouldRemoveProjectConfigAfterApply()) {
                    $projectConfigFile = $this->getProjectConfigFile();
                    if (is_string($projectConfigFile) && unlink($projectConfigFile)) {
                        $this->io->write(PHP_EOL . "$projectConfigFile removed");
                    }
                }


            } else {
                $this->io->writeError(PHP_EOL . "▶ <info>Craft auto migrate</info> [up ERROR]");
                $this->io->writeError(PHP_EOL . $cmd->getErrorOutput());
                return false;
            }
        } else {
            $this->io->write(PHP_EOL . "▶ <info>Project Config not found.</info>");
        }

        $this->io->write(PHP_EOL . "▶ <info>Craft auto migrate</info> [END]" . PHP_EOL);

        return true;
    }

    protected function shouldRemoveProjectConfigAfterApply(): bool
    {
        if (getenv('KEEP_PROJECT_CONFIG') == 1 || $this->io->isInteractive() === true) {
            return false;
        }

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


    protected function hasProjectConfig(): bool
    {
        $projectRoot = realpath(dirname(Factory::getComposerFile()));
        $pathToConfigFile = implode(DIRECTORY_SEPARATOR, [$projectRoot, 'config', 'project', 'project.yaml']);
        $pathToConfigDir = implode(DIRECTORY_SEPARATOR, [$projectRoot, 'config', 'project']);

        return file_exists($pathToConfigFile) || is_dir($pathToConfigDir);
    }

    protected function getProjectConfigFile(): ?string
    {
        $projectRoot = realpath(dirname(Factory::getComposerFile()));
        $projectConfigFile = implode(DIRECTORY_SEPARATOR, [$projectRoot, 'config', 'project', 'project.yaml']);

        return file_exists($projectConfigFile)
            ? $projectConfigFile
            : null;

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

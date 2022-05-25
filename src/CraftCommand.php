<?php

namespace fortrabbit\CraftAutoMigrate;

use Composer\Factory;
use Composer\Util\ProcessExecutor;
use Symfony\Component\Process\PhpExecutableFinder;

class CraftCommand
{
    private array $args = [];

    private ProcessExecutor $process;

    private $output;

    public function __construct(array $args, ProcessExecutor $process)
    {
        $this->args = $args;
        $this->process = $process;
    }


    /**
     * Full path to craft command
     */
    public static function getCraftCommand(): string
    {
        if (is_string(getenv('CRAFT_COMMAND'))) {
            return getenv('CRAFT_COMMAND');
        }

        $projectRoot  = realpath(dirname(Factory::getComposerFile()));
        $craftCommand = $projectRoot . DIRECTORY_SEPARATOR . 'craft';

        if (!file_exists($craftCommand)) {
            throw new \LogicException("Missing 'craft' executable in '$projectRoot'");
        }

        $finder = new PhpExecutableFinder();
        $php    = $finder->find();

        return "$php $craftCommand";
    }

    /**
     * Run Craft command
     */
    public function run(): bool
    {
        $command       = sprintf("%s %s", self::getCraftCommand(), implode(" ", $this->args));
        $exitCode = $this->process->execute($command, $this->output);

        return $exitCode === 0;
    }

    /**
     * Output of command
     */
    public function getOutput(): string
    {
        return trim($this->output);
    }

    /**
     * Error output of command
     */
    public function getErrorOutput(): string
    {
        return $this->process->getErrorOutput();
    }
}

<?php

namespace fortrabbit\CraftAutoMigrate;

use Composer\Factory;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Class CraftCommand
 *
 * @package fortrabbit\CraftAutoMigrate
 */
class CraftCommand
{

    private $args = [];

    /**
     * @var Process
     */
    private $process;

    public function __construct($args = [])
    {
        $this->args = $args;
    }


    /**
     * Full path to craft command
     *
     * @return string
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
    public function run()
    {
        $command       = sprintf("%s %s", self::getCraftCommand(), implode(" ", $this->args));
        $this->process = Process::fromShellCommandline($command);
        $this->process->mustRun();
    }

    /**
     * Output of command
     *
     * @return string
     */
    public function getOutput(): string
    {
        return trim($this->process->getOutput());
    }


}

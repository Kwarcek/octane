<?php

namespace Laravel\Octane\Commands\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

trait InstallsReactPhpDependencies
{
    /**
     * Ensure the ReactPHP package is installed.
     *
     * @return bool
     */
    protected function ensureReactPhpPackageIsInstalled()
    {
        if (! class_exists('React\Http\HttpServer')) {
            $this->components->error('The ReactPHP package is missing.');

            if ($this->confirm('Would you like to install it now?')) {
                $this->installReactPhpPackage();
            }

            return false;
        }

        return true;
    }

    /**
     * Install the ReactPHP package.
     *
     * @return void
     */
    protected function installReactPhpPackage()
    {
        $this->components->info('Installing ReactPHP package...');

        $this->runCommand([
            'composer',
            'require',
            'react/http',
            'react/socket',
        ]);
    }

    /**
     * Run the given command.
     *
     * @param  array  $command
     * @return void
     */
    protected function runCommand(array $command)
    {
        $process = $this->createProcess($command);

        $process->setTty(true);

        $process->run(function ($type, $line) {
            $this->output->write($line);
        });
    }

    /**
     * Create a new process instance.
     *
     * @param  array  $command
     * @return Process
     */
    protected function createProcess(array $command)
    {
        return new Process($command, base_path());
    }
}

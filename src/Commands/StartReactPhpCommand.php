<?php

namespace Laravel\Octane\Commands;

use Laravel\Octane\ReactPhp\ReactPhpExtension;
use Laravel\Octane\ReactPhp\ServerProcessInspector;
use Laravel\Octane\ReactPhp\ServerStateFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'octane:reactphp')]
class StartReactPhpCommand extends Command implements SignalableCommandInterface
{
    use Concerns\InteractsWithEnvironmentVariables, Concerns\InteractsWithServers;

    /**
     * The command's signature.
     *
     * @var string
     */
    public $signature = 'octane:reactphp
                    {--host= : The IP address the server should bind to}
                    {--port= : The port the server should be available on}
                    {--workers=auto : The number of workers that should be available to handle requests}
                    {--max-requests=500 : The number of requests to process before reloading the server}
                    {--watch : Automatically reload the server when the application is modified}
                    {--poll : Use file system polling while watching in order to watch files over a network}';

    /**
     * The command's description.
     *
     * @var string
     */
    public $description = 'Start the Octane ReactPHP server';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Handle the command.
     *
     * @return int
     */
    public function handle(
        ServerProcessInspector $inspector,
        ServerStateFile $serverStateFile,
        ReactPhpExtension $extension
    ) {
        if (! $extension->isInstalled()) {
            $this->components->error('The ReactPHP dependencies are missing. Please install them with: composer require react/http react/socket');

            return 1;
        }

        $this->ensurePortIsAvailable();

        if ($inspector->serverIsRunning()) {
            $this->components->error('Server is already running.');

            return 1;
        }

        $this->writeServerStateFile($serverStateFile, $extension);

        $this->forgetEnvironmentVariables();

        $server = tap(new Process([
            (new PhpExecutableFinder)->find(),
            ...config('octane.swoole.php_options', []),
            'reactphp-server',
            $serverStateFile->path(),
        ], realpath(__DIR__.'/../../bin'), [
            'APP_ENV' => app()->environment(),
            'APP_BASE_PATH' => base_path(),
            'LARAVEL_OCTANE' => 1,
        ]))->start();

        return $this->runServer($server, $inspector, 'reactphp');
    }

    /**
     * Write the ReactPHP server state file.
     *
     * @return void
     */
    protected function writeServerStateFile(
        ServerStateFile $serverStateFile,
        ReactPhpExtension $extension
    ) {
        $serverStateFile->write(array_merge($this->defaultServerOptions($extension), [
            'appName' => app()->environment(),
            'octaneConfig' => config('octane'),
            'publicPath' => public_path(),
        ]));
    }

    /**
     * Get the default server options.
     *
     * @return array
     */
    protected function defaultServerOptions(ReactPhpExtension $extension)
    {
        return array_merge($extension->defaultServerOptions(), [
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'workers' => $this->workerCount($extension),
            'max_requests' => $this->option('max-requests'),
            'watch' => $this->option('watch'),
            'poll' => $this->option('poll'),
        ]);
    }

    /**
     * Get the worker count.
     *
     * @return int
     */
    protected function workerCount(ReactPhpExtension $extension)
    {
        $workers = $this->option('workers');

        if ($workers === 'auto') {
            return $extension->workerCount();
        }

        return (int) $workers;
    }

    /**
     * Write the server output.
     *
     * @return void
     */
    protected function writeServerOutput($server)
    {
        $server->wait(function ($type, $data) {
            if ($type === Process::OUT) {
                $this->output->write($data);
            } else {
                $this->output->write('<error>'.$data.'</error>');
            }
        });
    }

    /**
     * Stop the server.
     *
     * @return void
     */
    protected function stopServer()
    {
        $inspector = app(ServerProcessInspector::class);

        if ($inspector->serverIsRunning()) {
            $inspector->stopServer();
        }
    }
}

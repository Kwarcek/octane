<?php

namespace Laravel\Octane\ReactPhp;

class ReactPhpExtension
{
    /**
     * Determine if the ReactPHP extension is installed.
     */
    public function isInstalled(): bool
    {
        return class_exists('React\Http\HttpServer') &&
               class_exists('React\EventLoop\Loop') &&
               class_exists('React\Socket\SocketServer');
    }

    /**
     * Get the default server options.
     */
    public function defaultServerOptions(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => 8000,
            'workers' => 'auto',
            'max_requests' => 500,
            'watch' => false,
            'poll' => false,
        ];
    }

    /**
     * Get the worker count.
     */
    public function workerCount(): int
    {
        return $this->getAutoWorkerCount();
    }

    /**
     * Get the auto worker count based on CPU cores.
     */
    protected function getAutoWorkerCount(): int
    {
        $cores = (int) shell_exec('nproc');

        return max(1, $cores * 2);
    }
}

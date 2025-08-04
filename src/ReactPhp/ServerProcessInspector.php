<?php

namespace Laravel\Octane\ReactPhp;

use Laravel\Octane\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Laravel\Octane\PosixExtension;
use Laravel\Octane\ReactPhp\ServerStateFile;

class ServerProcessInspector implements ServerProcessInspectorContract
{
    public function __construct(
        protected PosixExtension $posix
    ) {
    }
    /**
     * Determine if the server is running.
     */
    public function serverIsRunning(): bool
    {
        $state = $this->getServerState();

        if (! isset($state['pid'])) {
            return false;
        }

        return $this->posix->kill($state['pid'], 0);
    }

    /**
     * Reload the server.
     */
    public function reloadServer(): void
    {
        $state = $this->getServerState();

        if (isset($state['pid'])) {
            $this->posix->kill($state['pid'], SIGUSR1);
        }
    }

    /**
     * Stop the server.
     */
    public function stopServer(): bool
    {
        $state = $this->getServerState();

        if (isset($state['pid'])) {
            return $this->posix->kill($state['pid'], SIGTERM);
        }

        return false;
    }

    /**
     * Get the server state.
     */
    protected function getServerState(): array
    {
        $stateFile = new ServerStateFile($this->getStateFilePath());

        return $stateFile->read();
    }

    /**
     * Get the path to the server state file.
     */
    protected function getStateFilePath(): string
    {
        return storage_path('logs/octane-reactphp-server-state.json');
    }
}

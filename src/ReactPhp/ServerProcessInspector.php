<?php

namespace Laravel\Octane\ReactPhp;

use Laravel\Octane\Contracts\ServerProcessInspector as ServerProcessInspectorContract;
use Laravel\Octane\PosixExtension;
use Throwable;

class ServerProcessInspector implements ServerProcessInspectorContract
{
    public function __construct(
        protected ServerStateFile $serverStateFile,
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

        try {
            return $this->posix->kill($state['pid'], 0);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Reload the server.
     */
    public function reloadServer(): void
    {
        $state = $this->getServerState();

        if (isset($state['pid']) && defined('SIGUSR1')) {
            try {
                $this->posix->kill($state['pid'], SIGUSR1);
            } catch (Throwable) {
                //
            }
        }
    }

    /**
     * Stop the server.
     */
    public function stopServer(): bool
    {
        $state = $this->getServerState();

        if (isset($state['pid']) && defined('SIGTERM')) {
            try {
                return $this->posix->kill($state['pid'], SIGTERM);
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Get the server state.
     */
    protected function getServerState(): array
    {
        return $this->serverStateFile->read();
    }
}

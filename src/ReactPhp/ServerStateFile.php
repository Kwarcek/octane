<?php

namespace Laravel\Octane\ReactPhp;

use Laravel\Octane\Contracts\ServerProcessInspector;

class ServerStateFile
{
    /**
     * Create a new server state file instance.
     */
    public function __construct(protected string $path)
    {
    }

    /**
     * Write the server state to the file.
     */
    public function write(array $state): void
    {
        file_put_contents($this->path, json_encode([
            'state' => $state,
        ]));
    }

    /**
     * Read the server state from the file.
     */
    public function read(): array
    {
        if (! file_exists($this->path)) {
            return [];
        }

        $contents = json_decode(file_get_contents($this->path), true);

        return $contents['state'] ?? [];
    }

    /**
     * Get the path to the server state file.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Delete the server state file.
     */
    public function delete(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
} 
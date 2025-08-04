<?php

namespace Laravel\Octane\ReactPhp;

class WorkerState
{
    /**
     * The Laravel application instance.
     */
    public $app;

    /**
     * The ReactPHP client instance.
     */
    public $client;

    /**
     * The Octane worker instance.
     */
    public $worker;

    /**
     * The number of requests handled by this worker.
     */
    public $requestsHandled = 0;

    /**
     * Whether the worker should be terminated.
     */
    public $shouldTerminate = false;
}

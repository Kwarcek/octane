<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\PosixExtension;
use Laravel\Octane\ReactPhp\ServerProcessInspector;

class ReactPhpServerProcessInspectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->inspector = new ServerProcessInspector(new PosixExtension());
    }

    public function test_can_check_if_server_is_running()
    {
        $this->assertIsBool($this->inspector->serverIsRunning());
    }

    public function test_can_reload_server()
    {
        $this->inspector->reloadServer();
        $this->assertTrue(true);
    }

    public function test_can_stop_server()
    {
        $this->inspector->stopServer();
        $this->assertTrue(true);
    }
}

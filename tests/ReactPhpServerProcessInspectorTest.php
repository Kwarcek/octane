<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\PosixExtension;
use Laravel\Octane\ReactPhp\ServerProcessInspector;
use Laravel\Octane\ReactPhp\ServerStateFile;
use Mockery;

class ReactPhpServerProcessInspectorTest extends TestCase
{
    public function test_can_check_if_server_is_running()
    {
        $stateFile = new ServerStateFile(sys_get_temp_dir().'/octane-reactphp-state.json');
        $stateFile->write(['pid' => 123]);

        $posix = Mockery::mock(PosixExtension::class);
        $posix->shouldReceive('kill')->with(123, 0)->andReturn(true);

        $inspector = new ServerProcessInspector($stateFile, $posix);

        $this->assertTrue($inspector->serverIsRunning());

        $stateFile->delete();
    }

    /** @doesNotPerformAssertions @test */
    public function test_can_reload_server()
    {
        if (! defined('SIGUSR1')) {
            $this->markTestSkipped('SIGUSR1 is not available on this platform.');
        }

        $stateFile = new ServerStateFile(sys_get_temp_dir().'/octane-reactphp-state.json');
        $stateFile->write(['pid' => 123]);

        $posix = Mockery::mock(PosixExtension::class);
        $posix->shouldReceive('kill')->with(123, SIGUSR1)->once()->andReturn(true);

        (new ServerProcessInspector($stateFile, $posix))->reloadServer();

        $stateFile->delete();
    }

    public function test_can_stop_server()
    {
        if (! defined('SIGTERM')) {
            $this->markTestSkipped('SIGTERM is not available on this platform.');
        }

        $stateFile = new ServerStateFile(sys_get_temp_dir().'/octane-reactphp-state.json');
        $stateFile->write(['pid' => 123]);

        $posix = Mockery::mock(PosixExtension::class);
        $posix->shouldReceive('kill')->with(123, SIGTERM)->once()->andReturn(true);

        $this->assertTrue((new ServerProcessInspector($stateFile, $posix))->stopServer());

        $stateFile->delete();
    }
}

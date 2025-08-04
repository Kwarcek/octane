<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Commands\StartReactPhpCommand;
use Laravel\Octane\ReactPhp\ReactPhpExtension;

class StartReactPhpCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new StartReactPhpCommand;
    }

    public function test_can_check_if_reactphp_is_installed()
    {
        $extension = new ReactPhpExtension;

        $this->assertIsBool($extension->isInstalled());
    }

    public function test_can_get_default_server_options()
    {
        $extension = new ReactPhpExtension;
        $options = $extension->defaultServerOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('host', $options);
        $this->assertArrayHasKey('port', $options);
        $this->assertArrayHasKey('workers', $options);
        $this->assertArrayHasKey('max_requests', $options);
        $this->assertArrayHasKey('watch', $options);
        $this->assertArrayHasKey('poll', $options);
    }

    public function test_can_get_worker_count()
    {
        $extension = new ReactPhpExtension;
        $workers = $extension->workerCount();

        $this->assertIsInt($workers);
        $this->assertGreaterThan(0, $workers);
    }

    public function test_command_has_correct_signature()
    {
        $this->assertStringContainsString('octane:reactphp', $this->command->signature);
        $this->assertStringContainsString('--host=', $this->command->signature);
        $this->assertStringContainsString('--port=', $this->command->signature);
        $this->assertStringContainsString('--workers=', $this->command->signature);
        $this->assertStringContainsString('--max-requests=', $this->command->signature);
        $this->assertStringContainsString('--watch', $this->command->signature);
        $this->assertStringContainsString('--poll', $this->command->signature);
    }

    public function test_command_has_correct_description()
    {
        $this->assertEquals('Start the Octane ReactPHP server', $this->command->description);
    }

    public function test_command_is_hidden()
    {
        $reflection = new \ReflectionProperty($this->command, 'hidden');
        $reflection->setAccessible(true);
        $this->assertTrue($reflection->getValue($this->command));
    }
}

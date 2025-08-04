<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\ReactPhp\ReactPhpExtension;

class ReactPhpExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new ReactPhpExtension;
    }

    public function test_can_check_if_installed()
    {
        $this->assertIsBool($this->extension->isInstalled());
    }

    public function test_can_get_default_server_options()
    {
        $options = $this->extension->defaultServerOptions();

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
        $workers = $this->extension->workerCount();

        $this->assertIsInt($workers);
        $this->assertGreaterThan(0, $workers);
    }
}

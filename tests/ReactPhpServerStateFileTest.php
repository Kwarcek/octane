<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\ReactPhp\ServerStateFile;

class ReactPhpServerStateFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->stateFile = new ServerStateFile($this->getTempFile());
    }

    public function test_can_write_and_read_state()
    {
        $state = [
            'host' => '127.0.0.1',
            'port' => 8000,
            'workers' => 4,
        ];

        $this->stateFile->write($state);

        $this->assertEquals($state, $this->stateFile->read());
    }

    public function test_can_get_path()
    {
        $path = $this->getTempFile();
        $stateFile = new ServerStateFile($path);

        $this->assertEquals($path, $stateFile->path());
    }

    public function test_can_delete_file()
    {
        $state = ['test' => 'data'];
        $this->stateFile->write($state);

        $this->assertFileExists($this->stateFile->path());

        $this->stateFile->delete();

        $this->assertFileDoesNotExist($this->stateFile->path());
    }

    public function test_returns_empty_array_when_file_does_not_exist()
    {
        $this->assertEquals([], $this->stateFile->read());
    }

    protected function getTempFile(): string
    {
        return tempnam(sys_get_temp_dir(), 'octane-test-');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->stateFile->path())) {
            unlink($this->stateFile->path());
        }

        parent::tearDown();
    }
} 
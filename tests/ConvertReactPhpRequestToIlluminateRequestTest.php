<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Laravel\Octane\ReactPhp\Actions\ConvertReactPhpRequestToIlluminateRequest;
use Mockery;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class ConvertReactPhpRequestToIlluminateRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = new ConvertReactPhpRequestToIlluminateRequest;
    }

    public function test_can_convert_get_request()
    {
        $reactPhpRequest = Mockery::mock(ServerRequestInterface::class);
        $uri = Mockery::mock(UriInterface::class);
        $stream = Mockery::mock(StreamInterface::class);

        $reactPhpRequest->shouldReceive('getMethod')->andReturn('GET');
        $reactPhpRequest->shouldReceive('getUri')->andReturn($uri);
        $reactPhpRequest->shouldReceive('getHeaders')->andReturn(['Host' => ['localhost']]);
        $reactPhpRequest->shouldReceive('getBody')->andReturn($stream);
        $reactPhpRequest->shouldReceive('getQueryParams')->andReturn(['param' => 'value']);
        $reactPhpRequest->shouldReceive('getParsedBody')->andReturn([]);
        $reactPhpRequest->shouldReceive('getCookieParams')->andReturn(['cookie' => 'value']);
        $reactPhpRequest->shouldReceive('getUploadedFiles')->andReturn([]);

        $uri->shouldReceive('__toString')->andReturn('http://localhost/test?param=value');
        $uri->shouldReceive('getPath')->andReturn('/test');
        $uri->shouldReceive('getQuery')->andReturn('param=value');
        $uri->shouldReceive('getHost')->andReturn('localhost');
        $uri->shouldReceive('getPort')->andReturn(null);
        $uri->shouldReceive('getScheme')->andReturn('http');

        $stream->shouldReceive('getContents')->andReturn('');

        $request = ($this->converter)($reactPhpRequest, 'cli');

        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/test', $request->getPathInfo());
        $this->assertEquals('value', $request->query('param'));
        $this->assertEquals('value', $request->cookie('cookie'));
    }

    public function test_can_convert_post_request()
    {
        $reactPhpRequest = Mockery::mock(ServerRequestInterface::class);
        $uri = Mockery::mock(UriInterface::class);
        $stream = Mockery::mock(StreamInterface::class);

        $reactPhpRequest->shouldReceive('getMethod')->andReturn('POST');
        $reactPhpRequest->shouldReceive('getUri')->andReturn($uri);
        $reactPhpRequest->shouldReceive('getHeaders')->andReturn(['Host' => ['localhost']]);
        $reactPhpRequest->shouldReceive('getBody')->andReturn($stream);
        $reactPhpRequest->shouldReceive('getQueryParams')->andReturn([]);
        $reactPhpRequest->shouldReceive('getParsedBody')->andReturn(['post_data' => 'value']);
        $reactPhpRequest->shouldReceive('getCookieParams')->andReturn([]);
        $reactPhpRequest->shouldReceive('getUploadedFiles')->andReturn([]);

        $uri->shouldReceive('__toString')->andReturn('http://localhost/test');
        $uri->shouldReceive('getPath')->andReturn('/test');
        $uri->shouldReceive('getQuery')->andReturn('');
        $uri->shouldReceive('getHost')->andReturn('localhost');
        $uri->shouldReceive('getPort')->andReturn(null);
        $uri->shouldReceive('getScheme')->andReturn('http');

        $stream->shouldReceive('getContents')->andReturn('post_data=value');

        $request = ($this->converter)($reactPhpRequest, 'cli');

        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/test', $request->getPathInfo());
        $this->assertEquals('value', $request->input('post_data'));
    }

    public function test_can_convert_request_with_uploaded_files()
    {
        $reactPhpRequest = Mockery::mock(ServerRequestInterface::class);
        $uri = Mockery::mock(UriInterface::class);
        $stream = Mockery::mock(StreamInterface::class);
        $uploadedFile = Mockery::mock(\Psr\Http\Message\UploadedFileInterface::class);
        $fileStream = Mockery::mock(StreamInterface::class);

        $reactPhpRequest->shouldReceive('getMethod')->andReturn('POST');
        $reactPhpRequest->shouldReceive('getUri')->andReturn($uri);
        $reactPhpRequest->shouldReceive('getHeaders')->andReturn(['Host' => ['localhost']]);
        $reactPhpRequest->shouldReceive('getBody')->andReturn($stream);
        $reactPhpRequest->shouldReceive('getQueryParams')->andReturn([]);
        $reactPhpRequest->shouldReceive('getParsedBody')->andReturn([]);
        $reactPhpRequest->shouldReceive('getCookieParams')->andReturn([]);
        $reactPhpRequest->shouldReceive('getUploadedFiles')->andReturn(['file' => $uploadedFile]);

        $uri->shouldReceive('__toString')->andReturn('http://localhost/test');
        $uri->shouldReceive('getPath')->andReturn('/test');
        $uri->shouldReceive('getQuery')->andReturn('');
        $uri->shouldReceive('getHost')->andReturn('localhost');
        $uri->shouldReceive('getPort')->andReturn(null);
        $uri->shouldReceive('getScheme')->andReturn('http');

        $stream->shouldReceive('getContents')->andReturn('');

        $uploadedFile->shouldReceive('getClientFilename')->andReturn('test.txt');
        $uploadedFile->shouldReceive('getMediaType')->andReturn('text/plain');
        $uploadedFile->shouldReceive('getStream')->andReturn($fileStream);
        $uploadedFile->shouldReceive('getError')->andReturn(UPLOAD_ERR_OK);
        $uploadedFile->shouldReceive('getSize')->andReturn(10);

        $tempFile = tempnam(sys_get_temp_dir(), 'octane-upload-');
        file_put_contents($tempFile, 'test');

        $fileStream->shouldReceive('getMetadata')->with('uri')->andReturn($tempFile);

        $request = ($this->converter)($reactPhpRequest, 'cli');

        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('POST', $request->getMethod());
        $this->assertTrue($request->hasFile('file'));

        @unlink($tempFile);
    }

    public function test_can_convert_request_with_https()
    {
        $reactPhpRequest = Mockery::mock(ServerRequestInterface::class);
        $uri = Mockery::mock(UriInterface::class);
        $stream = Mockery::mock(StreamInterface::class);

        $reactPhpRequest->shouldReceive('getMethod')->andReturn('GET');
        $reactPhpRequest->shouldReceive('getUri')->andReturn($uri);
        $reactPhpRequest->shouldReceive('getHeaders')->andReturn(['Host' => ['localhost']]);
        $reactPhpRequest->shouldReceive('getBody')->andReturn($stream);
        $reactPhpRequest->shouldReceive('getQueryParams')->andReturn([]);
        $reactPhpRequest->shouldReceive('getParsedBody')->andReturn([]);
        $reactPhpRequest->shouldReceive('getCookieParams')->andReturn([]);
        $reactPhpRequest->shouldReceive('getUploadedFiles')->andReturn([]);

        $uri->shouldReceive('__toString')->andReturn('https://localhost/test');
        $uri->shouldReceive('getPath')->andReturn('/test');
        $uri->shouldReceive('getQuery')->andReturn('');
        $uri->shouldReceive('getHost')->andReturn('localhost');
        $uri->shouldReceive('getPort')->andReturn(443);
        $uri->shouldReceive('getScheme')->andReturn('https');

        $stream->shouldReceive('getContents')->andReturn('');

        $request = ($this->converter)($reactPhpRequest, 'cli');

        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertTrue($request->isSecure());
    }
} 
<?php

namespace Laravel\Octane\Tests;

use Illuminate\Http\Request;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\ReactPhp\ReactPhpClient;
use Laravel\Octane\RequestContext;
use Mockery;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use React\Promise\Deferred;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReactPhpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new ReactPhpClient;
    }

    public function test_can_marshal_request()
    {
        $reactPhpRequest = Mockery::mock(ServerRequestInterface::class);
        $uri = Mockery::mock(UriInterface::class);
        $stream = Mockery::mock(StreamInterface::class);

        $reactPhpRequest->shouldReceive('getMethod')->andReturn('GET');
        $reactPhpRequest->shouldReceive('getUri')->andReturn($uri);
        $reactPhpRequest->shouldReceive('getHeaders')->andReturn(['Host' => ['localhost']]);
        $reactPhpRequest->shouldReceive('getBody')->andReturn($stream);
        $reactPhpRequest->shouldReceive('getQueryParams')->andReturn([]);
        $reactPhpRequest->shouldReceive('getCookieParams')->andReturn([]);
        $reactPhpRequest->shouldReceive('getUploadedFiles')->andReturn([]);

        $uri->shouldReceive('__toString')->andReturn('http://localhost/test');
        $uri->shouldReceive('getPath')->andReturn('/test');
        $uri->shouldReceive('getQuery')->andReturn('');
        $uri->shouldReceive('getHost')->andReturn('localhost');
        $uri->shouldReceive('getPort')->andReturn(null);
        $uri->shouldReceive('getScheme')->andReturn('http');

        $stream->shouldReceive('getContents')->andReturn('');

        $context = new RequestContext;
        $context->reactPhpRequest = $reactPhpRequest;

        [$request, $context] = $this->client->marshalRequest($context);

        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/test', $request->getPathInfo());
    }

    public function test_can_serve_static_files()
    {
        $request = Request::create('/test.txt');
        $context = new RequestContext;
        $context->publicPath = __DIR__.'/public';
        $context->octaneConfig = ['serve_static_files' => true];

        $this->assertTrue($this->client->canServeRequestAsStaticFile($request, $context));
    }

    public function test_cannot_serve_static_files_when_disabled()
    {
        $request = Request::create('/test.txt');
        $context = new RequestContext;
        $context->publicPath = __DIR__.'/public';
        $context->octaneConfig = ['serve_static_files' => false];

        $this->assertFalse($this->client->canServeRequestAsStaticFile($request, $context));
    }

    public function test_cannot_serve_static_files_for_root_path()
    {
        $request = Request::create('/');
        $context = new RequestContext;
        $context->publicPath = __DIR__.'/public';
        $context->octaneConfig = ['serve_static_files' => true];

        $this->assertFalse($this->client->canServeRequestAsStaticFile($request, $context));
    }

    public function test_can_respond_with_simple_response()
    {
        $response = new SymfonyResponse('Hello World', 200);
        $octaneResponse = new OctaneResponse($response);

        $context = new RequestContext;
        $deferred = new Deferred;
        $context->reactPhpResponse = $deferred;

        $this->client->respond($context, $octaneResponse);

        $promise = $deferred->promise();
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $promise);
    }

    public function test_can_respond_with_streamed_response()
    {
        $response = new SymfonyResponse();
        $response->setCallback(function () {
            echo 'Streamed content';
        });

        $octaneResponse = new OctaneResponse($response);

        $context = new RequestContext;
        $deferred = new Deferred;
        $context->reactPhpResponse = $deferred;

        $this->client->respond($context, $octaneResponse);

        $promise = $deferred->promise();
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $promise);
    }

    public function test_can_handle_errors()
    {
        $exception = new \Exception('Test error');
        $request = Request::create('/test');
        $context = new RequestContext;
        $deferred = new Deferred;
        $context->reactPhpResponse = $deferred;

        $this->client->error($exception, app(), $request, $context);

        $promise = $deferred->promise();
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $promise);
    }

    public function test_can_serve_static_file()
    {
        $request = Request::create('/bar.txt');
        $context = new RequestContext;
        $context->publicPath = __DIR__.'/public/files';
        $deferred = new Deferred;
        $context->reactPhpResponse = $deferred;

        $this->client->serveStaticFile($request, $context);

        $promise = $deferred->promise();
        $this->assertInstanceOf(\React\Promise\PromiseInterface::class, $promise);
    }

    public function test_can_handle_uploaded_files()
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

        $fileStream->shouldReceive('getMetadata')->with('uri')->andReturn('/tmp/test');

        $context = new RequestContext;
        $context->reactPhpRequest = $reactPhpRequest;

        [$request, $context] = $this->client->marshalRequest($context);

        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('POST', $request->getMethod());
    }
}

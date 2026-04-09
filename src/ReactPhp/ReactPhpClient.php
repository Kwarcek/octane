<?php

namespace Laravel\Octane\ReactPhp;

use DateTime;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Contracts\ServesStaticFiles;
use Laravel\Octane\MimeType;
use Laravel\Octane\Octane;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use React\Http\Message\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReactPhpClient implements Client, ServesStaticFiles
{
    const STATUS_CODE_REASONS = [
        419 => 'Page Expired',
        425 => 'Too Early',
        431 => 'Request Header Fields Too Large',                             // RFC6585
        451 => 'Unavailable For Legal Reasons',                               // RFC7725
    ];

    public function __construct(protected int $chunkSize = 1048576)
    {
    }

    /**
     * Marshal the given request context into an Illuminate request.
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            (new Actions\ConvertReactPhpRequestToIlluminateRequest)(
                $context->reactPhpRequest,
                PHP_SAPI
            ),
            $context,
        ];
    }

    /**
     * Determine if the request can be served as a static file.
     */
    public function canServeRequestAsStaticFile(Request $request, RequestContext $context): bool
    {
        $octaneConfig = $context->octaneConfig ?? [];

        if (array_key_exists('serve_static_files', $octaneConfig) &&
            ! $octaneConfig['serve_static_files']) {
            return false;
        }

        if (! ($context->publicPath ?? false) ||
            $request->path() === '/') {
            return false;
        }

        $publicPath = realpath($context->publicPath) ?: $context->publicPath;

        $pathToFile = realpath($publicPath.'/'.$request->path());

        if ($this->isValidFileWithinSymlink($request, $publicPath, $pathToFile)) {
            $pathToFile = $publicPath.'/'.$request->path();
        }

        return $this->fileIsServable(
            $publicPath,
            $pathToFile,
        );
    }

    /**
     * Determine if the request is for a valid static file within a symlink.
     */
    private function isValidFileWithinSymlink(Request $request, string $publicPath, string $pathToFile): bool
    {
        $pathAfterSymlink = $this->pathAfterSymlink($publicPath, $request->path());

        return $pathAfterSymlink && str_ends_with($pathToFile, $pathAfterSymlink);
    }

    /**
     * If the given public file is within a symlinked directory, return the path after the symlink.
     *
     * @return string|bool
     */
    private function pathAfterSymlink(string $publicPath, string $path)
    {
        $directories = explode('/', $path);

        while ($directory = array_shift($directories)) {
            $publicPath .= '/'.$directory;

            if (is_link($publicPath)) {
                return implode('/', $directories);
            }
        }

        return false;
    }

    /**
     * Determine if the given file is servable.
     */
    protected function fileIsServable(string $publicPath, string $pathToFile): bool
    {
        return $pathToFile &&
               ! in_array(pathinfo($pathToFile, PATHINFO_EXTENSION), ['php', 'htaccess', 'config']) &&
               str_starts_with($pathToFile, $publicPath) &&
               is_file($pathToFile);
    }

    /**
     * Serve a static file.
     */
    public function serveStaticFile(Request $request, RequestContext $context): void
    {
        $publicPath = realpath($context->publicPath) ?: $context->publicPath;
        $pathToFile = realpath($publicPath.'/'.$request->path());

        if ($this->isValidFileWithinSymlink($request, $publicPath, $pathToFile)) {
            $pathToFile = $publicPath.'/'.$request->path();
        }

        $mimeType = MimeType::get(pathinfo($pathToFile, PATHINFO_EXTENSION));

        $response = new Response(
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Length' => filesize($pathToFile),
                'Last-Modified' => (new DateTime)->setTimestamp(filemtime($pathToFile))->format('D, d M Y H:i:s').' GMT',
            ],
            file_get_contents($pathToFile)
        );

        $context->reactPhpResponse->resolve($response);
    }

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, OctaneResponse $octaneResponse): void
    {
        $response = $octaneResponse->response;
        $reactPhpResponse = $context->reactPhpResponse;

        if ($response instanceof StreamedResponse) {
            $content = '';
            ob_start(function ($buffer) use (&$content) {
                $content .= $buffer;
                return '';
            });

            $response->sendContent();
            ob_end_clean();

            $reactPhpResponse->resolve(new Response(
                $response->getStatusCode(),
                $this->getResponseHeaders($response),
                $content
            ));

            return;
        }

        if ($response instanceof BinaryFileResponse) {
            $this->sendBinaryFileResponse($response, $reactPhpResponse);

            return;
        }

        $reactPhpResponse->resolve(new Response(
            $response->getStatusCode(),
            $this->getResponseHeaders($response),
            $response->getContent()
        ));
    }

    /**
     * Send a binary file response.
     */
    protected function sendBinaryFileResponse(BinaryFileResponse $response, $reactPhpResponse): void
    {
        $file = $response->getFile();
        $headers = $this->getResponseHeaders($response);

        if ($response->getStatusCode() === 200) {
            $headers['Content-Length'] = $file->getSize();
            $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $file->getMTime()).' GMT';
        }

        $reactPhpResponse->resolve(new Response(
            $response->getStatusCode(),
            $headers,
            file_get_contents($file->getPathname())
        ));
    }

    /**
     * Get the response headers.
     */
    protected function getResponseHeaders(SymfonyResponse $response): array
    {
        $headers = [];

        foreach ($response->headers->allPreserveCase() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        foreach ($response->headers->getCookies() as $cookie) {
            $headers['Set-Cookie'][] = (string) $cookie;
        }

        return $headers;
    }

    /**
     * Send an error message to the server.
     */
    public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
    {
        $statusCode = 500;
        $reason = $this->getReasonFromStatusCode($statusCode);

        $headers = [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ];

        if ($app->environment('local')) {
            $content = Octane::formatExceptionForClient($e, $app->make('config')->get('app.debug'));
        } else {
            $content = 'Internal Server Error';
        }

        $context->reactPhpResponse->resolve(new Response(
            $statusCode,
            $headers,
            $content
        ));
    }

    /**
     * Get the reason phrase for the given status code.
     */
    protected function getReasonFromStatusCode(int $code): ?string
    {
        return static::STATUS_CODE_REASONS[$code] ?? null;
    }
}

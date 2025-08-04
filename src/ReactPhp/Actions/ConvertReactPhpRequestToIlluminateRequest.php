<?php

namespace Laravel\Octane\ReactPhp\Actions;

use Illuminate\Http\Request;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class ConvertReactPhpRequestToIlluminateRequest
{
    /**
     * Convert a ReactPHP request to an Illuminate request.
     */
    public function __invoke(ServerRequestInterface $reactPhpRequest, string $sapi): Request
    {
        $method = $reactPhpRequest->getMethod();
        $uri = $reactPhpRequest->getUri();
        $headers = $reactPhpRequest->getHeaders();
        $body = $reactPhpRequest->getBody()->getContents();

        $symfonyHeaders = [];
        foreach ($headers as $name => $values) {
            $symfonyHeaders[strtolower($name)] = $values;
        }

        $symfonyRequest = SymfonyRequest::create(
            (string) $uri,
            $method,
            $reactPhpRequest->getQueryParams(),
            $reactPhpRequest->getCookieParams(),
            $this->convertUploadedFiles($reactPhpRequest->getUploadedFiles()),
            $_SERVER,
            $body
        );

        foreach ($symfonyHeaders as $name => $values) {
            $symfonyRequest->headers->set($name, $values);
        }

        $server = $_SERVER;
        $server['REQUEST_METHOD'] = $method;
        $server['REQUEST_URI'] = $uri->getPath();
        $server['QUERY_STRING'] = $uri->getQuery();
        $server['HTTP_HOST'] = $uri->getHost();
        $server['SERVER_PORT'] = $uri->getPort() ?: ($uri->getScheme() === 'https' ? 443 : 80);
        $server['HTTPS'] = $uri->getScheme() === 'https' ? 'on' : 'off';

        $symfonyRequest->server->replace($server);

        return Request::createFromBase($symfonyRequest);
    }

    /**
     * Convert ReactPHP uploaded files to Symfony format.
     */
    protected function convertUploadedFiles(array $uploadedFiles): array
    {
        $files = [];

        foreach ($uploadedFiles as $key => $uploadedFile) {
            if (is_array($uploadedFile)) {
                $files[$key] = $this->convertUploadedFiles($uploadedFile);
            } else {
                $files[$key] = [
                    'name' => $uploadedFile->getClientFilename(),
                    'type' => $uploadedFile->getMediaType(),
                    'tmp_name' => $uploadedFile->getStream()->getMetadata('uri'),
                    'error' => $uploadedFile->getError(),
                    'size' => $uploadedFile->getSize(),
                ];
            }
        }

        return $files;
    }
}

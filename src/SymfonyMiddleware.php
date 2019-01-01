<?php

namespace Woody\Middleware\Symfony;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class SymfonyMiddleware
 *
 * @package Woody\Middleware\Symfony
 */
class SymfonyMiddleware implements MiddlewareInterface
{

    /**
     * @var callable
     */
    protected $kernelGenerator;

    /**
     * SymfonyMiddleware constructor.
     *
     * @param callable $kernelGenerator
     */
    public function __construct(callable $kernelGenerator = null)
    {
        if (is_null($kernelGenerator) && class_exists('\App\Kernel')) {
            $kernelGenerator = function() {
                return new \App\Kernel($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG']);
            };
        } else {
            throw new \RuntimeException('Unable to detect kernel');
        }

        $this->kernelGenerator = $kernelGenerator;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $symfonyRequest = Request::create(
            $request->getUri(),
            $request->getMethod(),
            [],
            $request->getCookieParams(),
            $request->getUploadedFiles(),
            $request->getServerParams(),
            $request->getBody()->getContents()
        );

        $kernelGenerator = $this->kernelGenerator;
        $kernel = $kernelGenerator();

        /** @var \Symfony\Component\HttpFoundation\Response $symfonyResponse */
        $symfonyResponse = $kernel->handle($symfonyRequest);
        $kernel->terminate($symfonyRequest, $symfonyResponse);
        unset($kernel);

        $response = new Response(
            $symfonyResponse->getStatusCode(),
            $symfonyResponse->headers->all(),
            $symfonyResponse->getContent(),
            $symfonyResponse->getProtocolVersion()
        );

        return $response;
    }
}
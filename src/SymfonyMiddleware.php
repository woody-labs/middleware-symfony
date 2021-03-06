<?php

namespace Woody\Middleware\Symfony;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Woody\Http\Message\Response;
use Woody\Http\Server\Middleware\MiddlewareInterface;

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
        // Autodetect default Kernel in Symfony 4.x
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
     * @param bool $debug
     *
     * @return bool
     */
    public function isEnabled(bool $debug): bool
    {
        return true;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $symfonyRequest = Request::create(
                $request->getUri(),
                $request->getMethod(),
                [],
                $request->getCookieParams(),
                $request->getUploadedFiles(),
                $request->getServerParams(),
                $request->getBody()->getContents()
            );

            $kernel = call_user_func($this->kernelGenerator);

            /** @var \Symfony\Component\HttpFoundation\Response $symfonyResponse */
            $symfonyResponse = $kernel->handle($symfonyRequest);

            // Doctrine.
            if ($kernel->getContainer()->has('doctrine.orm.entity_manager')) {
                /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
                $entityManager = $kernel->getContainer()->get('doctrine.orm.entity_manager');

                $entityManager->clear();
                $entityManager->close();
                $entityManager->getConnection()->close();
            }

            $kernel->terminate($symfonyRequest, $symfonyResponse);
            unset($kernel);

            $response = new Response(
                $symfonyResponse->getStatusCode(),
                $symfonyResponse->headers->all(),
                $symfonyResponse->getContent(),
                $symfonyResponse->getProtocolVersion()
            );

            return $response;
        } catch(HttpException $e ) {
            throw new \Woody\Http\Message\Exception\HttpException(
                $e->getStatusCode(),
                $e->getMessage(),
                $e->getPrevious(),
                $e->getHeaders(),
                $e->getCode()
            );
        } catch(\Throwable $t) {
            throw $t;
        }
    }
}

# Middleware Symfony

This middleware load and dispatch request to Symfony Kernel.


## Implementation

Kernel is created for each request.

````php
// @todo: generate request

// Initialize logger.
$logHandler = new ErrorLogHandler();
$memoryUsageProcessor = new MemoryUsageProcessor(true, false);
$logger = new Logger('http', [$logHandler], [$memoryUsageProcessor]);

// Dispatch request into middleware stack.
$dispatcher = new Dispatcher();
$dispatcher->pipe(new CorrelationIdMiddleware());
$dispatcher->pipe(new LogsMiddleware($logger));
$dispatcher->pipe(new ExceptionMiddleware());
$dispatcher->pipe(new SymfonyMiddleware());

// @todo: add other middleware

$response = $dispatcher->handle($request);
````

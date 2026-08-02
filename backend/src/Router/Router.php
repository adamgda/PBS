<?php

declare(strict_types=1);

namespace App\Router;

use App\Http\Request;
use App\Http\Response;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

/**
 * Router oparty na nikic/fast-route.
 *
 * @phpstan-type Handler callable(\App\Http\Request, array<string, string>): \App\Http\Response
 */
final class Router
{
    private readonly Dispatcher $dispatcher;

    /**
     * @param array<array{method: string, path: string, handler: Handler}> $routes
     */
    public function __construct(array $routes)
    {
        $this->dispatcher = simpleDispatcher(static function (RouteCollector $collector) use ($routes): void {
            foreach ($routes as $route) {
                $collector->addRoute($route['method'], $route['path'], $route['handler']);
            }
        });
    }

    /**
     * @return array{status: int, handler: Handler|null, params: array<string, string>}
     */
    public function dispatch(Request $request): array
    {
        $routeInfo = $this->dispatcher->dispatch($request->method(), $request->path());

        if ($routeInfo[0] === Dispatcher::NOT_FOUND) {
            return ['status' => 404, 'handler' => null, 'params' => []];
        }

        if ($routeInfo[0] === Dispatcher::METHOD_NOT_ALLOWED) {
            return ['status' => 405, 'handler' => null, 'params' => []];
        }

        /** @var Handler $handler */
        $handler = $routeInfo[1];
        /** @var array<string, string> $params */
        $params = $routeInfo[2];

        return ['status' => 200, 'handler' => $handler, 'params' => $params];
    }

    public function handle(Request $request): Response
    {
        $result = $this->dispatch($request);

        if ($result['status'] === 404) {
            return Response::error(404, 'Not Found');
        }

        if ($result['status'] === 405) {
            return Response::error(405, 'Method Not Allowed');
        }

        $handler = $result['handler'];
        if ($handler === null) {
            return Response::error(500, 'No handler');
        }

        return $handler($request, $result['params']);
    }
}
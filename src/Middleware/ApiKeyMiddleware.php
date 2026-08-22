<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class ApiKeyMiddleware implements MiddlewareInterface
{
    public function __construct(private string $apiKey) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        $token = preg_match('/^Bearer\s+(\S+)$/i', $header, $m) === 1 ? $m[1] : '';

        // hash_equals evita timing attack na comparação da chave.
        if ($token === '' || !hash_equals($this->apiKey, $token)) {
            $response = new Response();
            $response->getBody()->write(json_encode(['erro' => 'Não autorizado'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $response
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withStatus(401);
        }

        return $handler->handle($request);
    }
}

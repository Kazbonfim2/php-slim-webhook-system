<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\WebhookService;
use DomainException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

final class WebhookController
{
    public function __construct(private WebhookService $service) {}

    public function cadastrar(Request $request, Response $response): Response
    {
        return $this->run($response, function () use ($request) {
            $body = $this->body($request);
            $url = isset($body['url']) ? (string) $body['url'] : '';

            return [201, $this->service->cadastrar($url)];
        });
    }

    public function disparar(Request $request, Response $response): Response
    {
        return $this->run($response, function () use ($request) {
            $body = $this->body($request);
            $destinoId = isset($body['destino_id']) ? (int) $body['destino_id'] : 0;

            return [201, $this->service->disparar($destinoId, $body['payload'] ?? null)];
        });
    }

    public function historico(Request $request, Response $response): Response
    {
        return $this->run($response, fn () => [200, $this->service->historico()]);
    }

    private function body(Request $request): array
    {
        $parsed = $request->getParsedBody();

        return is_array($parsed) ? $parsed : [];
    }

    private function run(Response $response, callable $fn): Response
    {
        try {
            [$status, $data] = $fn();
            return $this->json($response, $data, $status);
        } catch (InvalidArgumentException $e) {
            return $this->json($response, ['erro' => $e->getMessage()], 400);
        } catch (DomainException $e) {
            $status = $e->getCode() >= 400 ? (int) $e->getCode() : 422;
            return $this->json($response, ['erro' => $e->getMessage()], $status);
        } catch (Throwable $e) {
            return $this->json($response, ['erro' => 'Erro interno'], 500);
        }
    }

    private function json(Response $response, mixed $data, int $status): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\WebhookRepository;
use DomainException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

final class WebhookService
{
    public function __construct(
        private WebhookRepository $repo,
        private Client $http,
    ) {}

    public static function urlValida(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        // ponytail: aceita LAN/localhost. Produção = denylist de IP privado (SSRF).

        return in_array($scheme, ['http', 'https'], true);
    }

    public static function statusDeHttp(int $code): string
    {
        return $code >= 200 && $code < 300 ? 'sucesso' : 'falha';
    }

    public function cadastrar(string $url): array
    {
        $url = trim($url);
        if (!self::urlValida($url)) {
            throw new InvalidArgumentException('URL inválida. Use http ou https.');
        }

        return $this->repo->criarDestino($url);
    }

    public function disparar(int $destinoId, mixed $payload): array
    {
        if ($destinoId < 1) {
            throw new InvalidArgumentException('destino_id inválido.');
        }
        if (!is_array($payload)) {
            throw new InvalidArgumentException('payload deve ser um objeto JSON.');
        }

        $destino = $this->repo->buscarDestino($destinoId);
        if ($destino === null) {
            throw new DomainException('Destino não encontrado.', 404);
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            // http_errors=false: 4xx/5xx viram log de falha, não exception. Timeout no Client.
            $response = $this->http->post($destino['url'], ['json' => $payload]);
            $httpCode = $response->getStatusCode();
            $status = self::statusDeHttp($httpCode);
            $erro = $status === 'falha' ? substr((string) $response->getBody(), 0, 500) : null;
        } catch (GuzzleException $e) {
            $httpCode = null;
            $status = 'falha';
            $erro = $e->getMessage();
        }

        return $this->formatar($this->repo->registrarDisparo(
            $destinoId,
            $payloadJson,
            $status,
            $httpCode,
            $erro,
        ));
    }

    public function historico(): array
    {
        return array_map(fn (array $row) => $this->formatar($row), $this->repo->listarDisparos());
    }

    /** @param array<string, mixed> $row */
    private function formatar(array $row): array
    {
        $decoded = json_decode((string) $row['payload'], true);

        return [
            'id' => (int) $row['id'],
            'destino_id' => (int) $row['destino_id'],
            'url' => $row['url'],
            'payload' => is_array($decoded) ? $decoded : $row['payload'],
            'status' => $row['status'],
            'http_code' => $row['http_code'] !== null ? (int) $row['http_code'] : null,
            'erro' => $row['erro'],
            'criado_em' => $row['criado_em'],
        ];
    }
}

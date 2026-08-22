<?php

declare(strict_types=1);

namespace App\Repositories;

final class WebhookRepository
{
    public function __construct(private string $path) {}

    public function criarDestino(string $url): array
    {
        return $this->withStore(true, function (array &$data) use ($url): array {
            $id = ++$data['seq_destinos'];
            $row = [
                'id' => $id,
                'url' => $url,
                'criado_em' => date('Y-m-d H:i:s'),
            ];
            $data['destinos'][] = $row;
            return $row;
        });
    }

    public function buscarDestino(int $id): ?array
    {
        return $this->withStore(false, function (array &$data) use ($id): ?array {
            foreach ($data['destinos'] as $row) {
                if ((int) $row['id'] === $id) {
                    return $row;
                }
            }
            return null;
        });
    }

    public function registrarDisparo(
        int $destinoId,
        string $payloadJson,
        string $status,
        ?int $httpCode,
        ?string $erro,
    ): array {
        return $this->withStore(true, function (array &$data) use ($destinoId, $payloadJson, $status, $httpCode, $erro): array {
            $destino = null;
            foreach ($data['destinos'] as $row) {
                if ((int) $row['id'] === $destinoId) {
                    $destino = $row;
                    break;
                }
            }
            if ($destino === null) {
                throw new \RuntimeException("Destino {$destinoId} sumiu no meio do disparo");
            }

            $id = ++$data['seq_disparos'];
            $row = [
                'id' => $id,
                'destino_id' => $destinoId,
                'url' => $destino['url'],
                'payload' => $payloadJson,
                'status' => $status,
                'http_code' => $httpCode,
                'erro' => $erro,
                'criado_em' => date('Y-m-d H:i:s'),
            ];
            $data['disparos'][] = $row;
            return $row;
        });
    }

    public function listarDisparos(): array
    {
        return $this->withStore(false, function (array &$data): array {
            $urls = [];
            foreach ($data['destinos'] as $d) {
                $urls[(int) $d['id']] = $d['url'];
            }
            $out = [];
            foreach ($data['disparos'] as $row) {
                $row['url'] = $urls[(int) $row['destino_id']] ?? $row['url'];
                $out[] = $row;
            }
            return array_reverse($out);
        });
    }

    /**
     * ponytail: lock global no arquivo. Teto = um writer por vez.
     * Upgrade: SQLite quando a escrita concorrente doer.
     *
     * @template T
     * @param callable(array<string, mixed> &$data): T $fn
     * @return T
     */
    private function withStore(bool $write, callable $fn): mixed
    {
        $dir = dirname($this->path);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $fh = fopen($this->path, 'c+');
        if ($fh === false) {
            throw new \RuntimeException('Não foi possível abrir o store');
        }

        flock($fh, $write ? LOCK_EX : LOCK_SH);
        try {
            $raw = stream_get_contents($fh) ?: '';
            $data = $raw === ''
                ? ['destinos' => [], 'disparos' => [], 'seq_destinos' => 0, 'seq_disparos' => 0]
                : json_decode($raw, true);
            if (!is_array($data)) {
                throw new \RuntimeException('Store corrompido');
            }

            $result = $fn($data);

            if ($write) {
                rewind($fh);
                ftruncate($fh, 0);
                fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE));
                fflush($fh);
            }

            return $result;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}

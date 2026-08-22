<?php

declare(strict_types=1);

/**
 * Self-check sem rede: validação de URL e mapeamento HTTP -> status.
 * Roda: php bin/check.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\WebhookService;

$ok = static function (bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "OK: {$msg}\n";
};

$ok(WebhookService::urlValida('https://exemplo.com/hook') === true, 'https válido');
$ok(WebhookService::urlValida('http://localhost:9999/x') === true, 'http local válido');
$ok(WebhookService::urlValida('ftp://exemplo.com') === false, 'ftp inválido');
$ok(WebhookService::urlValida('nao-e-url') === false, 'lixo inválido');
$ok(WebhookService::statusDeHttp(200) === 'sucesso', '200 sucesso');
$ok(WebhookService::statusDeHttp(201) === 'sucesso', '201 sucesso');
$ok(WebhookService::statusDeHttp(404) === 'falha', '404 falha');
$ok(WebhookService::statusDeHttp(500) === 'falha', '500 falha');
$ok(WebhookService::statusDeHttp(0) === 'falha', '0 falha');

echo "check passou\n";

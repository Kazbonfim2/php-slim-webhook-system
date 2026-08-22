# PHP Slim Webhook System

## O que resolve

API REST mínima pra cadastrar URLs de destino, disparar um payload HTTP e guardar o histórico com status de sucesso ou falha. Serve pra estudar Slim 4, PHP-DI e chamada HTTP de saída com Guzzle, sem fila e sem worker.

## Como pode ser usado

Como microsserviço local (ou Docker) que outros sistemas chamam quando precisam notificar uma URL externa. Útil como peça de portfólio, sandbox de webhooks ou ponto de partida pra evoluir retry/assinatura HMAC depois.

## Tecnologias

- PHP 8.3 (strict types)
- Slim 4 + slim/psr7
- PHP-DI (autowire)
- Guzzle
- Store JSON em arquivo (`flock`)
- Docker Compose
- Servidor embutido do PHP (`php -S`)

## Funcionalidades

- `POST /destinos` — cadastra URL `http`/`https`
- `POST /disparos` — envia o JSON da URL e grava o resultado
- `GET /disparos` — lista o histórico (mais recente primeiro)
- Autenticação por API Key (`Authorization: Bearer …`)
- Timeout de 5s no disparo (a API não fica presa em destino lento)
- Status `sucesso` só em HTTP 2xx; o resto (inclusive timeout) vira `falha`

## Como rodar

### Docker (recomendado)

```bash
cp .env.example .env
docker compose up -d --build
```

API: `http://localhost:8081`

### Sem Docker

1. `composer install`
2. `cp .env.example .env`
3. `php -d opcache.enable=0 -S localhost:8081 -t public public/index.php`

## Estrutura do projeto

```
public/index.php              # front controller + container PHP-DI
src/Config/Env.php            # leitura simples de .env
src/Middleware/               # Bearer API Key
src/Controllers/              # camada HTTP
src/Services/                 # regras + Guzzle
src/Repositories/             # destinos + disparos em JSON
bin/check.php                 # self-check sem rede
```

## Testes

Self-check (sem Docker, sem rede):

```bash
composer install
php bin/check.php
```

GitHub Actions (`.github/workflows/ci.yml`): `composer install` + o mesmo check e `docker compose build`.

Smoke da API (stack no ar, chave padrão `dev-secret`):

```bash
TOKEN='Authorization: Bearer dev-secret'

curl -s -X POST http://localhost:8081/destinos \
  -H "$TOKEN" -H 'Content-Type: application/json' \
  -d '{"url":"https://httpbin.org/post"}'

curl -s -X POST http://localhost:8081/disparos \
  -H "$TOKEN" -H 'Content-Type: application/json' \
  -d '{"destino_id":1,"payload":{"evento":"pagamento","id":99}}'

curl -s http://localhost:8081/disparos -H "$TOKEN"
```

Sem Bearer a API responde `401`.

## Demonstração

| Método | Rota | Body | Efeito |
|--------|------|------|--------|
| `POST` | `/destinos` | `{"url":"https://…"}` | cadastra destino |
| `POST` | `/disparos` | `{"destino_id":1,"payload":{…}}` | dispara e loga |
| `GET` | `/disparos` | — | histórico |

Exemplo de resposta de disparo:

```json
{
  "id": 1,
  "destino_id": 1,
  "url": "https://httpbin.org/post",
  "payload": {"evento": "pagamento", "id": 99},
  "status": "sucesso",
  "http_code": 200,
  "erro": null,
  "criado_em": "2026-08-22 06:22:00"
}
```

## Licença

MIT

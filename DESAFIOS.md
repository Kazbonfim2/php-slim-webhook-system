# Desafios enfrentados

Anotações do que pegou no caminho. Nada de post mortem formal — só o que realmente doeu.

## Guzzle gritando 4xx como se fosse bug meu

Primeira tentação: deixar o default do Guzzle (`http_errors = true`). Aí um destino que responde 404 vira exception, eu caio no `catch` e perco o status HTTP. Passei `http_errors => false` no Client. 2xx vira `sucesso`, qualquer outro código vira `falha` com o body cortado em 500 chars. Timeout e DNS ainda explodem em exception — aí sim o `catch` marca `falha` sem `http_code`.

Timeout de 5s entra no mesmo saco. Sem isso, um webhook lento segura o worker do `php -S` e a API inteira parece travada.

## Bearer não é “mandar a chave no body”

O enunciado pede API Key. Dá pra aceitar `X-Api-Key`, query string, body… Bagunça. Fechei em `Authorization: Bearer <chave>` lida do `.env`. Comparação com `hash_equals` pra não vazar a chave por timing. Errado ou ausente: 401 antes de tocar no banco.

Detalhe do Slim: middleware empilha LIFO. Quem entra por último roda primeiro. Auth adicionada depois do body parser — recusa sem gastar parse se o header já veio podre? Na prática o parse é barato. O que importa é não esquecer o `add()` e deixar a rota aberta.

## PDO sem driver é 500 mudo

Primeira versão foi SQLite. No Docker ia. No PHP do host: `could not find driver` — PDO instalado, `pdo_sqlite` não. Fatal antes do `catch` do controller, curl via `500` com body vazio.

O dado aqui é lista de URLs + log. Troquei por JSON com `flock`. Roda em qualquer PHP 8, sem extensão, sem segundo container. Teto conhecido: um writer por vez. Se a concorrência doer, aí sim SQLite — e só depois de ter o driver no ambiente.

## URL “válida” ainda é SSRF de brinde

`FILTER_VALIDATE_URL` + scheme `http`/`https` barra `ftp://` e lixo. Não barra `http://127.0.0.1` nem metadata de cloud. Pra demo local (httpbin, outro container) isso é feature. Em produção vira denylist de IP privado. Deixei passar de propósito; o check cobre o filtro, não o SSRF.

## `php -S` de novo sem o script router

Já tinha me queimado nisso: sem `public/index.php` no comando, POST chega e o body some. Compose sobe com `-t public public/index.php` e opcache desligado no volume, senão edito o PHP e o container serve a versão velha.

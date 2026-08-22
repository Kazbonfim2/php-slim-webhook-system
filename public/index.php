<?php

declare(strict_types=1);

use App\Config\Env;
use App\Controllers\WebhookController;
use App\Middleware\ApiKeyMiddleware;
use App\Repositories\WebhookRepository;
use DI\Container;
use GuzzleHttp\Client;
use Slim\Factory\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

$root = dirname(__DIR__);
$storePath = Env::get('STORE_PATH');
if (!str_starts_with($storePath, '/')) {
    $storePath = $root . '/' . $storePath;
}

$container = new Container();
$container->set(WebhookRepository::class, fn () => new WebhookRepository($storePath));
$container->set(Client::class, fn () => new Client([
    'timeout' => 5,
    'connect_timeout' => 3,
    'http_errors' => false,
    'allow_redirects' => false,
]));

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->add(new ApiKeyMiddleware(Env::get('API_KEY')));

$app->post('/destinos', [WebhookController::class, 'cadastrar']);
$app->post('/disparos', [WebhookController::class, 'disparar']);
$app->get('/disparos', [WebhookController::class, 'historico']);

$app->run();

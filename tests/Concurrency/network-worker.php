<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;
use Passa\Network\NetworkSignature;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);

[, $uri, $body, $startAt] = $argv;

$app->instance('request', Request::create('/'));
$kernel->bootstrap();

while (microtime(true) < (float) $startAt) {
    Sleep::usleep(500);
}

$timestamp = (string) Date::now()->getTimestamp();

$request = Request::create($uri, 'POST', server: [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_X_NETWORK_TIMESTAMP' => $timestamp,
    'HTTP_X_NETWORK_SIGNATURE' => NetworkSignature::fromConfig()->sign($timestamp, $body),
], content: $body);

$response = $kernel->handle($request);

echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode((string) $response->getContent(), true)]);

$kernel->terminate($request, $response);

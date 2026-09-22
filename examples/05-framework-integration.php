<?php

declare(strict_types=1);

/*
 * Reference wiring for Laravel and Symfony, plus the PSR-18 transport.
 *
 * This file is documentation rather than a script: it is not meant to be run
 * directly. Copy the block you need into your application.
 */

use BoudhraaDhia7\Vultr\Config;
use BoudhraaDhia7\Vultr\VultrClient;

require __DIR__.'/../vendor/autoload.php';

// ---------------------------------------------------------------------------
// Plain PHP
// ---------------------------------------------------------------------------

$vultr = VultrClient::create($_ENV['VULTR_API_KEY'] ?? '');

// Tuning: shorter timeouts and more retries for a background worker.
$worker = new VultrClient(
    Config::create($_ENV['VULTR_API_KEY'] ?? '')
        ->withTimeout(15.0)
        ->withConnectTimeout(5.0)
        ->withMaxRetries(4)
        ->withRetryDelays(baseDelay: 1.0, maxDelay: 30.0),
);

// ---------------------------------------------------------------------------
// Laravel: register a singleton in a service provider
// ---------------------------------------------------------------------------

/*
// config/services.php
return [
    'vultr' => [
        'key' => env('VULTR_API_KEY'),
    ],
];

// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(VultrClient::class, function ($app) {
        return new VultrClient(
            Config::create((string) config('services.vultr.key'))->withMaxRetries(3),
            // Reuse Laravel's Guzzle instance so the SDK inherits its middleware.
            new BoudhraaDhia7\Vultr\Http\Psr18HttpClient(
                new GuzzleHttp\Client(),
                new GuzzleHttp\Psr7\HttpFactory(),
                new GuzzleHttp\Psr7\HttpFactory(),
            ),
        );
    });
}

// Then type-hint VultrClient anywhere it is needed.
public function __construct(private readonly VultrClient $vultr) {}
*/

// ---------------------------------------------------------------------------
// Symfony: wire it in config/services.yaml
// ---------------------------------------------------------------------------

/*
# config/services.yaml
services:
    BoudhraaDhia7\Vultr\Config:
        factory: ['BoudhraaDhia7\Vultr\Config', 'create']
        arguments: ['%env(VULTR_API_KEY)%']

    BoudhraaDhia7\Vultr\Http\Psr18HttpClient:
        arguments:
            $client: '@Psr\Http\Client\ClientInterface'
            $requestFactory: '@Psr\Http\Message\RequestFactoryInterface'
            $streamFactory: '@Psr\Http\Message\StreamFactoryInterface'

    BoudhraaDhia7\Vultr\VultrClient:
        arguments:
            $config: '@BoudhraaDhia7\Vultr\Config'
            $httpClient: '@BoudhraaDhia7\Vultr\Http\Psr18HttpClient'
*/

// ---------------------------------------------------------------------------
// An endpoint the SDK does not model yet
// ---------------------------------------------------------------------------

// Authentication, retries, error mapping and JSON decoding still apply.
$kubernetes = $vultr->request('GET', 'v2/kubernetes/clusters', ['per_page' => 25]);

// Or keep the raw response when headers matter.
$response = $vultr->send('GET', 'v2/account');
$remaining = $response->rateLimitRemaining();

unset($worker, $kubernetes, $remaining);

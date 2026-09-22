<?php

declare(strict_types=1);

/*
 * Deploys an instance and waits for it to come up.
 *
 * This example creates real, billable resources. It asks for confirmation
 * first and prints the id so the instance can be destroyed afterwards.
 *
 * Run with:
 *     VULTR_API_KEY=your-key php examples/02-create-instance.php
 */

use BoudhraaDhia7\Vultr\Builder\InstanceBuilder;
use BoudhraaDhia7\Vultr\Exception\VultrExceptionInterface;
use BoudhraaDhia7\Vultr\VultrClient;

require __DIR__.'/../vendor/autoload.php';

const REGION = 'ewr';
const PLAN = 'vc2-1c-1gb';
const OS_ID = 1743; // Ubuntu 22.04 LTS x64

$vultr = VultrClient::fromEnvironment();

printf('About to deploy a %s instance in %s. This is billable. Continue? [y/N] ', PLAN, REGION);

if ('y' !== strtolower(trim((string) fgets(STDIN)))) {
    echo 'Aborted.', PHP_EOL;

    exit(0);
}

$blueprint = InstanceBuilder::in(REGION)
    ->plan(PLAN)
    ->fromOperatingSystem(OS_ID)
    ->label('sdk-example')
    ->hostname('sdk-example')
    ->tags(['example'])
    ->enableIpv6()
    ->userData(<<<'CLOUD_CONFIG'
        #cloud-config
        package_update: true
        packages:
          - nginx
        CLOUD_CONFIG);

try {
    $instance = $vultr->instances()->create($blueprint);
} catch (VultrExceptionInterface $exception) {
    fwrite(STDERR, 'Could not deploy: '.$exception->getMessage().PHP_EOL);

    exit(1);
}

$instanceId = (string) $instance['id'];

printf('Created %s, waiting for it to become active...%s', $instanceId, PHP_EOL);

$deadline = time() + 300;

while (time() < $deadline) {
    $current = $vultr->instances()->get($instanceId);

    if ('active' === $current['status'] && 'ok' === $current['server_status']) {
        printf('Ready at %s%s', $current['main_ip'], PHP_EOL);

        break;
    }

    printf('  status=%s server_status=%s%s', $current['status'], $current['server_status'], PHP_EOL);
    sleep(10);
}

printf('%sDestroy it with:%s  $vultr->instances()->delete(%s);%s', PHP_EOL, PHP_EOL, var_export($instanceId, true), PHP_EOL);

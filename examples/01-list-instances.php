<?php

declare(strict_types=1);

/*
 * Lists every instance on the account and prints a short summary.
 *
 * Run with:
 *     VULTR_API_KEY=your-key php examples/01-list-instances.php
 */

use BoudhraaDhia7\Vultr\Util\Bytes;
use BoudhraaDhia7\Vultr\VultrClient;

require __DIR__.'/../vendor/autoload.php';

$vultr = VultrClient::fromEnvironment();

$account = $vultr->account()->get();

printf(
    'Account: %s (%s)%sRemaining credit: %.2f USD%s%s',
    $account['name'] ?? 'unknown',
    $account['email'] ?? 'unknown',
    PHP_EOL,
    $vultr->account()->remainingCredit(),
    PHP_EOL,
    PHP_EOL,
);

// all() pages through the API lazily, so this works the same with 5 or 5000
// instances and only fetches a page at a time.
$count = 0;

foreach ($vultr->instances()->all() as $instance) {
    ++$count;

    printf(
        '%-38s %-18s %-14s %-10s %s%s',
        $instance['id'],
        $instance['label'] ?: '(no label)',
        $instance['main_ip'],
        $instance['region'],
        $instance['status'],
        PHP_EOL,
    );
}

printf('%s%d instance(s).%s', PHP_EOL, $count, PHP_EOL);

// Bandwidth figures come back as raw byte counts.
if ($count > 0) {
    $first = $vultr->instances()->list(perPage: 1)->first();

    if (null !== $first) {
        $bandwidth = $vultr->instances()->bandwidth((string) $first['id']);
        $today = reset($bandwidth);

        if (is_array($today)) {
            printf(
                'Latest day for %s: %s in, %s out%s',
                $first['label'] ?: $first['id'],
                Bytes::humanize((int) ($today['incoming_bytes'] ?? 0)),
                Bytes::humanize((int) ($today['outgoing_bytes'] ?? 0)),
                PHP_EOL,
            );
        }
    }
}

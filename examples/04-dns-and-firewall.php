<?php

declare(strict_types=1);

/*
 * Sets up a DNS zone and a firewall group, then attaches the group to an
 * instance. Demonstrates the enum-typed parameters.
 *
 * Run with:
 *     VULTR_API_KEY=your-key php examples/04-dns-and-firewall.php example.test 192.0.2.10
 */

use BoudhraaDhia7\Vultr\Enum\DnsRecordType;
use BoudhraaDhia7\Vultr\VultrClient;

require __DIR__.'/../vendor/autoload.php';

$domain = $argv[1] ?? null;
$ip = $argv[2] ?? null;

if (null === $domain || null === $ip) {
    fwrite(STDERR, 'Usage: php examples/04-dns-and-firewall.php <domain> <ip>'.PHP_EOL);

    exit(1);
}

$vultr = VultrClient::fromEnvironment();

// Creating the zone with an IP seeds the default A and MX records.
$zone = $vultr->domains()->create($domain, $ip);
printf('Created zone %s%s', $zone['domain'], PHP_EOL);

$vultr->domains()->createRecord($domain, 'www', DnsRecordType::A, $ip, ttl: 300);
$vultr->domains()->createRecord($domain, '', DnsRecordType::TXT, 'v=spf1 -all', ttl: 3600);
$vultr->domains()->createRecord($domain, '', DnsRecordType::MX, 'mail.'.$domain, priority: 10);

foreach ($vultr->domains()->allRecords($domain) as $record) {
    printf(
        '  %-6s %-20s %s%s',
        $record['type'],
        '' === $record['name'] ? '@' : $record['name'],
        $record['data'],
        PHP_EOL,
    );
}

// A firewall group starts empty and denies nothing until rules are added.
$group = $vultr->firewalls()->createGroup('web servers');
$groupId = (string) $group['id'];

$vultr->firewalls()->createRule($groupId, 'v4', 'tcp', '0.0.0.0', 0, '80', notes: 'http');
$vultr->firewalls()->createRule($groupId, 'v4', 'tcp', '0.0.0.0', 0, '443', notes: 'https');
$vultr->firewalls()->createRule($groupId, 'v4', 'tcp', '198.51.100.0', 24, '22', notes: 'ssh from the office');

printf('%sFirewall group %s:%s', PHP_EOL, $groupId, PHP_EOL);

foreach ($vultr->firewalls()->allRules($groupId) as $rule) {
    printf(
        '  %-5s %-5s %-18s %s%s',
        $rule['protocol'],
        $rule['port'],
        $rule['subnet'].'/'.$rule['subnet_size'],
        $rule['notes'],
        PHP_EOL,
    );
}

// Attach it to every instance tagged "web".
foreach ($vultr->instances()->all(['tag' => 'web']) as $instance) {
    $vultr->instances()->setFirewallGroup((string) $instance['id'], $groupId);
    printf('Attached the group to %s%s', $instance['label'], PHP_EOL);
}

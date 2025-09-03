# Vultr API v2 PHP Client (Laravel & Symfony Ready)

A modern PHP client for the [Vultr API v2](https://www.vultr.com/api/), optimized for **Laravel** and **Symfony**.  
Works with any PHP 8.1+ project.

## Features

- Instance (server) management
- Regions, Plans, and Operating Systems
- Applications & Marketplace
- DNS, Block Storage, Backups, Snapshots, Object Storage, SSH Keys, and more

---

## Installation

Install via Composer:

```bash
composer require boudhraadhia7/vultr-php-sdk
```

---

## Usage

```php
<?php

use BoudhraaDhia7\VultrLaravelSymfony\VultrAPI;

// Laravel: put these in config/services.php
// 'vultr' => [
//     'key' => env('VULTR_API_KEY'),
//     'url' => env('VULTR_API_URL', 'https://api.vultr.com/'),
// ]

// Production (TLS verify ON by default — recommended)
$vultr = new VultrAPI(config('services.vultr.key'), config('services.vultr.url'));

// Localhost/dev with broken CA store (TLS verify OFF):
// ⚠️ Only for local testing. Never disable in production.
$devClient = new VultrAPI(config('services.vultr.key'), config('services.vultr.url'), false);

// List instances (auto adds Bearer & JSON headers; v2 paths)
$instances = $vultr->doCall('v2/instances', 'GET');

// List regions/plans
$regions = $vultr->doCall('v2/regions', 'GET');
$plans   = $vultr->doCall('v2/plans', 'GET');

// Create a new server (example flow; adjust to your helpers)
$vultr->serverCreateDC('ewr');             // New Jersey (example)
$vultr->serverCreatePlan('vc2-1c-1gb');    // 1 vCPU, 1GB RAM (example)
$vultr->serverCreateType('OS', '1743');    // Ubuntu 22.04 (example)
$vultr->serverCreateLabel('My Laravel App');
$createResponse = $vultr->serverCreate(); // returns details/subid

// Manage an existing instance
$vultr->setSubId('YOUR_INSTANCE_ID');
$reboot  = $vultr->doCall("v2/instances/{$vultr->getSubId()}/reboot", 'POST');
$halt    = $vultr->doCall("v2/instances/{$vultr->getSubId()}/halt", 'POST');
$destroy = $vultr->doCall("v2/instances/{$vultr->getSubId()}", 'DELETE');

// Error handling
// On failures, doCall() returns an array with:
// - http_code
// - curl_errno / curl_error (transport issues => http_code = 0) // 'curl_errno' and 'curl_error' are PHP cURL terms
// - response_json / response_raw (Vultr often returns {"error": "..."} on 4xx)

### Laravel config snippet (for completeness) <!-- 'Laravel' is a PHP framework name -->

```php
// config/services.php
return [
    // ...
    'vultr' => [
        'key' => env('VULTR_API_KEY'),
        'url' => env('VULTR_API_URL', 'https://api.vultr.com/'),
    ],
];



## Contributing

Pull requests are welcome!  
Please submit improvements or fixes via PRs.

---

## License

Licensed under the [MIT License](LICENSE).

---

## Credits

- Original work by corbpie ([GitHub](https://github.com/cp6/Vultr-API-PHP-class)) <!-- 'corbpie' is a GitHub username -->
- Forked and optimized for Laravel and Symfony by Boudhraa Dhia ([GitHub](https://github.com/boudhraadhia7)) <!-- 'Laravel', 'Symfony', 'Boudhraa', and 'Dhia' are proper nouns -->


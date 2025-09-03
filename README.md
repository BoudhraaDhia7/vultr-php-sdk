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
use BoudhraaDhia7\vultr-php-sdk\VultrAPI;

// Get your API key from environment or config
$apiKey = $_ENV['VULTR_API_KEY'] ?? getenv('VULTR_API_KEY');

// Initialize the client
$vultr = new VultrAPI($apiKey);

// List all instances (servers)
echo $vultr->doCall('v2/instances', 'GET', false, $vultr->apiKeyHeader());

// List regions
echo $vultr->doCall('v2/regions', 'GET', false, $vultr->apiKeyHeader());

// List plans
echo $vultr->doCall('v2/plans', 'GET', false, $vultr->apiKeyHeader());

// Create a new server
$vultr->serverCreateDC('ewr'); // New Jersey, USA
$vultr->serverCreatePlan('vc2-1c-1gb'); // 1 CPU, 1GB RAM
$vultr->serverCreateType('OS', '1743'); // Ubuntu 22.04
$vultr->serverCreateLabel('My Laravel App Server');
echo $vultr->serverCreate(); // Deploy instance

// Manage an existing instance
$vultr->setSubId('YOUR_INSTANCE_ID');
$vultr->instanceReboot();    // Reboot
$vultr->serverStop();        // Stop
$vultr->instanceDestroy();   // Destroy
```

---

## Contributing

Pull requests are welcome!  
Please submit improvements or fixes via PRs.

---

## License

Licensed under the [MIT License](LICENSE).

---

## Credits

- Original work by corbpie ([GitHub](https://github.com/cp6/Vultr-API-PHP-class))
- Forked and optimized for Laravel and Symfony by Boudhraa Dhia ([GitHub](https://github.com/boudhraadhia7))


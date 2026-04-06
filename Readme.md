# ER Import Module

PrestaShop module for importing and synchronising remote catalog data, including Educators Resource feeds.

## Included module

- Module name: `csi_remotecatalog`
- Composer package: `catalogsolutions/csi_remotecatalog`

## What it does

- Downloads remote feed files over FTP
- Builds a translated PrestaShop import CSV
- Runs full product imports
- Updates pricing, quantity, and discontinued products on schedule

## PrestaShop 9 / PHP 8.x notes

This repository includes compatibility-oriented fixes for newer environments, including:

- safer admin trigger controller flow for PrestaShop 9 session handling
- PHP 8.4-compatible CSV iterator configuration
- import progress logging through `PrestaShopLogger`

## Cron usage

Use the module's cron trigger controller rather than calling the full manual import endpoint every hour.

Example wrapper:

```php
<?php

$url = 'https://your-store.example.com/admin-path/?controller=AdminTriggerRemoteCatalogCron&cron=Ds6qF896jyLV0&token=YOUR_TOKEN_HERE';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 1800,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$error = curl_error($ch);

curl_close($ch);

file_put_contents(
    __DIR__ . '/csi_import.log',
    '[' . date('Y-m-d H:i:s') . '] ' . ($error ? 'ERROR: ' . $error : $response) . PHP_EOL,
    FILE_APPEND
);
```

Suggested cron schedule:

```cron
0 * * * * /path/to/php /path/to/csi_import.php
```

The module decides internally whether the current run should process hourly, daily, weekly, or monthly work.

## Monitoring

Progress can be monitored from PrestaShop logs by filtering for:

```text
CSI RemoteCatalog:
```

Typical shell check:

```bash
grep -r "CSI RemoteCatalog:" /path/to/prestashop/var/logs/ 2>/dev/null | tail -100
```

## Repository contents

This repository stores the module source directly at repository root so it can be copied into a PrestaShop `modules/csi_remotecatalog` directory.

## Original project notes

The legacy project changelog is preserved in `Readme.md`.

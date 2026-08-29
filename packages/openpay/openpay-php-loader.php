<?php

/**
 * Loader for the Openpay PHP SDK (https://github.com/open-pay/openpay-php).
 *
 * The official SDK ships with two bugs that make it unusable as-is:
 *
 *   1. Its `Openpay.php` wrapper uses `require` (not `require_once`) in
 *      a chain that has a loading-order bug — several child classes
 *      extend a base class that's loaded AFTER them, so the very first
 *      require() that hits a child class dies with "Class ... not found".
 *
 *   2. The classes are in two namespaces (Openpay\Data and
 *      Openpay\Resources) but the parent class references inside child
 *      files (e.g. `extends OpenpayApiResourceBase`) omit the namespace,
 *      so even a working autoloader wouldn't help — the child classes
 *      reference the *unqualified* base class, which only resolves if
 *      the base is loaded into the same namespace first.
 *
 * Fix: require the patched Openpay.php directly. The patch (applied
 * to packages/openpay/openpay-php/Openpay.php) is just changing
 * `require` to `require_once` and reordering the requires so each
 * base class is loaded before its children. This single line is
 * enough to make the SDK loadable from a vanilla autoloader.
 *
 * Usage:
 *   require_once base_path('packages/openpay/openpay-php-loader.php');
 *   Openpay::setId('...');
 *   Openpay::setApiKey('...');
 *   Openpay::setSandboxMode(true);
 *   $openpay = Openpay::getInstance();
 */

require_once __DIR__ . '/openpay-php/Openpay.php';

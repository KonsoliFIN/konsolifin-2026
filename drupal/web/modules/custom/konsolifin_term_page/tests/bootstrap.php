<?php

/**
 * @file
 * Bootstrap for konsolifin_term_page PHPUnit tests.
 */

declare(strict_types=1);

$loader = require dirname(__DIR__, 5) . '/vendor/autoload.php';

// Register the module's PSR-4 namespaces.
$module_dir = dirname(__DIR__);
$loader->addPsr4('Drupal\\konsolifin_term_page\\', $module_dir . '/src');
$loader->addPsr4('Drupal\\Tests\\konsolifin_term_page\\', $module_dir . '/tests/src');

// Register date_ish module namespace (needed for DateIshHelper).
$date_ish_dir = dirname($module_dir) . '/date_ish';
$loader->addPsr4('Drupal\\date_ish\\', $date_ish_dir . '/src');

// Register Drupal core namespaces needed for tests.
$drupal_root = dirname(__DIR__, 4);
$loader->addPsr4('Drupal\\Core\\', $drupal_root . '/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $drupal_root . '/core/lib/Drupal/Component');

// Register taxonomy module namespace (for TermInterface).
$loader->addPsr4('Drupal\\taxonomy\\', $drupal_root . '/core/modules/taxonomy/src');

// Register node module namespace (for NodeInterface).
$loader->addPsr4('Drupal\\node\\', $drupal_root . '/core/modules/node/src');

// Register user module namespace (for EntityOwnerInterface, needed by NodeInterface).
$loader->addPsr4('Drupal\\user\\', $drupal_root . '/core/modules/user/src');

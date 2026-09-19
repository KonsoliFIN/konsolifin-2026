<?php

/**
 * @file
 * Bootstrap for konsolifin_workflows PHPUnit tests.
 */

declare(strict_types=1);

$loader = require dirname(__DIR__, 5) . '/vendor/autoload.php';

// Register the module's PSR-4 namespaces.
$module_dir = dirname(__DIR__);
$loader->addPsr4('Drupal\\konsolifin_workflows\\', $module_dir . '/src');
$loader->addPsr4('Drupal\\Tests\\konsolifin_workflows\\', $module_dir . '/tests/src');

// Register Drupal core namespaces needed for tests.
$drupal_root = dirname(__DIR__, 4);
$loader->addPsr4('Drupal\\Core\\', $drupal_root . '/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $drupal_root . '/core/lib/Drupal/Component');

// Register node and user module namespaces.
$loader->addPsr4('Drupal\\node\\', $drupal_root . '/core/modules/node/src');
$loader->addPsr4('Drupal\\user\\', $drupal_root . '/core/modules/user/src');

// Register workflow module namespace.
$loader->addPsr4('Drupal\\workflow\\', $drupal_root . '/modules/contrib/workflow/src');

<?php

/**
 * @file
 * Bootstrap for konsolifin_review_score PHPUnit tests.
 */

declare(strict_types=1);

$loader = require dirname(__DIR__, 5) . '/vendor/autoload.php';

// Register the module's PSR-4 namespaces.
$module_dir = dirname(__DIR__);
$loader->addPsr4('Drupal\\konsolifin_review_score\\', $module_dir . '/src');
$loader->addPsr4('Drupal\\Tests\\konsolifin_review_score\\', $module_dir . '/tests/src');

// Register Drupal core namespaces needed for tests.
$drupal_root = dirname(__DIR__, 4);
$loader->addPsr4('Drupal\\Core\\', $drupal_root . '/core/lib/Drupal/Core');
$loader->addPsr4('Drupal\\Component\\', $drupal_root . '/core/lib/Drupal/Component');

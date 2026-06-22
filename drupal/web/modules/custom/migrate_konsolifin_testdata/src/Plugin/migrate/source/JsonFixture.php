<?php

declare(strict_types=1);

namespace Drupal\migrate_konsolifin_testdata\Plugin\migrate\source;

use Drupal\migrate\Attribute\MigrateSource;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;

/**
 * Source plugin for reading JSON fixture files.
 *
 * Reads a JSON array from a file in the module's data/ directory and yields
 * each record as a migration source row.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: json_fixture
 *   file: taxonomy/alustat.json
 *   ids:
 *     id:
 *       type: integer
 * @endcode
 */
#[MigrateSource('json_fixture')]
class JsonFixture extends SourcePluginBase {

  /**
   * The parsed JSON data array.
   *
   * @var array
   */
  protected array $data = [];

  /**
   * The resolved absolute path to the JSON fixture file.
   *
   * @var string
   */
  protected string $filePath;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    if (empty($configuration['file'])) {
      throw new MigrateException('The "file" configuration key is required for the json_fixture source plugin.');
    }

    $module_path = \Drupal::service('extension.list.module')->getPath('migrate_konsolifin_testdata');
    $this->filePath = $module_path . '/data/' . $configuration['file'];

    if (!file_exists($this->filePath)) {
      throw new MigrateException(sprintf('The fixture file "%s" does not exist.', $this->filePath));
    }

    $json = file_get_contents($this->filePath);
    $this->data = json_decode($json, TRUE);

    if (!is_array($this->data)) {
      throw new MigrateException(sprintf('The fixture file "%s" does not contain a valid JSON array.', $this->filePath));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return (string) $this->configuration['file'];
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \ArrayIterator {
    return new \ArrayIterator($this->data);
  }

  /**
   * {@inheritdoc}
   */
  public function fields(): array {
    $fields = [];

    if (!empty($this->data)) {
      $first_record = reset($this->data);
      foreach (array_keys($first_record) as $key) {
        $fields[$key] = (string) $key;
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds(): array {
    return $this->configuration['ids'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  protected function doCount(): int {
    return count($this->data);
  }

}

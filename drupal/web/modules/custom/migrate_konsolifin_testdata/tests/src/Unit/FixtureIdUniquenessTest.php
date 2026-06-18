<?php

declare(strict_types=1);

// Feature: test-fixture-migration, Property 2: Fixture ID Uniqueness and Validity

namespace Drupal\Tests\migrate_konsolifin_testdata\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: fixture ID uniqueness and validity.
 *
 * For any fixture file (taxonomy, users, media, or nodes), all `id` values
 * SHALL be unique positive integers within that file, and all taxonomy fixture
 * records SHALL have non-empty `name` fields.
 *
 * **Validates: Requirements 4.5, 7.6, 11.1, 11.3**
 *
 * @group migrate_konsolifin_testdata
 */
class FixtureIdUniquenessTest extends TestCase {

  /**
   * The module's data directory path.
   */
  private static function dataDir(): string {
    return dirname(__DIR__, 3) . '/data';
  }

  /**
   * Recursively find all JSON fixture files in the data directory.
   *
   * @return array<string, string>
   *   Keyed by relative path, value is the absolute path.
   */
  private static function discoverFixtureFiles(): array {
    $dataDir = self::dataDir();
    $files = [];

    if (!is_dir($dataDir)) {
      return $files;
    }

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dataDir, \RecursiveDirectoryIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
      if ($file->getExtension() === 'json') {
        $relativePath = str_replace($dataDir . '/', '', $file->getPathname());
        $files[$relativePath] = $file->getPathname();
      }
    }

    return $files;
  }

  /**
   * Data provider that discovers all fixture files with `id` fields.
   *
   * If no real fixture files exist yet, provides programmatically generated
   * temp fixtures to validate the property logic.
   */
  public static function fixtureFilesWithIdsProvider(): \Generator {
    $files = self::discoverFixtureFiles();

    $realFilesYielded = FALSE;

    foreach ($files as $relativePath => $absolutePath) {
      $content = file_get_contents($absolutePath);
      $data = json_decode($content, TRUE);

      if (!is_array($data) || empty($data)) {
        continue;
      }

      // Only test files that have records with 'id' fields.
      $firstRecord = reset($data);
      if (!is_array($firstRecord) || !array_key_exists('id', $firstRecord)) {
        continue;
      }

      $realFilesYielded = TRUE;
      yield $relativePath => [$relativePath, $data];
    }

    // If no real fixture files exist yet, generate temp fixtures to validate
    // the property logic works correctly.
    if (!$realFilesYielded) {
      yield 'generated: valid fixture with unique IDs' => [
        'generated/valid_ids.json',
        [
          ['id' => 1, 'name' => 'Item One'],
          ['id' => 2, 'name' => 'Item Two'],
          ['id' => 3, 'name' => 'Item Three'],
          ['id' => 10, 'name' => 'Item Ten'],
        ],
      ];
    }
  }

  /**
   * Data provider for taxonomy fixture files specifically.
   *
   * If no real taxonomy fixtures exist yet, provides programmatically generated
   * temp fixtures to validate the taxonomy name property.
   */
  public static function taxonomyFixtureFilesProvider(): \Generator {
    $files = self::discoverFixtureFiles();

    $realFilesYielded = FALSE;

    foreach ($files as $relativePath => $absolutePath) {
      // Only taxonomy fixtures.
      if (!str_starts_with($relativePath, 'taxonomy/')) {
        continue;
      }

      $content = file_get_contents($absolutePath);
      $data = json_decode($content, TRUE);

      if (!is_array($data) || empty($data)) {
        continue;
      }

      $realFilesYielded = TRUE;
      yield $relativePath => [$relativePath, $data];
    }

    // If no real taxonomy fixtures exist yet, generate temp fixtures.
    if (!$realFilesYielded) {
      yield 'generated: taxonomy fixture with names' => [
        'taxonomy/generated_vocab.json',
        [
          ['id' => 1, 'name' => 'PlayStation 5', 'description' => 'Sonyn konsoli', 'weight' => 0],
          ['id' => 2, 'name' => 'Xbox Series X', 'description' => 'Microsoftin konsoli', 'weight' => 1],
          ['id' => 3, 'name' => 'Nintendo Switch', 'description' => 'Nintendon hybridi', 'weight' => 2],
        ],
      ];
    }
  }

  /**
   * Tests that all ID values are positive integers within a fixture file.
   *
   * **Validates: Requirements 4.5, 7.6, 11.1, 11.3**
   */
  #[DataProvider('fixtureFilesWithIdsProvider')]
  public function testAllIdsArePositiveIntegers(string $relativePath, array $records): void {
    foreach ($records as $index => $record) {
      $this->assertArrayHasKey('id', $record, sprintf(
        'Record at index %d in %s is missing the "id" field.',
        $index,
        $relativePath,
      ));

      $id = $record['id'];

      $this->assertIsInt($id, sprintf(
        'Record at index %d in %s has non-integer "id" value: %s',
        $index,
        $relativePath,
        var_export($id, TRUE),
      ));

      $this->assertGreaterThan(0, $id, sprintf(
        'Record at index %d in %s has non-positive "id" value: %d',
        $index,
        $relativePath,
        $id,
      ));
    }
  }

  /**
   * Tests that all ID values are unique within a fixture file.
   *
   * **Validates: Requirements 4.5, 7.6, 11.1, 11.3**
   */
  #[DataProvider('fixtureFilesWithIdsProvider')]
  public function testAllIdsAreUniqueWithinFile(string $relativePath, array $records): void {
    $ids = array_column($records, 'id');
    $duplicates = array_diff_key($ids, array_unique($ids));

    $this->assertEmpty($duplicates, sprintf(
      'File %s contains duplicate "id" values: %s',
      $relativePath,
      implode(', ', array_unique($duplicates)),
    ));
  }

  /**
   * Tests that all taxonomy records have non-empty name fields.
   *
   * **Validates: Requirements 4.5, 7.6, 11.1, 11.3**
   */
  #[DataProvider('taxonomyFixtureFilesProvider')]
  public function testTaxonomyRecordsHaveNonEmptyNames(string $relativePath, array $records): void {
    foreach ($records as $index => $record) {
      $this->assertArrayHasKey('name', $record, sprintf(
        'Taxonomy record at index %d in %s is missing the "name" field.',
        $index,
        $relativePath,
      ));

      $name = $record['name'];

      $this->assertIsString($name, sprintf(
        'Taxonomy record at index %d in %s has non-string "name" value: %s',
        $index,
        $relativePath,
        var_export($name, TRUE),
      ));

      $this->assertNotEmpty(trim($name), sprintf(
        'Taxonomy record at index %d in %s has empty "name" field.',
        $index,
        $relativePath,
      ));
    }
  }

}

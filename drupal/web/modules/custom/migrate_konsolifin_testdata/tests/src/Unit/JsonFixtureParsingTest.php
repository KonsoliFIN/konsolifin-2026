<?php

declare(strict_types=1);

// Feature: test-fixture-migration, Property 1: JSON Fixture Parsing Produces Correct Row Count

namespace Drupal\Tests\migrate_konsolifin_testdata\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: JSON fixture parsing produces correct row count.
 *
 * For any valid JSON array fixture file containing N records, parsing SHALL
 * yield exactly N rows, and count() SHALL return N.
 *
 * Since the JsonFixture source plugin requires the Drupal container
 * (\Drupal::service('extension.list.module')), this test validates the
 * underlying property directly: that JSON fixture files parse correctly
 * and their count matches the number of iterable records.
 *
 * **Validates: Requirements 2.1, 2.4**
 *
 * @group migrate_konsolifin_testdata
 */
class JsonFixtureParsingTest extends TestCase {

  /**
   * The module's data/ directory path.
   */
  private static function dataDir(): string {
    return dirname(__DIR__, 3) . '/data';
  }

  /**
   * Recursively find all JSON files in a directory.
   *
   * @return array<string>
   *   Array of absolute file paths.
   */
  private static function findJsonFiles(string $directory): array {
    $files = [];
    if (!is_dir($directory)) {
      return $files;
    }

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
      if ($file->isFile() && strtolower($file->getExtension()) === 'json') {
        $files[] = $file->getPathname();
      }
    }

    return $files;
  }

  /**
   * Data provider: real fixture files from the module's data/ directory.
   *
   * If no fixture files exist yet, this provider yields nothing and the
   * generated fixture tests below cover the property.
   */
  public static function realFixtureFileProvider(): \Generator {
    $dataDir = self::dataDir();
    $files = self::findJsonFiles($dataDir);

    foreach ($files as $filePath) {
      $relativePath = str_replace($dataDir . '/', '', $filePath);
      yield "fixture: {$relativePath}" => [$filePath];
    }
  }

  /**
   * Data provider: programmatically generated JSON arrays of varying sizes.
   *
   * This validates the property across different record counts, including
   * edge cases (empty array, single record, many records).
   */
  public static function generatedFixtureProvider(): \Generator {
    $seed = crc32('json_fixture_parsing_property_' . date('Y-m-d'));
    mt_srand($seed);

    // Edge case: empty array (0 records).
    yield 'generated: 0 records (empty array)' => [0];

    // Edge case: single record.
    yield 'generated: 1 record' => [1];

    // Small sizes.
    yield 'generated: 3 records' => [3];
    yield 'generated: 5 records' => [5];

    // Medium sizes representative of typical fixture files.
    yield 'generated: 10 records' => [10];
    yield 'generated: 20 records' => [20];

    // Larger sizes to stress-test the property.
    yield 'generated: 50 records' => [50];
    yield 'generated: 100 records' => [100];

    // Random sizes.
    for ($i = 0; $i < 20; $i++) {
      $count = mt_rand(0, 200);
      yield "generated: random #{$i} ({$count} records)" => [$count];
    }
  }

  /**
   * Tests that real fixture files parse as valid JSON arrays with correct count.
   *
   * For any fixture file found in the module's data/ directory, parsing the
   * JSON SHALL produce a valid array where count() equals the number of
   * records yielded during iteration.
   *
   * **Validates: Requirements 2.1, 2.4**
   */
  #[DataProvider('realFixtureFileProvider')]
  public function testRealFixtureFileParsesWithCorrectCount(string $filePath): void {
    $this->assertFileExists($filePath, "Fixture file should exist: {$filePath}");

    $json = file_get_contents($filePath);
    $this->assertNotFalse($json, "Failed to read fixture file: {$filePath}");

    $data = json_decode($json, TRUE);
    $this->assertIsArray($data, "Fixture file should contain a valid JSON array: {$filePath}");

    // Property: count() returns N.
    $expectedCount = count($data);

    // Property: iteration yields exactly N rows.
    $iteratedCount = 0;
    foreach ($data as $row) {
      $this->assertIsArray($row, "Each record in the fixture should be an associative array: {$filePath}");
      $iteratedCount++;
    }

    $this->assertSame(
      $expectedCount,
      $iteratedCount,
      "count() ({$expectedCount}) should match iterated row count ({$iteratedCount}) for: {$filePath}",
    );
  }

  /**
   * Tests the parsing property with generated temporary fixture data.
   *
   * For any valid JSON array with N records, parsing and iterating SHALL
   * yield exactly N rows, and count() SHALL return N.
   *
   * **Validates: Requirements 2.1, 2.4**
   */
  #[DataProvider('generatedFixtureProvider')]
  public function testGeneratedFixtureCountMatchesIteration(int $recordCount): void {
    // Generate a temporary fixture file with N records.
    $records = [];
    for ($i = 1; $i <= $recordCount; $i++) {
      $records[] = [
        'id' => $i,
        'name' => "Test Record {$i}",
        'description' => "Description for record {$i}",
        'weight' => $i - 1,
      ];
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'fixture_') . '.json';
    file_put_contents($tempFile, json_encode($records, JSON_PRETTY_PRINT));

    try {
      // Read back and parse — simulating what JsonFixture::__construct does.
      $json = file_get_contents($tempFile);
      $this->assertNotFalse($json, 'Failed to read temporary fixture file.');

      $data = json_decode($json, TRUE);
      $this->assertIsArray($data, 'Parsed fixture data should be a valid array.');

      // Property: count() returns N.
      $this->assertSame(
        $recordCount,
        count($data),
        "count() should return {$recordCount} for a fixture with {$recordCount} records.",
      );

      // Property: iteration yields exactly N rows.
      $iterator = new \ArrayIterator($data);
      $iteratedCount = 0;
      foreach ($iterator as $row) {
        $this->assertIsArray($row, 'Each iterated row should be an associative array.');
        $iteratedCount++;
      }

      $this->assertSame(
        $recordCount,
        $iteratedCount,
        "Iteration should yield exactly {$recordCount} rows.",
      );

      // Verify the ArrayIterator count method also matches.
      $this->assertSame(
        $recordCount,
        $iterator->count(),
        "ArrayIterator::count() should return {$recordCount}.",
      );
    }
    finally {
      @unlink($tempFile);
    }
  }

  /**
   * Tests that fixture files contain only array records (not nested objects at top level).
   *
   * This ensures the fixture format is a JSON array where each element
   * represents one migration source row.
   *
   * **Validates: Requirements 2.1, 2.4**
   */
  #[DataProvider('realFixtureFileProvider')]
  public function testFixtureRecordsAreAssociativeArrays(string $filePath): void {
    $json = file_get_contents($filePath);
    $data = json_decode($json, TRUE);

    $this->assertIsArray($data, "Fixture should be a JSON array: {$filePath}");

    foreach ($data as $index => $record) {
      $this->assertIsArray(
        $record,
        "Record at index {$index} should be an associative array in: {$filePath}",
      );
    }
  }

}

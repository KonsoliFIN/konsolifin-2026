<?php

declare(strict_types=1);

// Feature: test-fixture-migration, Property 5: Timestamp Format Validity

namespace Drupal\Tests\migrate_konsolifin_testdata\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: timestamp format validity.
 *
 * For any fixture record containing a timestamp field (e.g., `created`), the
 * value SHALL be a valid ISO 8601 datetime string.
 *
 * **Validates: Requirements 11.5**
 *
 * @group migrate_konsolifin_testdata
 */
class TimestampFormatValidityTest extends TestCase {

  /**
   * Expected timestamp format: YYYY-MM-DDTHH:MM:SS.
   */
  private const TIMESTAMP_FORMAT = 'Y-m-d\TH:i:s';

  /**
   * Regex pattern for ISO 8601 datetime without timezone.
   */
  private const TIMESTAMP_REGEX = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/';

  /**
   * Fields that are expected to contain timestamps.
   */
  private const TIMESTAMP_FIELDS = ['created'];

  /**
   * The module's data directory path.
   */
  private static function dataDir(): string {
    return dirname(__DIR__, 3) . '/data';
  }

  /**
   * Recursively discover all JSON fixture files in the data directory.
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
   * Data provider: yields each fixture file that contains timestamp fields.
   */
  public static function fixtureFilesWithTimestampsProvider(): \Generator {
    $files = self::discoverFixtureFiles();

    $realFilesYielded = FALSE;

    foreach ($files as $relativePath => $absolutePath) {
      $content = file_get_contents($absolutePath);
      $data = json_decode($content, TRUE);

      if (!is_array($data) || empty($data)) {
        continue;
      }

      // Check if any record in this file has timestamp fields.
      $hasTimestampFields = FALSE;
      foreach ($data as $record) {
        if (!is_array($record)) {
          continue;
        }
        foreach (self::TIMESTAMP_FIELDS as $field) {
          if (array_key_exists($field, $record)) {
            $hasTimestampFields = TRUE;
            break 2;
          }
        }
      }

      if ($hasTimestampFields) {
        $realFilesYielded = TRUE;
        yield $relativePath => [$relativePath, $data];
      }
    }

    // If no real fixture files with timestamps exist yet, yield nothing.
    // The test should only run when there are actual fixtures to validate.
    if (!$realFilesYielded) {
      self::fail('No fixture files with timestamp fields (created) found in data/ directory.');
    }
  }

  /**
   * Tests that all timestamp fields are valid ISO 8601 datetime strings.
   *
   * Validates:
   * - The value matches the regex pattern YYYY-MM-DDTHH:MM:SS
   * - DateTime::createFromFormat can parse the value
   * - strtotime() can parse the value (used by migration process)
   *
   * **Validates: Requirements 11.5**
   */
  #[DataProvider('fixtureFilesWithTimestampsProvider')]
  public function testTimestampFieldsAreValidIso8601(string $relativePath, array $records): void {
    $errors = [];

    foreach ($records as $index => $record) {
      if (!is_array($record)) {
        continue;
      }

      $recordId = $record['id'] ?? "index:$index";

      foreach (self::TIMESTAMP_FIELDS as $field) {
        if (!array_key_exists($field, $record)) {
          continue;
        }

        $value = $record[$field];

        // Skip null or empty values (field is optional).
        if ($value === NULL || $value === '') {
          continue;
        }

        // Check it's a string.
        if (!is_string($value)) {
          $errors[] = sprintf(
            'Record %s: field "%s" is not a string (got %s)',
            $recordId,
            $field,
            gettype($value),
          );
          continue;
        }

        // Check regex pattern.
        if (!preg_match(self::TIMESTAMP_REGEX, $value)) {
          $errors[] = sprintf(
            'Record %s: field "%s" value "%s" does not match expected format YYYY-MM-DDTHH:MM:SS',
            $recordId,
            $field,
            $value,
          );
          continue;
        }

        // Check DateTime::createFromFormat can parse it.
        $dateTime = \DateTime::createFromFormat(self::TIMESTAMP_FORMAT, $value);
        if ($dateTime === FALSE) {
          $errors[] = sprintf(
            'Record %s: field "%s" value "%s" cannot be parsed by DateTime::createFromFormat',
            $recordId,
            $field,
            $value,
          );
          continue;
        }

        // Verify the parsed date formats back to the same string
        // (catches invalid dates like 2024-02-30).
        if ($dateTime->format(self::TIMESTAMP_FORMAT) !== $value) {
          $errors[] = sprintf(
            'Record %s: field "%s" value "%s" is not a valid calendar date (parses as "%s")',
            $recordId,
            $field,
            $value,
            $dateTime->format(self::TIMESTAMP_FORMAT),
          );
          continue;
        }

        // Check strtotime() can parse it (migration uses strtotime).
        $timestamp = strtotime($value);
        if ($timestamp === FALSE) {
          $errors[] = sprintf(
            'Record %s: field "%s" value "%s" cannot be parsed by strtotime()',
            $recordId,
            $field,
            $value,
          );
        }
      }
    }

    $this->assertEmpty($errors, sprintf(
      "Timestamp format validity violations in %s:\n- %s",
      $relativePath,
      implode("\n- ", $errors),
    ));
  }

}

<?php

declare(strict_types=1);

// Feature: test-fixture-migration, Property 9: Review Score Field Validity

namespace Drupal\Tests\migrate_konsolifin_testdata\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: review score field validity.
 *
 * For any peliarvostelu fixture record, the `arvosana` field SHALL be an
 * integer value.
 *
 * **Validates: Requirements 8.4**
 *
 * @group migrate_konsolifin_testdata
 */
class ReviewScoreValidityTest extends TestCase {

  /**
   * Path to the peliarvostelu fixture file.
   */
  private static function fixturePath(): string {
    return dirname(__DIR__, 3) . '/data/nodes/peliarvostelu.json';
  }

  /**
   * Data provider that yields each peliarvostelu record.
   */
  public static function peliarvosteluRecordsProvider(): \Generator {
    $fixturePath = self::fixturePath();

    if (!file_exists($fixturePath)) {
      self::fail('Fixture file does not exist: ' . $fixturePath);
    }

    $content = file_get_contents($fixturePath);
    $records = json_decode($content, TRUE, 512, JSON_THROW_ON_ERROR);

    if (!is_array($records) || empty($records)) {
      self::fail('Fixture file is empty or not a valid JSON array: ' . $fixturePath);
    }

    foreach ($records as $index => $record) {
      $label = sprintf('Record id=%s (%s)', $record['id'] ?? $index, $record['title'] ?? 'untitled');
      yield $label => [$record];
    }
  }

  /**
   * Tests that each peliarvostelu record has an arvosana field.
   *
   * **Validates: Requirements 8.4**
   */
  #[DataProvider('peliarvosteluRecordsProvider')]
  public function testArvosanaFieldExists(array $record): void {
    $this->assertArrayHasKey('arvosana', $record, sprintf(
      'Record id=%s is missing the "arvosana" field.',
      $record['id'] ?? 'unknown',
    ));
  }

  /**
   * Tests that each peliarvostelu record's arvosana is an integer.
   *
   * **Validates: Requirements 8.4**
   */
  #[DataProvider('peliarvosteluRecordsProvider')]
  public function testArvosanaFieldIsInteger(array $record): void {
    $this->assertArrayHasKey('arvosana', $record, sprintf(
      'Record id=%s is missing the "arvosana" field.',
      $record['id'] ?? 'unknown',
    ));

    $arvosana = $record['arvosana'];

    $this->assertIsInt($arvosana, sprintf(
      'Record id=%s has arvosana value "%s" of type %s, expected integer.',
      $record['id'] ?? 'unknown',
      (string) $arvosana,
      get_debug_type($arvosana),
    ));
  }

}

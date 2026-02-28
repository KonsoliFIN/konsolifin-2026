<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_review_score\Unit;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\konsolifin_review_score\Plugin\Field\FieldType\ReviewScoreItem;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReviewScoreItem field type.
 *
 * Tests schema(), isEmpty(), and generateSampleValue() methods.
 *
 * @group konsolifin_review_score
 *
 * _Requirements: 2.1, 2.2, 2.4_
 */
class ReviewScoreItemTest extends TestCase {

  /**
   * Tests that schema() returns a single int column, unsigned, size small.
   *
   * **Validates: Requirements 2.1**
   */
  public function testSchemaReturnsSingleIntColumn(): void {
    $fieldStorage = $this->createStub(FieldStorageDefinitionInterface::class);
    $schema = ReviewScoreItem::schema($fieldStorage);

    $this->assertArrayHasKey('columns', $schema);
    $this->assertArrayHasKey('value', $schema['columns']);
    $this->assertCount(1, $schema['columns'], 'Schema should have exactly one column.');

    $column = $schema['columns']['value'];
    $this->assertSame('int', $column['type']);
    $this->assertTrue($column['unsigned']);
    $this->assertSame('small', $column['size']);
  }

  /**
   * Tests that isEmpty() returns true for null value.
   *
   * **Validates: Requirements 2.4**
   */
  public function testIsEmptyReturnsTrueForNull(): void {
    $item = $this->createIsEmptyTestDouble(NULL);
    $this->assertTrue($item->isEmpty());
  }

  /**
   * Tests that isEmpty() returns false for value 0.
   *
   * **Validates: Requirements 2.2**
   */
  public function testIsEmptyReturnsFalseForZero(): void {
    $item = $this->createIsEmptyTestDouble(0);
    $this->assertFalse($item->isEmpty());
  }

  /**
   * Tests that isEmpty() returns false for value 400.
   *
   * **Validates: Requirements 2.2**
   */
  public function testIsEmptyReturnsFalseFor400(): void {
    $item = $this->createIsEmptyTestDouble(400);
    $this->assertFalse($item->isEmpty());
  }

  /**
   * Tests that generateSampleValue() returns a value in 0–400.
   *
   * **Validates: Requirements 2.2**
   */
  public function testGenerateSampleValueReturnsValueInRange(): void {
    $fieldDefinition = $this->createStub(FieldDefinitionInterface::class);

    // Run multiple times to increase confidence.
    for ($i = 0; $i < 50; $i++) {
      $sample = ReviewScoreItem::generateSampleValue($fieldDefinition);

      $this->assertArrayHasKey('value', $sample);
      $this->assertIsInt($sample['value']);
      $this->assertGreaterThanOrEqual(0, $sample['value']);
      $this->assertLessThanOrEqual(400, $sample['value']);
    }
  }

  /**
   * Creates a test double for isEmpty() testing.
   *
   * Since isEmpty() calls $this->get('value')->getValue(), we create
   * a partial mock that stubs the internal data access chain.
   */
  private function createIsEmptyTestDouble(mixed $value): ReviewScoreItem {
    $typedData = $this->createStub(\Drupal\Core\TypedData\TypedDataInterface::class);
    $typedData->method('getValue')->willReturn($value);

    $item = $this->getMockBuilder(ReviewScoreItem::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get'])
      ->getMock();

    $item->method('get')->with('value')->willReturn($typedData);

    return $item;
  }

}

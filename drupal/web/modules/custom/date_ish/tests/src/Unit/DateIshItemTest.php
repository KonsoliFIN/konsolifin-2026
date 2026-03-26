<?php

declare(strict_types=1);

namespace Drupal\Tests\date_ish\Unit;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\date_ish\Plugin\Field\FieldType\DateIshItem;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DateIshItem field type.
 *
 * Tests schema() and isEmpty() methods.
 *
 * @group date_ish
 *
 * _Requirements: 2.1, 2.2, 2.3, 11.1_
 */
class DateIshItemTest extends TestCase {

  /**
   * Tests that schema() returns accuracy_level VARCHAR(16) column.
   *
   * **Validates: Requirements 2.1**
   */
  public function testSchemaReturnsAccuracyLevelColumn(): void {
    $fieldStorage = $this->createStub(FieldStorageDefinitionInterface::class);
    $schema = DateIshItem::schema($fieldStorage);

    $this->assertArrayHasKey('columns', $schema);
    $this->assertArrayHasKey('accuracy_level', $schema['columns']);

    $column = $schema['columns']['accuracy_level'];
    $this->assertSame('varchar', $column['type']);
    $this->assertSame(16, $column['length']);
  }

  /**
   * Tests that schema() returns stored_date DATE column.
   *
   * **Validates: Requirements 2.2**
   */
  public function testSchemaReturnsStoredDateColumn(): void {
    $fieldStorage = $this->createStub(FieldStorageDefinitionInterface::class);
    $schema = DateIshItem::schema($fieldStorage);

    $this->assertArrayHasKey('columns', $schema);
    $this->assertArrayHasKey('stored_date', $schema['columns']);

    $column = $schema['columns']['stored_date'];
    $this->assertSame('varchar', $column['type']);
    $this->assertSame('date', $column['mysql_type']);
  }

  /**
   * Tests that schema() has exactly two columns.
   *
   * **Validates: Requirements 2.3**
   */
  public function testSchemaHasTwoColumns(): void {
    $fieldStorage = $this->createStub(FieldStorageDefinitionInterface::class);
    $schema = DateIshItem::schema($fieldStorage);

    $this->assertCount(2, $schema['columns'], 'Schema should have exactly two columns.');
  }

  /**
   * Tests isEmpty() returns true when both values are null.
   *
   * **Validates: Requirements 11.1**
   */
  public function testIsEmptyReturnsTrueWhenBothNull(): void {
    $item = $this->createIsEmptyTestDouble(NULL, NULL);
    $this->assertTrue($item->isEmpty());
  }

  /**
   * Tests isEmpty() returns true when both values are empty strings.
   *
   * **Validates: Requirements 11.1**
   */
  public function testIsEmptyReturnsTrueWhenBothEmptyStrings(): void {
    $item = $this->createIsEmptyTestDouble('', '');
    $this->assertTrue($item->isEmpty());
  }

  /**
   * Tests isEmpty() returns true when accuracy is null and date is empty.
   *
   * **Validates: Requirements 11.1**
   */
  public function testIsEmptyReturnsTrueWhenNullAndEmpty(): void {
    $item = $this->createIsEmptyTestDouble(NULL, '');
    $this->assertTrue($item->isEmpty());
  }

  /**
   * Tests isEmpty() returns true when accuracy is empty and date is null.
   *
   * **Validates: Requirements 11.1**
   */
  public function testIsEmptyReturnsTrueWhenEmptyAndNull(): void {
    $item = $this->createIsEmptyTestDouble('', NULL);
    $this->assertTrue($item->isEmpty());
  }

  /**
   * Tests isEmpty() returns false when accuracy is set but date is null.
   *
   * **Validates: Requirements 2.2**
   */
  public function testIsEmptyReturnsFalseWhenAccuracySetDateNull(): void {
    $item = $this->createIsEmptyTestDouble('month', NULL);
    $this->assertFalse($item->isEmpty());
  }

  /**
   * Tests isEmpty() returns false when accuracy is null but date is set.
   *
   * **Validates: Requirements 2.2**
   */
  public function testIsEmptyReturnsFalseWhenAccuracyNullDateSet(): void {
    $item = $this->createIsEmptyTestDouble(NULL, '2025-03-31');
    $this->assertFalse($item->isEmpty());
  }

  /**
   * Tests isEmpty() returns false when both values are set.
   *
   * **Validates: Requirements 2.2**
   */
  public function testIsEmptyReturnsFalseWhenBothSet(): void {
    $item = $this->createIsEmptyTestDouble('exact', '2025-06-15');
    $this->assertFalse($item->isEmpty());
  }

  /**
   * Creates a test double for isEmpty() testing.
   *
   * Since isEmpty() calls $this->get('accuracy_level')->getValue() and
   * $this->get('stored_date')->getValue(), we create a partial mock that
   * stubs the internal data access chain for both properties.
   */
  private function createIsEmptyTestDouble(mixed $accuracyLevel, mixed $storedDate): DateIshItem {
    $accuracyTypedData = $this->createStub(\Drupal\Core\TypedData\TypedDataInterface::class);
    $accuracyTypedData->method('getValue')->willReturn($accuracyLevel);

    $dateTypedData = $this->createStub(\Drupal\Core\TypedData\TypedDataInterface::class);
    $dateTypedData->method('getValue')->willReturn($storedDate);

    $item = $this->getMockBuilder(DateIshItem::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['get'])
      ->getMock();

    $item->method('get')->willReturnCallback(function (string $name) use ($accuracyTypedData, $dateTypedData) {
      return match ($name) {
        'accuracy_level' => $accuracyTypedData,
        'stored_date' => $dateTypedData,
      };
    });

    return $item;
  }

}

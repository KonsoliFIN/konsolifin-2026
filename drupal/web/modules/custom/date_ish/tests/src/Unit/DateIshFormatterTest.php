<?php

declare(strict_types=1);

// Feature: date-ish, Property 3: Empty field produces no formatter output

namespace Drupal\Tests\date_ish\Unit;

require_once dirname(__DIR__, 3) . '/src/DateIshHelper.php';
require_once dirname(__DIR__, 3) . '/src/Plugin/Field/FieldFormatter/DateIshFormatter.php';

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\date_ish\Plugin\Field\FieldFormatter\DateIshFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A minimal iterable stub implementing FieldItemListInterface for testing.
 *
 * PHPUnit 12 cannot configure getIterator() on mocked interfaces that extend
 * Traversable without explicitly implementing IteratorAggregate. This concrete
 * class solves that by implementing both interfaces.
 */
class StubFieldItemList implements \IteratorAggregate, FieldItemListInterface {

  public function __construct(private readonly array $items) {}

  public function getIterator(): \ArrayIterator {
    return new \ArrayIterator($this->items);
  }

  // All remaining FieldItemListInterface methods are no-ops for testing.
  public function getEntity() { return NULL; }
  public function setLangcode($langcode) {}
  public function getLangcode() { return 'en'; }
  public function getFieldDefinition() { return NULL; }
  public function getSettings() { return []; }
  public function getSetting($setting_name) { return NULL; }
  public function defaultAccess($operation = 'view', $account = NULL) { return NULL; }
  public function filterEmptyItems() { return $this; }
  public function __get($property_name) { return NULL; }
  public function __set($property_name, $value) {}
  public function __isset($property_name) { return FALSE; }
  public function __unset($property_name) {}
  public function preSave() {}
  public function postSave($update) { return FALSE; }
  public function delete() {}
  public function deleteRevision() {}
  public function view($display_options = []) { return []; }
  public function generateSampleItems($count = 1) {}
  public function defaultValuesForm(array &$form, $form_state) { return []; }
  public function defaultValuesFormValidate(array $element, array &$form, $form_state) {}
  public function defaultValuesFormSubmit(array $element, array &$form, $form_state) { return []; }
  public static function processDefaultValue($default_value, $entity, $definition) { return []; }
  public function equals($list_to_compare) { return FALSE; }
  public function hasAffectingChanges($original_items, $langcode) { return FALSE; }
  public function access($operation = 'view', $account = NULL, $return_as_object = FALSE) { return TRUE; }

  // ListInterface / TypedDataInterface methods.
  public function isEmpty() { return empty($this->items); }
  public function getValue() { return $this->items; }
  public function setValue($value, $notify = TRUE) {}
  public function getString() { return ''; }
  public function getConstraints() { return []; }
  public function validate() { return NULL; }
  public function getDataDefinition() { return NULL; }
  public function getName() { return NULL; }
  public function getParent() { return NULL; }
  public function getRoot() { return NULL; }
  public function getPropertyPath() { return ''; }
  public function setContext($name = NULL, $parent = NULL) {}
  public function applyDefaultValue($notify = TRUE) { return $this; }
  public function first() { return $this->items[0] ?? NULL; }
  public function get($index) { return $this->items[$index] ?? NULL; }
  public function set($index, $value) { return $this; }
  public function removeItem($index) { return $this; }
  public function appendItem($value = NULL) { return NULL; }
  public function count(): int { return count($this->items); }
  public function offsetExists($offset): bool { return isset($this->items[$offset]); }
  public function offsetGet($offset): mixed { return $this->items[$offset] ?? NULL; }
  public function offsetSet($offset, $value): void {}
  public function offsetUnset($offset): void {}
  public function onChange($delta) {}
  public function getItemDefinition() { return NULL; }
  public function last(): ?\Drupal\Core\TypedData\TypedDataInterface { return NULL; }
  public function filter($callback) { return $this; }
  public static function createInstance($definition, $name = NULL, $parent = NULL) { return NULL; }

}

/**
 * Property test: empty field produces no formatter output.
 *
 * For any field item where both accuracy_level and stored_date are null or
 * empty, DateIshFormatter::viewElements() must return an empty render array.
 *
 * **Validates: Requirements 11.2**
 *
 * @group date_ish
 */
class DateIshFormatterTest extends TestCase {

  /**
   * Data provider generating 100 random empty field item states.
   *
   * Each case is an array of items, where each item is an [accuracy, date]
   * pair. All items use empty states: null or empty string for both fields.
   */
  public static function randomEmptyItemsProvider(): \Generator {
    $seed = crc32('empty_field_no_output_property_' . date('Y-m-d'));
    mt_srand($seed);

    $emptyValues = [NULL, ''];

    for ($i = 0; $i < 100; $i++) {
      $itemCount = mt_rand(1, 3);
      $items = [];
      for ($j = 0; $j < $itemCount; $j++) {
        $accuracy = $emptyValues[mt_rand(0, 1)];
        $storedDate = $emptyValues[mt_rand(0, 1)];
        $items[] = [$accuracy, $storedDate];
      }

      $label = "empty #{$i}: " . $itemCount . ' item(s)';
      yield $label => [$items];
    }
  }

  /**
   * Tests that empty items produce no formatter output.
   *
   * **Validates: Requirements 11.2**
   */
  #[DataProvider('randomEmptyItemsProvider')]
  public function testEmptyItemProducesNoOutput(array $itemDefs): void {
    // Build stdClass items with public properties.
    $mockItems = [];
    foreach ($itemDefs as [$accuracy, $storedDate]) {
      $item = new \stdClass();
      $item->accuracy_level = $accuracy;
      $item->stored_date = $storedDate;
      $mockItems[] = $item;
    }

    $fieldItemList = new StubFieldItemList($mockItems);

    // Create formatter without Drupal bootstrap.
    $formatter = $this->createStub(DateIshFormatter::class);

    $result = $formatter->viewElements($fieldItemList, 'en');

    $this->assertSame(
      [],
      $result,
      'Empty field items must produce an empty render array.',
    );
  }

  /**
   * Creates a real DateIshFormatter instance without Drupal bootstrap.
   *
   * Uses ReflectionClass to bypass the FormatterBase constructor which
   * requires Drupal services, while keeping viewElements() as the real
   * implementation.
   */
  private function createRealFormatter(): DateIshFormatter {
    $reflection = new \ReflectionClass(DateIshFormatter::class);
    return $reflection->newInstanceWithoutConstructor();
  }

  /**
   * Creates a StubFieldItemList with a single item.
   */
  private function createSingleItemList(?string $accuracy, ?string $storedDate): StubFieldItemList {
    $item = new \stdClass();
    $item->accuracy_level = $accuracy;
    $item->stored_date = $storedDate;
    return new StubFieldItemList([$item]);
  }

  /**
   * Tests exact accuracy renders full date "15 March 2025".
   *
   * **Validates: Requirements 10.1**
   */
  public function testExactAccuracyRendersFullDate(): void {
    $formatter = $this->createRealFormatter();
    $items = $this->createSingleItemList('exact', '2025-03-15');

    $result = $formatter->viewElements($items, 'en');

    $this->assertSame('15 March 2025', $result[0]['#markup']);
  }

  /**
   * Tests month accuracy renders "March 2025".
   *
   * **Validates: Requirements 10.2**
   */
  public function testMonthAccuracyRendersMonthYear(): void {
    $formatter = $this->createRealFormatter();
    $items = $this->createSingleItemList('month', '2025-03-31');

    $result = $formatter->viewElements($items, 'en');

    $this->assertSame('March 2025', $result[0]['#markup']);
  }

  /**
   * Tests quarter accuracy renders "Q1 2025".
   *
   * **Validates: Requirements 10.3**
   */
  public function testQuarterAccuracyRendersQuarterYear(): void {
    $formatter = $this->createRealFormatter();
    $items = $this->createSingleItemList('quarter', '2025-03-31');

    $result = $formatter->viewElements($items, 'en');

    $this->assertSame('Q1 2025', $result[0]['#markup']);
  }

  /**
   * Tests year_half accuracy renders "H1 2025".
   *
   * **Validates: Requirements 10.4**
   */
  public function testYearHalfAccuracyRendersHalfYear(): void {
    $formatter = $this->createRealFormatter();
    $items = $this->createSingleItemList('year_half', '2025-06-30');

    $result = $formatter->viewElements($items, 'en');

    $this->assertSame('H1 2025', $result[0]['#markup']);
  }

  /**
   * Tests year accuracy renders "2025".
   *
   * **Validates: Requirements 10.5**
   */
  public function testYearAccuracyRendersYear(): void {
    $formatter = $this->createRealFormatter();
    $items = $this->createSingleItemList('year', '2025-12-31');

    $result = $formatter->viewElements($items, 'en');

    $this->assertSame('2025', $result[0]['#markup']);
  }

  /**
   * Tests empty field returns empty render array.
   *
   * **Validates: Requirements 11.2**
   */
  public function testEmptyFieldReturnsEmptyArray(): void {
    $formatter = $this->createRealFormatter();
    $items = new StubFieldItemList([]);

    $result = $formatter->viewElements($items, 'en');

    $this->assertSame([], $result);
  }

}

<?php

declare(strict_types=1);

// Feature: date-ish, Property 4: Missing date inputs fail validation

namespace Drupal\Tests\date_ish\Unit;

use Drupal\Core\Form\FormStateInterface;
use Drupal\date_ish\Plugin\Field\FieldWidget\DateIshWidget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Provide a stub for Drupal's global t() function if not already defined.
if (!function_exists('t')) {
  function t(string $string, array $args = [], array $options = []): string {
    return $string;
  }
}

/**
 * Property test: missing date inputs fail validation.
 *
 * For any accuracy level, when the widget receives a form submission where the
 * accuracy is set but the required date sub-inputs are absent or blank, the
 * widget's validateElement() method must set at least one form error.
 *
 * **Validates: Requirements 12.1**
 *
 * @group date_ish
 */
class DateIshWidgetValidationTest extends TestCase {

  /**
   * The accuracy levels supported by the widget.
   */
  private const ACCURACY_LEVELS = ['exact', 'month', 'quarter', 'year_half', 'year'];

  /**
   * Blank value variants used to simulate missing input.
   */
  private const BLANK_VALUES = ['', NULL];

  /**
   * Builds a mock element array mimicking the widget's formElement() output.
   *
   * All sub-inputs default to blank. The caller sets the accuracy #value.
   * A dummy value is placed in an unrelated wrapper so the "has any input"
   * check passes (simulating a real form where the user picked an accuracy
   * but forgot to fill in the date sub-inputs).
   */
  private static function buildElement(string $accuracy): array {
    $element = [
      'accuracy' => ['#value' => $accuracy],
      'exact_wrapper' => ['date' => ['#value' => '']],
      'month_wrapper' => ['year' => ['#value' => ''], 'month' => ['#value' => '']],
      'quarter_wrapper' => ['year' => ['#value' => ''], 'quarter' => ['#value' => '']],
      'year_half_wrapper' => ['year' => ['#value' => ''], 'half' => ['#value' => '']],
      'year_wrapper' => ['year' => ['#value' => '']],
    ];

    // Place a dummy value in an unrelated wrapper so the validator's
    // "has any input" guard is satisfied. This simulates the user having
    // interacted with the widget (selected an accuracy) without completing
    // the date sub-inputs for that accuracy.
    switch ($accuracy) {
      case 'exact':
        // Put a dummy year in the year_wrapper so hasAnyInput is true,
        // but the exact_wrapper date remains blank.
        $element['year_wrapper']['year']['#value'] = '2025';
        break;

      case 'month':
      case 'quarter':
      case 'year_half':
      case 'year':
        // Put a dummy date in the exact_wrapper.
        $element['exact_wrapper']['date']['#value'] = '2025-01-01';
        break;
    }

    return $element;
  }

  /**
   * Data provider generating 125+ random missing-input test cases.
   *
   * For each accuracy level, generates 25 cases where the accuracy is set but
   * the required sub-inputs are blank (empty string or NULL). Each case
   * randomly picks a blank variant for each required sub-input.
   */
  public static function randomMissingInputProvider(): \Generator {
    $seed = crc32('missing_date_inputs_property_' . date('Y-m-d'));
    mt_srand($seed);

    $blankValues = self::BLANK_VALUES;

    foreach (self::ACCURACY_LEVELS as $accuracy) {
      for ($i = 0; $i < 25; $i++) {
        $element = self::buildElement($accuracy);
        $blankVariant = $blankValues[mt_rand(0, count($blankValues) - 1)];

        // For accuracy levels with multiple sub-inputs, randomly decide
        // which inputs are blank. At least one required input must be blank.
        switch ($accuracy) {
          case 'exact':
            $element['exact_wrapper']['date']['#value'] = $blankVariant;
            break;

          case 'month':
            // Randomly blank one or both of year and month.
            $blankYear = (bool) mt_rand(0, 1);
            $blankMonth = (bool) mt_rand(0, 1);
            // Ensure at least one is blank.
            if (!$blankYear && !$blankMonth) {
              $blankYear = TRUE;
            }
            $element['month_wrapper']['year']['#value'] = $blankYear
              ? $blankValues[mt_rand(0, count($blankValues) - 1)]
              : (string) mt_rand(1900, 2100);
            $element['month_wrapper']['month']['#value'] = $blankMonth
              ? $blankValues[mt_rand(0, count($blankValues) - 1)]
              : (string) mt_rand(1, 12);
            break;

          case 'quarter':
            $blankYear = (bool) mt_rand(0, 1);
            $blankQuarter = (bool) mt_rand(0, 1);
            if (!$blankYear && !$blankQuarter) {
              $blankQuarter = TRUE;
            }
            $element['quarter_wrapper']['year']['#value'] = $blankYear
              ? $blankValues[mt_rand(0, count($blankValues) - 1)]
              : (string) mt_rand(1900, 2100);
            $element['quarter_wrapper']['quarter']['#value'] = $blankQuarter
              ? $blankValues[mt_rand(0, count($blankValues) - 1)]
              : (string) mt_rand(1, 4);
            break;

          case 'year_half':
            $blankYear = (bool) mt_rand(0, 1);
            $blankHalf = (bool) mt_rand(0, 1);
            if (!$blankYear && !$blankHalf) {
              $blankHalf = TRUE;
            }
            $element['year_half_wrapper']['year']['#value'] = $blankYear
              ? $blankValues[mt_rand(0, count($blankValues) - 1)]
              : (string) mt_rand(1900, 2100);
            $element['year_half_wrapper']['half']['#value'] = $blankHalf
              ? $blankValues[mt_rand(0, count($blankValues) - 1)]
              : (string) mt_rand(1, 2);
            break;

          case 'year':
            $element['year_wrapper']['year']['#value'] = $blankVariant;
            break;
        }

        yield "{$accuracy} #{$i}" => [$element];
      }
    }
  }

  /**
   * Tests that missing date inputs produce at least one validation error.
   *
   * **Validates: Requirements 12.1**
   */
  #[DataProvider('randomMissingInputProvider')]
  public function testMissingInputsFailValidation(array $element): void {
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects($this->atLeastOnce())
      ->method('setError');

    $form = [];

    DateIshWidget::validateElement($element, $formState, $form);
  }

  // Feature: date-ish, Property 5: Invalid calendar dates fail validation

  /**
   * Data provider generating 100+ random invalid calendar date test cases.
   *
   * Generates invalid date combinations for accuracy="exact" using seeded
   * mt_rand. Invalid dates include: impossible day-of-month (Feb 30, Apr 31),
   * out-of-range months (0, 13), out-of-range days (0, 32), and non-leap-year
   * Feb 29.
   */
  public static function randomInvalidDateProvider(): \Generator {
    $seed = crc32('invalid_calendar_dates_property_5');
    mt_srand($seed);

    // Strategy 1: Months with 30 days given day=31 (30 cases).
    $thirtyDayMonths = [4, 6, 9, 11];
    for ($i = 0; $i < 30; $i++) {
      $year = mt_rand(1900, 2100);
      $month = $thirtyDayMonths[mt_rand(0, count($thirtyDayMonths) - 1)];
      $day = 31;
      $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $element = self::buildElementWithExactDate($dateStr);
      yield "30-day month day=31 #{$i} ({$dateStr})" => [$element, $dateStr];
    }

    // Strategy 2: February with impossible days (25 cases).
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      $month = 2;
      // Days 30 and 31 are always invalid for February.
      $day = mt_rand(30, 31);
      $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $element = self::buildElementWithExactDate($dateStr);
      yield "feb impossible day #{$i} ({$dateStr})" => [$element, $dateStr];
    }

    // Strategy 3: Non-leap-year February 29 (15 cases).
    // Non-leap years: not divisible by 4, or divisible by 100 but not 400.
    $nonLeapYears = [];
    for ($y = 1901; $y <= 2100; $y++) {
      if (!($y % 4 === 0 && ($y % 100 !== 0 || $y % 400 === 0))) {
        $nonLeapYears[] = $y;
      }
    }
    for ($i = 0; $i < 15; $i++) {
      $year = $nonLeapYears[mt_rand(0, count($nonLeapYears) - 1)];
      $dateStr = sprintf('%04d-02-29', $year);
      $element = self::buildElementWithExactDate($dateStr);
      yield "non-leap feb 29 #{$i} ({$dateStr})" => [$element, $dateStr];
    }

    // Strategy 4: Out-of-range month values (15 cases).
    for ($i = 0; $i < 15; $i++) {
      $year = mt_rand(1900, 2100);
      // Month 0 or 13+.
      $month = (mt_rand(0, 1) === 0) ? 0 : mt_rand(13, 99);
      $day = mt_rand(1, 28);
      $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $element = self::buildElementWithExactDate($dateStr);
      yield "invalid month #{$i} ({$dateStr})" => [$element, $dateStr];
    }

    // Strategy 5: Day=0 or day>31 (15 cases).
    for ($i = 0; $i < 15; $i++) {
      $year = mt_rand(1900, 2100);
      $month = mt_rand(1, 12);
      $day = (mt_rand(0, 1) === 0) ? 0 : mt_rand(32, 99);
      $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $element = self::buildElementWithExactDate($dateStr);
      yield "invalid day #{$i} ({$dateStr})" => [$element, $dateStr];
    }
  }

  /**
   * Builds an element array with accuracy="exact" and a given date string.
   */
  private static function buildElementWithExactDate(string $date): array {
    $element = self::buildElement('exact');
    $element['exact_wrapper']['date']['#value'] = $date;
    return $element;
  }

  /**
   * Tests that invalid calendar dates produce a validation error.
   *
   * **Validates: Requirements 12.2**
   */
  #[DataProvider('randomInvalidDateProvider')]
  public function testInvalidDatesFailValidation(array $element, string $dateStr): void {
    $formState = $this->createMock(FormStateInterface::class);

    $capturedMessage = NULL;
    $formState->expects($this->atLeastOnce())
      ->method('setError')
      ->willReturnCallback(function ($el, $message) use (&$capturedMessage): void {
        $capturedMessage = $message;
      });

    $form = [];

    DateIshWidget::validateElement($element, $formState, $form);

    // The message is a TranslatableMarkup object; extract the untranslated
    // string via getUntranslatedString() or fall back to plain string check.
    $messageText = is_object($capturedMessage) && method_exists($capturedMessage, 'getUntranslatedString')
      ? $capturedMessage->getUntranslatedString()
      : (is_string($capturedMessage) ? $capturedMessage : '');

    $this->assertStringContainsString('not valid', $messageText,
      "Expected 'not valid' error for invalid date {$dateStr}, got: {$messageText}");
  }

}

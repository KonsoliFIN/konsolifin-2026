<?php

declare(strict_types=1);

// Feature: date-ish, Property 1: Stored date is the last day of the applicable range

namespace Drupal\Tests\date_ish\Unit;

require_once dirname(__DIR__, 3) . '/src/DateIshHelper.php';

use Drupal\date_ish\DateIshHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: stored date is the last day of the applicable range.
 *
 * For any accuracy level (exact, month, quarter, year_half, year) and any valid
 * year/period inputs, DateIshHelper::computeStoredDate() must return a string
 * that (a) matches the ISO 8601 YYYY-MM-DD format, (b) is a valid calendar
 * date, and (c) is the last day of the applicable range — or, when accuracy is
 * "exact", equals the input date exactly.
 *
 * **Validates: Requirements 2.2, 2.4, 2.5, 2.6, 2.7, 2.8, 9.1, 9.2**
 *
 * @group date_ish
 */
class DateIshHelperTest extends TestCase {

  /**
   * Quarter end: Q1→Mar 31, Q2→Jun 30, Q3→Sep 30, Q4→Dec 31.
   */
  private const QUARTER_END = [
    1 => ['month' => 3, 'day' => 31],
    2 => ['month' => 6, 'day' => 30],
    3 => ['month' => 9, 'day' => 30],
    4 => ['month' => 12, 'day' => 31],
  ];

  /**
   * Half-year end: H1→Jun 30, H2→Dec 31.
   */
  private const HALF_END = [
    1 => ['month' => 6, 'day' => 30],
    2 => ['month' => 12, 'day' => 31],
  ];

  /**
   * Data provider generating 120+ random inputs across all accuracy levels.
   */
  public static function randomStoredDateProvider(): \Generator {
    $seed = crc32('stored_date_last_day_property_' . date('Y-m-d'));
    mt_srand($seed);

    // --- exact: 25 random dates ---
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      $month = mt_rand(1, 12);
      $lastDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
      $day = mt_rand(1, $lastDay);
      yield "exact #{$i}: {$year}-{$month}-{$day}" => [
        'exact', $year, $month, $day, NULL, NULL,
      ];
    }

    // --- month: 25 random year+month ---
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      $month = mt_rand(1, 12);
      yield "month #{$i}: {$year}-{$month}" => [
        'month', $year, $month, NULL, NULL, NULL,
      ];
    }

    // --- quarter: 25 random year+quarter ---
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      $quarter = mt_rand(1, 4);
      yield "quarter #{$i}: {$year} Q{$quarter}" => [
        'quarter', $year, NULL, NULL, $quarter, NULL,
      ];
    }

    // --- year_half: 25 random year+half ---
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      $half = mt_rand(1, 2);
      yield "year_half #{$i}: {$year} H{$half}" => [
        'year_half', $year, NULL, NULL, NULL, $half,
      ];
    }

    // --- year: 25 random years ---
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      yield "year #{$i}: {$year}" => [
        'year', $year, NULL, NULL, NULL, NULL,
      ];
    }
  }

  /**
   * Tests that computeStoredDate returns the last day of the applicable range.
   *
   * **Validates: Requirements 2.2, 2.4, 2.5, 2.6, 2.7, 2.8, 9.1, 9.2**
   */
  #[DataProvider('randomStoredDateProvider')]
  public function testStoredDateIsLastDayOfRange(
    string $accuracy,
    int $year,
    ?int $month,
    ?int $day,
    ?int $quarter,
    ?int $half,
  ): void {
    $result = DateIshHelper::computeStoredDate($accuracy, $year, $month, $day, $quarter, $half);

    // (a) Matches YYYY-MM-DD format.
    $this->assertMatchesRegularExpression(
      '/^\d{4}-\d{2}-\d{2}$/',
      $result,
      "Result '{$result}' does not match YYYY-MM-DD format.",
    );

    // (b) Is a valid calendar date.
    $parts = explode('-', $result);
    $rYear = (int) $parts[0];
    $rMonth = (int) $parts[1];
    $rDay = (int) $parts[2];
    $this->assertTrue(
      checkdate($rMonth, $rDay, $rYear),
      "Result '{$result}' is not a valid calendar date.",
    );

    // (c) Is the last day of the applicable range (or exact date).
    match ($accuracy) {
      'exact' => $this->assertExactDate($result, $year, $month, $day),
      'month' => $this->assertLastDayOfMonth($result, $year, $month),
      'quarter' => $this->assertLastDayOfQuarter($result, $year, $quarter),
      'year_half' => $this->assertLastDayOfHalf($result, $year, $half),
      'year' => $this->assertLastDayOfYear($result, $year),
    };
  }

  /**
   * Assert the result equals the exact input date.
   */
  private function assertExactDate(string $result, int $year, int $month, int $day): void {
    $expected = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $this->assertSame($expected, $result, "Exact date should be '{$expected}', got '{$result}'.");
  }

  /**
   * Assert the result is the last day of the given month.
   */
  private function assertLastDayOfMonth(string $result, int $year, int $month): void {
    $lastDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    $expected = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
    $this->assertSame($expected, $result, "Month accuracy should yield '{$expected}', got '{$result}'.");
  }

  /**
   * Assert the result is the last day of the given quarter.
   */
  private function assertLastDayOfQuarter(string $result, int $year, int $quarter): void {
    $end = self::QUARTER_END[$quarter];
    $expected = sprintf('%04d-%02d-%02d', $year, $end['month'], $end['day']);
    $this->assertSame($expected, $result, "Quarter Q{$quarter} should yield '{$expected}', got '{$result}'.");
  }

  /**
   * Assert the result is the last day of the given half-year.
   */
  private function assertLastDayOfHalf(string $result, int $year, int $half): void {
    $end = self::HALF_END[$half];
    $expected = sprintf('%04d-%02d-%02d', $year, $end['month'], $end['day']);
    $this->assertSame($expected, $result, "Half H{$half} should yield '{$expected}', got '{$result}'.");
  }

  /**
   * Assert the result is December 31 of the given year.
   */
  private function assertLastDayOfYear(string $result, int $year): void {
    $expected = sprintf('%04d-12-31', $year);
    $this->assertSame($expected, $result, "Year accuracy should yield '{$expected}', got '{$result}'.");
  }

  // Feature: date-ish, Property 2: Formatter output contains the correct period components

  /**
   * Full month names indexed 1–12.
   */
  private const MONTH_NAMES = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
  ];

  /**
   * Quarter end months: Q1→3, Q2→6, Q3→9, Q4→12.
   */
  private const QUARTER_END_MONTH = [1 => 3, 2 => 6, 3 => 9, 4 => 12];

  /**
   * Half-year end months: H1→6, H2→12.
   */
  private const HALF_END_MONTH = [1 => 6, 2 => 12];

  /**
   * Data provider generating 120+ random accuracy + stored_date pairs.
   */
  public static function randomFormatterProvider(): \Generator {
    $seed = crc32('formatter_period_components_property_' . date('Y-m-d'));
    mt_srand($seed);

    // --- exact: 25 random dates ---
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      $month = mt_rand(1, 12);
      $lastDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
      $day = mt_rand(1, $lastDay);
      $storedDate = DateIshHelper::computeStoredDate('exact', $year, $month, $day);
      yield "exact #{$i}: {$year}-{$month}-{$day}" => [
        'exact', $storedDate, $year, $month, $day, NULL, NULL,
      ];
    }

    // --- month: 25 random year+month ---
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      $month = mt_rand(1, 12);
      $storedDate = DateIshHelper::computeStoredDate('month', $year, $month);
      yield "month #{$i}: {$year}-{$month}" => [
        'month', $storedDate, $year, $month, NULL, NULL, NULL,
      ];
    }

    // --- quarter: 25 random year+quarter ---
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      $quarter = mt_rand(1, 4);
      $storedDate = DateIshHelper::computeStoredDate('quarter', $year, quarter: $quarter);
      yield "quarter #{$i}: {$year} Q{$quarter}" => [
        'quarter', $storedDate, $year, NULL, NULL, $quarter, NULL,
      ];
    }

    // --- year_half: 25 random year+half ---
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      $half = mt_rand(1, 2);
      $storedDate = DateIshHelper::computeStoredDate('year_half', $year, half: $half);
      yield "year_half #{$i}: {$year} H{$half}" => [
        'year_half', $storedDate, $year, NULL, NULL, NULL, $half,
      ];
    }

    // --- year: 25 random years ---
    for ($i = 0; $i < 25; $i++) {
      $year = mt_rand(1900, 2100);
      $storedDate = DateIshHelper::computeStoredDate('year', $year);
      yield "year #{$i}: {$year}" => [
        'year', $storedDate, $year, NULL, NULL, NULL, NULL,
      ];
    }
  }

  /**
   * Tests that formatForDisplay output contains the correct period components.
   *
   * **Validates: Requirements 10.1, 10.2, 10.3, 10.4, 10.5**
   */
  #[DataProvider('randomFormatterProvider')]
  public function testFormatterOutputContainsPeriodComponents(
    string $accuracy,
    string $storedDate,
    int $year,
    ?int $month,
    ?int $day,
    ?int $quarter,
    ?int $half,
  ): void {
    $output = DateIshHelper::formatForDisplay($accuracy, $storedDate);

    // All accuracy levels must contain the year.
    $this->assertStringContainsString(
      (string) $year,
      $output,
      "Output '{$output}' must contain year {$year}.",
    );

    match ($accuracy) {
      'exact' => $this->assertExactComponents($output, $year, $month, $day),
      'month' => $this->assertMonthComponents($output, $year, $month),
      'quarter' => $this->assertQuarterComponents($output, $quarter),
      'year_half' => $this->assertHalfComponents($output, $half),
      'year' => $this->assertYearOnlyComponents($output, $year),
    };
  }

  /**
   * Assert exact output contains day number, full month name, and year.
   */
  private function assertExactComponents(string $output, int $year, int $month, int $day): void {
    $monthName = self::MONTH_NAMES[$month];
    $this->assertStringContainsString(
      (string) $day,
      $output,
      "Exact output '{$output}' must contain day {$day}.",
    );
    $this->assertStringContainsString(
      $monthName,
      $output,
      "Exact output '{$output}' must contain month name '{$monthName}'.",
    );
  }

  /**
   * Assert month output contains full month name and year.
   */
  private function assertMonthComponents(string $output, int $year, int $month): void {
    $monthName = self::MONTH_NAMES[$month];
    $this->assertStringContainsString(
      $monthName,
      $output,
      "Month output '{$output}' must contain month name '{$monthName}'.",
    );
  }

  /**
   * Assert quarter output contains "Q" + quarter number.
   */
  private function assertQuarterComponents(string $output, int $quarter): void {
    $this->assertStringContainsString(
      "Q{$quarter}",
      $output,
      "Quarter output '{$output}' must contain 'Q{$quarter}'.",
    );
  }

  /**
   * Assert year_half output contains "H" + half number.
   */
  private function assertHalfComponents(string $output, int $half): void {
    $this->assertStringContainsString(
      "H{$half}",
      $output,
      "Year_half output '{$output}' must contain 'H{$half}'.",
    );
  }

  /**
   * Assert year-only output is exactly the year string.
   */
  private function assertYearOnlyComponents(string $output, int $year): void {
    $this->assertSame(
      (string) $year,
      $output,
      "Year output should be exactly '{$year}', got '{$output}'.",
    );
  }

}

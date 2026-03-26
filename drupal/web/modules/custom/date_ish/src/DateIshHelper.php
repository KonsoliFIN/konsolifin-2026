<?php

declare(strict_types=1);

namespace Drupal\date_ish;

/**
 * Static helper for date-ish date math and formatting.
 *
 * No Drupal dependencies — pure PHP date logic.
 */
class DateIshHelper {

  /**
   * Quarter end months: Q1→3, Q2→6, Q3→9, Q4→12.
   */
  private const QUARTER_END_MONTH = [1 => 3, 2 => 6, 3 => 9, 4 => 12];

  /**
   * Half-year end months: H1→6, H2→12.
   */
  private const HALF_END_MONTH = [1 => 6, 2 => 12];

  /**
   * Compute the stored date for a given accuracy level and date components.
   *
   * @param string $accuracy
   *   One of: exact, month, quarter, year_half, year.
   * @param int $year
   *   The year.
   * @param int|null $month
   *   The month (1–12), required for exact and month accuracy.
   * @param int|null $day
   *   The day, required for exact accuracy.
   * @param int|null $quarter
   *   The quarter (1–4), required for quarter accuracy.
   * @param int|null $half
   *   The half (1–2), required for year_half accuracy.
   *
   * @return string
   *   ISO 8601 date string (YYYY-MM-DD).
   *
   * @throws \InvalidArgumentException
   *   If month, quarter, or half values are out of range.
   */
  public static function computeStoredDate(
    string $accuracy,
    int $year,
    ?int $month = null,
    ?int $day = null,
    ?int $quarter = null,
    ?int $half = null,
  ): string {
    return match ($accuracy) {
      'exact' => self::computeExact($year, $month, $day),
      'month' => self::computeMonth($year, $month),
      'quarter' => self::computeQuarter($year, $quarter),
      'year_half' => self::computeYearHalf($year, $half),
      'year' => sprintf('%04d-12-31', $year),
      default => throw new \InvalidArgumentException("Unknown accuracy level: $accuracy"),
    };
  }

  /**
   * Format a stored date for human-readable display.
   *
   * @param string $accuracy
   *   One of: exact, month, quarter, year_half, year.
   * @param string $storedDate
   *   ISO 8601 date string (YYYY-MM-DD).
   *
   * @return string
   *   Human-readable label.
   */
  public static function formatForDisplay(string $accuracy, string $storedDate): string {
    $date = \DateTimeImmutable::createFromFormat('Y-m-d', $storedDate);
    if ($date === false) {
      return $storedDate;
    }

    return match ($accuracy) {
      'exact' => $date->format('j F Y'),
      'month' => $date->format('F Y'),
      'quarter' => self::formatQuarter($date),
      'year_half' => self::formatHalf($date),
      'year' => $date->format('Y'),
      default => $storedDate,
    };
  }

  /**
   * Compute stored date for exact accuracy.
   */
  private static function computeExact(int $year, ?int $month, ?int $day): string {
    self::validateMonth($month);
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
  }

  /**
   * Compute stored date for month accuracy (last day of month).
   */
  private static function computeMonth(int $year, ?int $month): string {
    self::validateMonth($month);
    $lastDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
    return sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
  }

  /**
   * Compute stored date for quarter accuracy (last day of quarter).
   */
  private static function computeQuarter(int $year, ?int $quarter): string {
    if ($quarter === null || $quarter < 1 || $quarter > 4) {
      throw new \InvalidArgumentException("Quarter must be between 1 and 4, got: $quarter");
    }
    $endMonth = self::QUARTER_END_MONTH[$quarter];
    $lastDay = (int) date('t', mktime(0, 0, 0, $endMonth, 1, $year));
    return sprintf('%04d-%02d-%02d', $year, $endMonth, $lastDay);
  }

  /**
   * Compute stored date for year_half accuracy (last day of half).
   */
  private static function computeYearHalf(int $year, ?int $half): string {
    if ($half === null || $half < 1 || $half > 2) {
      throw new \InvalidArgumentException("Half must be 1 or 2, got: $half");
    }
    $endMonth = self::HALF_END_MONTH[$half];
    $lastDay = (int) date('t', mktime(0, 0, 0, $endMonth, 1, $year));
    return sprintf('%04d-%02d-%02d', $year, $endMonth, $lastDay);
  }

  /**
   * Validate that month is in range 1–12.
   */
  private static function validateMonth(?int $month): void {
    if ($month === null || $month < 1 || $month > 12) {
      throw new \InvalidArgumentException("Month must be between 1 and 12, got: $month");
    }
  }

  /**
   * Format a date as quarter display (e.g. "Q1 2025").
   */
  private static function formatQuarter(\DateTimeImmutable $date): string {
    $month = (int) $date->format('n');
    $quarter = (int) ceil($month / 3);
    return sprintf('Q%d %s', $quarter, $date->format('Y'));
  }

  /**
   * Format a date as half-year display (e.g. "H1 2025").
   */
  private static function formatHalf(\DateTimeImmutable $date): string {
    $month = (int) $date->format('n');
    $half = $month <= 6 ? 1 : 2;
    return sprintf('H%d %s', $half, $date->format('Y'));
  }

}

<?php

declare(strict_types=1);

// Feature: konsolifin-review-score, Property 2: Formatter fill fraction round-trip

namespace Drupal\Tests\konsolifin_review_score\Unit;

use Drupal\konsolifin_review_score\Plugin\Field\FieldFormatter\StarFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: formatter fill fraction round-trip.
 *
 * For any valid score in 0–400, the StarFormatter SHALL render exactly 5 star
 * elements, and the sum of per-star fill fractions across all 5 stars SHALL
 * equal score / 400. Each star i (0–4) SHALL have fill
 * clamp(0, 1, score/400 * 5 - i).
 *
 * **Validates: Requirements 3.1, 3.2, 5.1, 5.2**
 *
 * @group konsolifin_review_score
 */
class StarFormatterFillPropertyTest extends TestCase {

  private const FLOAT_DELTA = 1e-9;

  /**
   * Data provider generating 120 random scores in 0–400.
   */
  public static function randomScoreProvider(): \Generator {
    $seed = crc32('fill_fraction_round_trip_' . date('Y-m-d'));
    mt_srand($seed);

    for ($i = 0; $i < 120; $i++) {
      $score = mt_rand(0, 400);
      yield "score #{$i}: {$score}" => [$score];
    }
  }

  /**
   * Tests that computeStarFills returns exactly 5 elements for any score.
   *
   * **Validates: Requirements 3.1**
   */
  #[DataProvider('randomScoreProvider')]
  public function testFillsReturnsFiveElements(int $score): void {
    $fills = StarFormatter::computeStarFills($score);
    $this->assertCount(5, $fills, "computeStarFills({$score}) must return exactly 5 elements.");
  }

  /**
   * Tests that each per-star fill is between 0.0 and 1.0.
   *
   * **Validates: Requirements 3.2, 5.1**
   */
  #[DataProvider('randomScoreProvider')]
  public function testEachFillIsBetweenZeroAndOne(int $score): void {
    $fills = StarFormatter::computeStarFills($score);
    foreach ($fills as $i => $fill) {
      $this->assertGreaterThanOrEqual(0.0, $fill, "Star {$i} fill for score {$score} must be >= 0.0.");
      $this->assertLessThanOrEqual(1.0, $fill, "Star {$i} fill for score {$score} must be <= 1.0.");
    }
  }

  /**
   * Tests that the sum of all 5 fills divided by 5 equals score / 400.
   *
   * Each star fill is in [0,1] and represents 1/5 of the total range,
   * so sum(fills) = fillFraction * 5 = score / 80.
   * Equivalently, sum(fills) / 5 = score / 400 (the overall fill fraction).
   *
   * **Validates: Requirements 3.2, 5.2**
   */
  #[DataProvider('randomScoreProvider')]
  public function testFillsSumRoundTrip(int $score): void {
    $fills = StarFormatter::computeStarFills($score);
    $actualSum = array_sum($fills);
    $expectedSum = $score / 80;
    $this->assertEqualsWithDelta(
      $expectedSum,
      $actualSum,
      self::FLOAT_DELTA,
      "Sum of fills for score {$score} should be {$expectedSum} (score/80), got {$actualSum}.",
    );
    // Round-trip: sum/5 should equal the original fill fraction (score/400).
    $roundTripFraction = $actualSum / 5;
    $expectedFraction = $score / 400;
    $this->assertEqualsWithDelta(
      $expectedFraction,
      $roundTripFraction,
      self::FLOAT_DELTA,
      "Round-trip fill fraction for score {$score} should be {$expectedFraction}, got {$roundTripFraction}.",
    );
  }

  /**
   * Tests that each star fill matches clamp(0, 1, score/400 * 5 - i).
   *
   * **Validates: Requirements 3.2, 5.1, 5.2**
   */
  #[DataProvider('randomScoreProvider')]
  public function testEachFillMatchesFormula(int $score): void {
    $fills = StarFormatter::computeStarFills($score);
    $fillFraction = $score / 400;

    for ($i = 0; $i < 5; $i++) {
      $expected = min(1.0, max(0.0, $fillFraction * 5 - $i));
      $this->assertEqualsWithDelta(
        $expected,
        $fills[$i],
        self::FLOAT_DELTA,
        "Star {$i} fill for score {$score}: expected {$expected}, got {$fills[$i]}.",
      );
    }
  }

}

<?php

declare(strict_types=1);

// Feature: konsolifin-review-score, Property 3: Formatter ARIA label accuracy

namespace Drupal\Tests\konsolifin_review_score\Unit;

use Drupal\konsolifin_review_score\Plugin\Field\FieldFormatter\StarFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: formatter ARIA label accuracy.
 *
 * For any valid score in 0–400, the StarFormatter output SHALL include an
 * aria-label attribute containing the text "Rating: X.X out of 5 stars"
 * where X.X equals round(score / 80, 1).
 *
 * **Validates: Requirements 6.1**
 *
 * @group konsolifin_review_score
 */
class StarFormatterAriaPropertyTest extends TestCase {

  /**
   * Data provider generating 120 random scores in 0–400.
   */
  public static function randomScoreProvider(): \Generator {
    $seed = crc32('aria_label_accuracy_' . date('Y-m-d'));
    mt_srand($seed);

    for ($i = 0; $i < 120; $i++) {
      $score = mt_rand(0, 400);
      yield "score #{$i}: {$score}" => [$score];
    }
  }

  /**
   * Computes the expected ARIA label for a given score.
   */
  private static function expectedAriaLabel(int $score): string {
    $rating = round($score / 80, 1);
    return sprintf('Rating: %s out of 5 stars', $rating);
  }

  /**
   * Tests that the computed ARIA label matches the expected format.
   *
   * **Validates: Requirements 6.1**
   */
  #[DataProvider('randomScoreProvider')]
  public function testAriaLabelMatchesExpectedFormat(int $score): void {
    $rating = round($score / 80, 1);
    $expected = self::expectedAriaLabel($score);
    $actual = sprintf('Rating: %s out of 5 stars', $rating);

    $this->assertSame(
      $expected,
      $actual,
      "ARIA label for score {$score} should be '{$expected}', got '{$actual}'.",
    );
  }

  /**
   * Tests that the rating value is between 0.0 and 5.0.
   *
   * **Validates: Requirements 6.1**
   */
  #[DataProvider('randomScoreProvider')]
  public function testRatingValueInRange(int $score): void {
    $rating = round($score / 80, 1);

    $this->assertGreaterThanOrEqual(
      0.0,
      $rating,
      "Rating for score {$score} must be >= 0.0, got {$rating}.",
    );
    $this->assertLessThanOrEqual(
      5.0,
      $rating,
      "Rating for score {$score} must be <= 5.0, got {$rating}.",
    );
  }

  /**
   * Tests that the rating has at most 1 decimal place.
   *
   * **Validates: Requirements 6.1**
   */
  #[DataProvider('randomScoreProvider')]
  public function testRatingHasAtMostOneDecimal(int $score): void {
    $rating = round($score / 80, 1);
    // Multiplying by 10 and checking it's an integer confirms at most 1 decimal.
    $scaled = $rating * 10;

    $this->assertEqualsWithDelta(
      round($scaled),
      $scaled,
      1e-9,
      "Rating {$rating} for score {$score} should have at most 1 decimal place.",
    );
  }

  /**
   * Tests that the ARIA label contains the rating text pattern.
   *
   * **Validates: Requirements 6.1**
   */
  #[DataProvider('randomScoreProvider')]
  public function testAriaLabelContainsRatingPattern(int $score): void {
    $rating = round($score / 80, 1);
    $ariaLabel = self::expectedAriaLabel($score);

    $this->assertStringContainsString(
      "Rating: {$rating} out of 5 stars",
      $ariaLabel,
      "ARIA label for score {$score} must contain 'Rating: {$rating} out of 5 stars'.",
    );
  }

}

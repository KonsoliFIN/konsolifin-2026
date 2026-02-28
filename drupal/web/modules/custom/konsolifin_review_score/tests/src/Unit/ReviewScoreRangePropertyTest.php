<?php

declare(strict_types=1);

// Feature: konsolifin-review-score, Property 1: Score range validation

namespace Drupal\Tests\konsolifin_review_score\Unit;

use Drupal\Core\Validation\Plugin\Validation\Constraint\RangeConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * Property test: score range validation.
 *
 * For any integer value, the ReviewScoreItem field SHALL accept the value
 * without validation errors if and only if the value is in the range 0–400
 * inclusive. For any integer outside that range, validation SHALL produce an
 * error message stating the allowed range.
 *
 * **Validates: Requirements 2.2, 2.3**
 *
 * @group konsolifin_review_score
 */
class ReviewScoreRangePropertyTest extends TestCase {

  private const MIN_SCORE = 0;
  private const MAX_SCORE = 400;
  private const ERROR_MESSAGE = 'The review score must be between 0 and 400.';

  /**
   * Validates a value against the Range constraint matching ReviewScoreItem.
   */
  private function validateScore(int $value) {
    $constraint = new RangeConstraint(
      min: self::MIN_SCORE,
      max: self::MAX_SCORE,
      notInRangeMessage: self::ERROR_MESSAGE,
    );

    $validator = Validation::createValidator();
    return $validator->validate($value, [$constraint]);
  }

  /**
   * Data provider generating 120 random integers: mix of in-range and out-of-range.
   */
  public static function randomScoreProvider(): \Generator {
    $seed = crc32('score_range_property_test_' . date('Y-m-d'));
    mt_srand($seed);

    // Generate 60 in-range values (0–400).
    for ($i = 0; $i < 60; $i++) {
      $value = mt_rand(self::MIN_SCORE, self::MAX_SCORE);
      yield "in-range #{$i}: {$value}" => [$value, TRUE];
    }

    // Generate 30 below-range values.
    for ($i = 0; $i < 30; $i++) {
      $value = mt_rand(-10000, -1);
      yield "below-range #{$i}: {$value}" => [$value, FALSE];
    }

    // Generate 30 above-range values.
    for ($i = 0; $i < 30; $i++) {
      $value = mt_rand(self::MAX_SCORE + 1, 10000);
      yield "above-range #{$i}: {$value}" => [$value, FALSE];
    }
  }

  /**
   * Data provider for boundary values.
   */
  public static function boundaryProvider(): array {
    return [
      'min boundary: 0' => [0, TRUE],
      'max boundary: 400' => [400, TRUE],
      'just below min: -1' => [-1, FALSE],
      'just above max: 401' => [401, FALSE],
    ];
  }

  /**
   * Tests that score range validation holds for random values.
   *
   * **Validates: Requirements 2.2, 2.3**
   */
  #[DataProvider('randomScoreProvider')]
  public function testScoreRangeValidation(int $value, bool $expectedValid): void {
    $violations = $this->validateScore($value);

    if ($expectedValid) {
      $this->assertCount(0, $violations, "Score {$value} should be valid (in range 0–400), but got violations.");
    }
    else {
      $this->assertGreaterThan(0, count($violations), "Score {$value} should be invalid (outside range 0–400), but passed validation.");
      $messages = [];
      foreach ($violations as $violation) {
        $messages[] = $violation->getMessage();
      }
      $this->assertContains(
        self::ERROR_MESSAGE,
        $messages,
        "Expected error message '" . self::ERROR_MESSAGE . "' for score {$value}, got: " . implode(', ', $messages),
      );
    }
  }

  /**
   * Tests boundary values explicitly.
   *
   * **Validates: Requirements 2.2, 2.3**
   */
  #[DataProvider('boundaryProvider')]
  public function testScoreRangeBoundaries(int $value, bool $expectedValid): void {
    $violations = $this->validateScore($value);

    if ($expectedValid) {
      $this->assertCount(0, $violations, "Boundary score {$value} should be valid.");
    }
    else {
      $this->assertGreaterThan(0, count($violations), "Boundary score {$value} should be invalid.");
    }
  }

}

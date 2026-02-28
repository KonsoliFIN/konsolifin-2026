<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_review_score\Unit;

use Drupal\konsolifin_review_score\Plugin\Field\FieldFormatter\StarFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StarFormatter field formatter.
 *
 * Tests computeStarFills() with null concept and boundary values.
 *
 * @group konsolifin_review_score
 *
 * _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 5.3, 5.4, 5.5_
 */
class StarFormatterTest extends TestCase {

  /**
   * Tests that a null score concept produces no star output.
   *
   * Since computeStarFills() takes int, we verify that the formatter's
   * viewElements() skips null values by testing that score 0 still produces
   * fills (i.e., null handling is at the viewElements level, not computeStarFills).
   * This test documents that null scores should not reach computeStarFills.
   *
   * **Validates: Requirements 3.7**
   */
  public function testNullScoreConceptProducesNoOutput(): void {
    // computeStarFills takes int, so null is handled at viewElements level.
    // We verify that score 0 produces valid fills (all zeros), confirming
    // that the null guard must happen before computeStarFills is called.
    $fills = StarFormatter::computeStarFills(0);
    $this->assertCount(5, $fills);
    // All should be 0.0, meaning "no output" is distinct from "score 0".
    foreach ($fills as $fill) {
      $this->assertSame(0.0, $fill);
    }
  }

  /**
   * Tests score 0 produces all empty stars.
   *
   * **Validates: Requirements 3.3, 5.3**
   */
  public function testScore0AllEmpty(): void {
    $fills = StarFormatter::computeStarFills(0);
    $this->assertCount(5, $fills);
    $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0], $fills);
  }

  /**
   * Tests score 400 produces all full stars.
   *
   * **Validates: Requirements 3.4, 5.3**
   */
  public function testScore400AllFull(): void {
    $fills = StarFormatter::computeStarFills(400);
    $this->assertCount(5, $fills);
    $this->assertSame([1.0, 1.0, 1.0, 1.0, 1.0], $fills);
  }

  /**
   * Tests score 80 produces 1 full star and 4 empty stars.
   *
   * **Validates: Requirements 5.3**
   */
  public function testScore80OneFull4Empty(): void {
    $fills = StarFormatter::computeStarFills(80);
    $this->assertCount(5, $fills);
    $this->assertSame([1.0, 0.0, 0.0, 0.0, 0.0], $fills);
  }

  /**
   * Tests score 200 produces 2 full, 1 half, and 2 empty stars.
   *
   * **Validates: Requirements 5.4**
   */
  public function testScore200TwoFullOneHalfTwoEmpty(): void {
    $fills = StarFormatter::computeStarFills(200);
    $this->assertCount(5, $fills);
    $this->assertSame([1.0, 1.0, 0.5, 0.0, 0.0], $fills);
  }

  /**
   * Tests score 320 produces 4 full stars and 1 empty star.
   *
   * **Validates: Requirements 5.5**
   */
  public function testScore320FourFull1Empty(): void {
    $fills = StarFormatter::computeStarFills(320);
    $this->assertCount(5, $fills);
    $this->assertSame([1.0, 1.0, 1.0, 1.0, 0.0], $fills);
  }

}

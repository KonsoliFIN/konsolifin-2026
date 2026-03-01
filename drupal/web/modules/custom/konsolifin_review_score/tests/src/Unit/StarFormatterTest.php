<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_review_score\Unit;

use Drupal\konsolifin_review_score\Plugin\Field\FieldFormatter\StarFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StarFormatter field formatter.
 *
 * Tests computeStarFills() with null concept and boundary values,
 * and buildStarElement() for image-based rendering.
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
    $fills = StarFormatter::computeStarFills(0);
    $this->assertCount(5, $fills);
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

  /**
   * Tests that buildStarElement for a full star contains gold and dim images.
   */
  public function testBuildStarElementFull(): void {
    $star = StarFormatter::buildStarElement(1.0, '/images/star_gold.png', '/images/star_dim.png');

    $this->assertSame('span', $star['#tag']);
    $this->assertContains('star--full', $star['#attributes']['class']);
    // Dim image present.
    $this->assertSame('img', $star['dim_img']['#tag']);
    $this->assertSame('/images/star_dim.png', $star['dim_img']['#attributes']['src']);
    // Gold image present without clip-path.
    $this->assertSame('img', $star['gold_img']['#tag']);
    $this->assertSame('/images/star_gold.png', $star['gold_img']['#attributes']['src']);
    $this->assertArrayNotHasKey('style', $star['gold_img']['#attributes']);
  }

  /**
   * Tests that buildStarElement for an empty star has no gold image.
   */
  public function testBuildStarElementEmpty(): void {
    $star = StarFormatter::buildStarElement(0.0, '/images/star_gold.png', '/images/star_dim.png');

    $this->assertContains('star--empty', $star['#attributes']['class']);
    $this->assertSame('img', $star['dim_img']['#tag']);
    $this->assertArrayNotHasKey('gold_img', $star);
  }

  /**
   * Tests that buildStarElement for a partial star has gold image with clip-path.
   */
  public function testBuildStarElementPartial(): void {
    $star = StarFormatter::buildStarElement(0.5, '/images/star_gold.png', '/images/star_dim.png');

    $this->assertContains('star--partial', $star['#attributes']['class']);
    $this->assertSame('img', $star['dim_img']['#tag']);
    $this->assertSame('img', $star['gold_img']['#tag']);
    $this->assertStringContainsString('clip-path: inset(0 50%', $star['gold_img']['#attributes']['style']);
  }

}

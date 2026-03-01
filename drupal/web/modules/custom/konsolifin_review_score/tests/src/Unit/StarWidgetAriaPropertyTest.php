<?php

declare(strict_types=1);

// Feature: konsolifin-review-score, Property 6: Widget ARIA slider attributes

namespace Drupal\Tests\konsolifin_review_score\Unit;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\konsolifin_review_score\Plugin\Field\FieldWidget\StarWidget;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: widget ARIA slider attributes.
 *
 * For any valid score in 0–400, the StarWidget render output SHALL include a
 * container element with role="slider", aria-valuemin="0", aria-valuemax="400",
 * and aria-valuenow equal to the current score value.
 *
 * **Validates: Requirements 6.2**
 *
 * @group konsolifin_review_score
 */
#[AllowMockObjectsWithoutExpectations]
class StarWidgetAriaPropertyTest extends TestCase {

  /**
   * Data provider generating 120 random scores in 0–400.
   */
  public static function randomScoreProvider(): \Generator {
    $seed = crc32('widget_aria_slider_' . date('Y-m-d'));
    mt_srand($seed);

    for ($i = 0; $i < 120; $i++) {
      $score = mt_rand(0, 400);
      yield "score #{$i}: {$score}" => [$score];
    }
  }

  /**
   * Calls formElement() on the widget with the given score and returns the render array.
   */
  private function callFormElement(int $score): array {
    $widget = $this->getMockBuilder(StarWidget::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getSetting', 'getImagePaths'])
      ->getMock();

    $widget->method('getSetting')->willReturnMap([
      ['width', 200],
    ]);

    $widget->method('getImagePaths')->willReturn([
      'gold' => '/modules/custom/konsolifin_review_score/images/star_gold.png',
      'dim' => '/modules/custom/konsolifin_review_score/images/star_dim.png',
    ]);

    $item = new \stdClass();
    $item->value = $score;

    $items = $this->createStub(FieldItemListInterface::class);
    $items->method('offsetExists')->willReturn(TRUE);
    $items->method('offsetGet')->willReturn($item);

    $formState = $this->createStub(FormStateInterface::class);
    $form = [];

    return $widget->formElement($items, 0, [], $form, $formState);
  }

  /**
   * Tests that the container has role="slider".
   *
   * **Validates: Requirements 6.2**
   */
  #[DataProvider('randomScoreProvider')]
  public function testContainerHasRoleSlider(int $score): void {
    $result = $this->callFormElement($score);

    $this->assertArrayHasKey('container', $result);
    $attrs = $result['container']['#attributes'];
    $this->assertSame(
      'slider',
      $attrs['role'],
      "Container for score {$score} must have role='slider'.",
    );
  }

  /**
   * Tests that aria-valuemin is "0".
   *
   * **Validates: Requirements 6.2**
   */
  #[DataProvider('randomScoreProvider')]
  public function testAriaValueminIsZero(int $score): void {
    $result = $this->callFormElement($score);
    $attrs = $result['container']['#attributes'];

    $this->assertSame(
      '0',
      $attrs['aria-valuemin'],
      "Container for score {$score} must have aria-valuemin='0'.",
    );
  }

  /**
   * Tests that aria-valuemax is "400".
   *
   * **Validates: Requirements 6.2**
   */
  #[DataProvider('randomScoreProvider')]
  public function testAriaValuemaxIs400(int $score): void {
    $result = $this->callFormElement($score);
    $attrs = $result['container']['#attributes'];

    $this->assertSame(
      '400',
      $attrs['aria-valuemax'],
      "Container for score {$score} must have aria-valuemax='400'.",
    );
  }

  /**
   * Tests that aria-valuenow equals the string representation of the score.
   *
   * **Validates: Requirements 6.2**
   */
  #[DataProvider('randomScoreProvider')]
  public function testAriaValuenowMatchesScore(int $score): void {
    $result = $this->callFormElement($score);
    $attrs = $result['container']['#attributes'];

    $this->assertSame(
      (string) $score,
      $attrs['aria-valuenow'],
      "Container for score {$score} must have aria-valuenow='{$score}'.",
    );
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_review_score\Unit;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\konsolifin_review_score\Plugin\Field\FieldWidget\StarWidget;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for StarWidget::formElement().
 *
 * **Validates: Requirements 4.1, 4.5, 4.8, 6.2, 6.3**
 *
 * @group konsolifin_review_score
 */
#[AllowMockObjectsWithoutExpectations]
class StarWidgetTest extends TestCase {

  /**
   * Calls formElement() on the widget with the given score.
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
   * Tests that formElement() returns a container with role="slider".
   *
   * **Validates: Requirements 4.1, 6.2**
   */
  public function testFormElementContainsContainerWithRoleSlider(): void {
    $result = $this->callFormElement(200);

    $this->assertArrayHasKey('container', $result);
    $this->assertSame('slider', $result['container']['#attributes']['role']);
  }

  /**
   * Tests that the container contains exactly 5 star span elements.
   *
   * **Validates: Requirements 4.1**
   */
  public function testFormElementContainsFiveStarSpans(): void {
    $result = $this->callFormElement(200);

    $this->assertArrayHasKey('stars', $result['container']);
    $this->assertCount(5, $result['container']['stars']);

    foreach ($result['container']['stars'] as $star) {
      $this->assertSame('span', $star['#tag']);
    }
  }

  /**
   * Tests that formElement() includes a hidden input for Form API submission.
   *
   * **Validates: Requirements 4.5**
   */
  public function testFormElementContainsHiddenInput(): void {
    $result = $this->callFormElement(200);

    $this->assertArrayHasKey('value', $result);
    $this->assertSame('hidden', $result['value']['#type']);
  }

  /**
   * Tests that formElement() includes a reset element.
   *
   * **Validates: Requirements 4.8**
   */
  public function testFormElementContainsResetElement(): void {
    $result = $this->callFormElement(200);

    $this->assertArrayHasKey('reset', $result);
    $this->assertSame('button', $result['reset']['#tag']);
  }

  /**
   * Tests that the star-widget library is attached.
   *
   * **Validates: Requirements 4.1**
   */
  public function testFormElementAttachesLibrary(): void {
    $result = $this->callFormElement(200);

    $this->assertArrayHasKey('#attached', $result);
    $this->assertContains(
      'konsolifin_review_score/star-widget',
      $result['#attached']['library'],
    );
  }

  /**
   * Tests that the container is focusable via tabindex="0".
   *
   * **Validates: Requirements 6.3**
   */
  public function testContainerIsFocusable(): void {
    $result = $this->callFormElement(200);

    $this->assertSame('0', $result['container']['#attributes']['tabindex']);
  }

}

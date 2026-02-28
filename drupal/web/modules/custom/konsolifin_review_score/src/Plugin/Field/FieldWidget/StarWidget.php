<?php

declare(strict_types=1);

namespace Drupal\konsolifin_review_score\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\konsolifin_review_score\Plugin\Field\FieldFormatter\StarFormatter;

/**
 * Plugin implementation of the 'star_widget' widget.
 */
#[FieldWidget(
  id: "star_widget",
  label: new TranslatableMarkup("Star Widget"),
  field_types: ["review_score"],
)]
class StarWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $value = $items[$delta]->value ?? NULL;
    $score = $value !== NULL ? (int) $value : NULL;

    // Compute star fills for the current score.
    $fills = $score !== NULL ? StarFormatter::computeStarFills($score) : array_fill(0, 5, 0.0);

    // Build the five star span elements.
    $stars = [];
    for ($i = 0; $i < 5; $i++) {
      $fill = $fills[$i];
      $star = [
        '#type' => 'html_tag',
        '#tag' => 'span',
      ];

      if ($fill >= 1.0) {
        $star['#attributes']['class'] = ['review-score-star', 'star--full'];
      }
      elseif ($fill <= 0.0) {
        $star['#attributes']['class'] = ['review-score-star', 'star--empty'];
      }
      else {
        $pct = $fill * 100;
        $star['#attributes']['class'] = ['review-score-star', 'star--partial'];
        $star['#attributes']['style'] = sprintf(
          'background: linear-gradient(90deg, #f5a623 %s%%, #ccc %s%%);',
          $pct,
          $pct,
        );
      }

      $stars[] = $star;
    }

    // Container div with ARIA slider attributes.
    $element['container'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['review-score-stars', 'review-score-widget'],
        'role' => 'slider',
        'tabindex' => '0',
        'aria-valuemin' => '0',
        'aria-valuemax' => '400',
        'aria-valuenow' => $score !== NULL ? (string) $score : '',
        'aria-label' => 'Review score',
      ],
      'stars' => $stars,
    ];

    // Hidden input for Form API submission.
    $element['value'] = [
      '#type' => 'hidden',
      '#default_value' => $score,
    ];

    // Reset button to clear the value.
    $element['reset'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => '×',
      '#attributes' => [
        'type' => 'button',
        'class' => ['review-score-reset'],
      ],
    ];

    // Attach the star-widget library.
    $element['#attached'] = [
      'library' => ['konsolifin_review_score/star-widget'],
    ];

    return $element;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\konsolifin_review_score\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'star_formatter' formatter.
 */
#[FieldFormatter(
  id: "star_formatter",
  label: new TranslatableMarkup("Star Rating"),
  field_types: ["review_score"],
)]
class StarFormatter extends FormatterBase {

  /**
   * Computes per-star fill values for a given score.
   *
   * @param int $score
   *   The review score (0–400).
   *
   * @return float[]
   *   Array of 5 float values (0.0 to 1.0), one per star.
   */
  public static function computeStarFills(int $score): array {
    $fillFraction = $score / 400;
    $fills = [];
    for ($i = 0; $i < 5; $i++) {
      $fills[] = min(1.0, max(0.0, $fillFraction * 5 - $i));
    }
    return $fills;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $value = $item->value;

      if ($value === NULL) {
        continue;
      }

      $score = (int) $value;
      $fills = self::computeStarFills($score);
      $rating = round($score / 80, 1);

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

      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['review-score-stars'],
          'aria-label' => sprintf('Rating: %s out of 5 stars', $rating),
        ],
        'stars' => $stars,
        '#attached' => [
          'library' => ['konsolifin_review_score/star-rating'],
        ],
      ];
    }

    return $elements;
  }

}

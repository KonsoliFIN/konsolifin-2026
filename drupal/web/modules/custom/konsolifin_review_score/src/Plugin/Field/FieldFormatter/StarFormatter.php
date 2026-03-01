<?php

declare(strict_types=1);

namespace Drupal\konsolifin_review_score\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
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
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'width' => 200,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['width'] = [
      '#type' => 'number',
      '#title' => new TranslatableMarkup('Width (px)'),
      '#default_value' => $this->getSetting('width'),
      '#min' => 50,
      '#max' => 1000,
      '#description' => new TranslatableMarkup('Total width of the star rating display in pixels.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [
      $this->t('Width: @widthpx', ['@width' => $this->getSetting('width')]),
    ];
  }

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
   * Builds a single star render array using image elements.
   *
   * @param float $fill
   *   The fill fraction for this star (0.0 to 1.0).
   * @param string $goldPath
   *   The path to the gold star image.
   * @param string $dimPath
   *   The path to the dim star image.
   *
   * @return array
   *   A render array for one star.
   */
  public static function buildStarElement(float $fill, string $goldPath, string $dimPath): array {
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
      $pct = round($fill * 100, 2);
      $star['#attributes']['class'] = ['review-score-star', 'star--partial'];
      $star['gold_img'] = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => $goldPath,
          'alt' => '',
          'class' => ['star-img-gold'],
          'style' => sprintf('clip-path: inset(0 %s%% 0 0);', round(100 - $pct, 2)),
        ],
      ];
    }

    // Dim image is always present (background).
    $star['dim_img'] = [
      '#type' => 'html_tag',
      '#tag' => 'img',
      '#attributes' => [
        'src' => $dimPath,
        'alt' => '',
        'class' => ['star-img-dim'],
      ],
    ];

    // Gold image for full stars (partial stars add it above with clip-path).
    if ($fill >= 1.0) {
      $star['gold_img'] = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => $goldPath,
          'alt' => '',
          'class' => ['star-img-gold'],
        ],
      ];
    }

    return $star;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $modulePath = $this->fieldDefinition->getFieldStorageDefinition()
      ->getProvider();
    // Build image paths relative to the module.
    $basePath = \Drupal::service('extension.list.module')->getPath($modulePath);
    $goldPath = '/' . $basePath . '/images/star_gold.png';
    $dimPath = '/' . $basePath . '/images/star_dim.png';
    $width = (int) $this->getSetting('width');

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
        $stars[] = self::buildStarElement($fills[$i], $goldPath, $dimPath);
      }

      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['review-score-stars'],
          'aria-label' => sprintf('Rating: %s out of 5 stars', $rating),
          'style' => sprintf('--review-score-width: %dpx;', $width),
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

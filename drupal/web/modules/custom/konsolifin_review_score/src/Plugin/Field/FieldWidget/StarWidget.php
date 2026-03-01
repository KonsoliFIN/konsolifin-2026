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
      '#description' => new TranslatableMarkup('Total width of the star widget in pixels.'),
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
   * Returns the image paths for gold and dim stars.
   *
   * @return array
   *   An array with 'gold' and 'dim' keys containing the image paths.
   */
  protected function getImagePaths(): array {
    $modulePath = \Drupal::service('extension.list.module')->getPath('konsolifin_review_score');
    return [
      'gold' => '/' . $modulePath . '/images/star_gold.png',
      'dim' => '/' . $modulePath . '/images/star_dim.png',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $value = $items[$delta]->value ?? NULL;
    $score = $value !== NULL ? (int) $value : NULL;
    $width = (int) $this->getSetting('width');

    // Resolve image paths.
    $imagePaths = $this->getImagePaths();
    $goldPath = $imagePaths['gold'];
    $dimPath = $imagePaths['dim'];

    // Compute star fills for the current score.
    $fills = $score !== NULL ? StarFormatter::computeStarFills($score) : array_fill(0, 5, 0.0);

    // Build the five star elements using images.
    $stars = [];
    for ($i = 0; $i < 5; $i++) {
      $stars[] = StarFormatter::buildStarElement($fills[$i], $goldPath, $dimPath);
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
        'style' => sprintf('--review-score-width: %dpx;', $width),
        'data-gold-src' => $goldPath,
        'data-dim-src' => $dimPath,
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

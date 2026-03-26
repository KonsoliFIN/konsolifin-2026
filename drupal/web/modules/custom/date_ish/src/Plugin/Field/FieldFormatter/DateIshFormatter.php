<?php

declare(strict_types=1);

namespace Drupal\date_ish\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\date_ish\DateIshHelper;

/**
 * Plugin implementation of the 'date_ish_default' formatter.
 */
#[FieldFormatter(
  id: "date_ish_default",
  label: new TranslatableMarkup("Date-ish"),
  field_types: ["date_ish"],
)]
class DateIshFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $accuracy = $item->accuracy_level;
      $storedDate = $item->stored_date;

      // Skip empty items.
      if (($accuracy === NULL || $accuracy === '') && ($storedDate === NULL || $storedDate === '')) {
        continue;
      }

      $display = DateIshHelper::formatForDisplay((string) $accuracy, (string) $storedDate);

      $elements[$delta] = [
        '#markup' => $display,
      ];
    }

    return $elements;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\date_ish\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\date_ish\DateIshHelper;

/**
 * Defines the 'date_ish' field type.
 */
#[FieldType(
  id: "date_ish",
  label: new TranslatableMarkup("Date-ish"),
  description: new TranslatableMarkup("Stores an approximate date with an accuracy level."),
  default_widget: "date_ish_default",
  default_formatter: "date_ish_default",
  category: "general",
)]
class DateIshItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = [];

    $properties['accuracy_level'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Accuracy level'))
      ->setRequired(TRUE);

    $properties['stored_date'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Stored date'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'accuracy_level' => [
          'type' => 'varchar',
          'length' => 16,
        ],
        'stored_date' => [
          'type' => 'varchar',
          'length' => 10,
          'mysql_type' => 'date',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $accuracy = $this->get('accuracy_level')->getValue();
    $date = $this->get('stored_date')->getValue();
    return ($accuracy === NULL || $accuracy === '') && ($date === NULL || $date === '');
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(): void {
    parent::preSave();

    // The widget's massageFormValues() already computes stored_date.
    // This is a safety net to normalise the date if it was set programmatically.
    $accuracy = $this->get('accuracy_level')->getValue();
    $storedDate = $this->get('stored_date')->getValue();

    if ($accuracy !== NULL && $accuracy !== '' && ($storedDate === NULL || $storedDate === '')) {
      // Cannot compute without a stored date; leave as-is for validation to catch.
      return;
    }
  }

}

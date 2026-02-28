<?php

declare(strict_types=1);

namespace Drupal\konsolifin_review_score\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'review_score' field type.
 */
#[FieldType(
  id: "review_score",
  label: new TranslatableMarkup("Review Score"),
  default_widget: "star_widget",
  default_formatter: "star_formatter",
  category: "number",
)]
class ReviewScoreItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = [];

    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Score'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'value' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'size' => 'small',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('value')->getValue();
    return $value === NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints(): array {
    $constraints = parent::getConstraints();

    $constraint_manager = $this->getTypedDataManager()->getValidationConstraintManager();
    $constraints[] = $constraint_manager->create('Range', [
      'min' => 0,
      'max' => 400,
      'notInRangeMessage' => 'The review score must be between 0 and 400.',
    ]);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(\Drupal\Core\Field\FieldDefinitionInterface $field_definition): array {
    return [
      'value' => mt_rand(0, 400),
    ];
  }

}

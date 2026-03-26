<?php

declare(strict_types=1);

namespace Drupal\date_ish\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\date_ish\DateIshHelper;

/**
 * Plugin implementation of the 'date_ish_default' widget.
 */
#[FieldWidget(
  id: "date_ish_default",
  label: new TranslatableMarkup("Date-ish"),
  field_types: ["date_ish"],
)]
class DateIshWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $accuracy = $items[$delta]->accuracy_level ?? 'exact';
    $storedDate = $items[$delta]->stored_date ?? '';

    // Parse existing stored date for pre-populating sub-inputs.
    $existingYear = '';
    $existingMonth = '';
    $existingDay = '';
    $existingQuarter = '';
    $existingHalf = '';
    $existingDate = '';

    if ($storedDate !== '' && $storedDate !== NULL) {
      $date = \DateTimeImmutable::createFromFormat('Y-m-d', $storedDate);
      if ($date !== FALSE) {
        $existingYear = (int) $date->format('Y');
        $existingMonth = (int) $date->format('n');
        $existingDay = (int) $date->format('j');
        $existingDate = $storedDate;

        // Derive quarter/half from stored month.
        $existingQuarter = (int) ceil($existingMonth / 3);
        $existingHalf = $existingMonth <= 6 ? 1 : 2;
      }
    }

    $element['#type'] = 'fieldset';
    $element['#element_validate'] = [[static::class, 'validateElement']];

    // Accuracy select — always visible.
    $element['accuracy'] = [
      '#type' => 'select',
      '#title' => $this->t('Accuracy'),
      '#options' => [
        'exact' => $this->t('Exact date'),
        'month' => $this->t('Month'),
        'quarter' => $this->t('Quarter'),
        'year_half' => $this->t('Year half'),
        'year' => $this->t('Year'),
      ],
      '#default_value' => $accuracy,
      '#attributes' => [
        'class' => ['date-ish-accuracy'],
      ],
    ];

    // --- Exact date wrapper ---
    $element['exact_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['date-ish-exact-wrapper'],
      ],
    ];
    $element['exact_wrapper']['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#default_value' => $existingDate,
    ];

    // --- Month wrapper ---
    $element['month_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['date-ish-month-wrapper'],
      ],
    ];
    $element['month_wrapper']['year'] = [
      '#type' => 'number',
      '#title' => $this->t('Year'),
      '#default_value' => ($accuracy === 'month' && $existingYear) ? $existingYear : '',
      '#min' => 1,
      '#max' => 9999,
    ];
    $element['month_wrapper']['month'] = [
      '#type' => 'select',
      '#title' => $this->t('Month'),
      '#options' => ['' => $this->t('- Select -')] + $this->getMonthOptions(),
      '#default_value' => ($accuracy === 'month' && $existingMonth) ? $existingMonth : '',
    ];

    // --- Quarter wrapper ---
    $element['quarter_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['date-ish-quarter-wrapper'],
      ],
    ];
    $element['quarter_wrapper']['year'] = [
      '#type' => 'number',
      '#title' => $this->t('Year'),
      '#default_value' => ($accuracy === 'quarter' && $existingYear) ? $existingYear : '',
      '#min' => 1,
      '#max' => 9999,
    ];
    $element['quarter_wrapper']['quarter'] = [
      '#type' => 'select',
      '#title' => $this->t('Quarter'),
      '#options' => [
        '' => $this->t('- Select -'),
        '1' => $this->t('Q1'),
        '2' => $this->t('Q2'),
        '3' => $this->t('Q3'),
        '4' => $this->t('Q4'),
      ],
      '#default_value' => ($accuracy === 'quarter' && $existingQuarter) ? $existingQuarter : '',
    ];

    // --- Year half wrapper ---
    $element['year_half_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['date-ish-year-half-wrapper'],
      ],
    ];
    $element['year_half_wrapper']['year'] = [
      '#type' => 'number',
      '#title' => $this->t('Year'),
      '#default_value' => ($accuracy === 'year_half' && $existingYear) ? $existingYear : '',
      '#min' => 1,
      '#max' => 9999,
    ];
    $element['year_half_wrapper']['half'] = [
      '#type' => 'select',
      '#title' => $this->t('Half'),
      '#options' => [
        '' => $this->t('- Select -'),
        '1' => $this->t('H1'),
        '2' => $this->t('H2'),
      ],
      '#default_value' => ($accuracy === 'year_half' && $existingHalf) ? $existingHalf : '',
    ];

    // --- Year-only wrapper ---
    $element['year_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['date-ish-year-wrapper'],
      ],
    ];
    $element['year_wrapper']['year'] = [
      '#type' => 'number',
      '#title' => $this->t('Year'),
      '#default_value' => ($accuracy === 'year' && $existingYear) ? $existingYear : '',
      '#min' => 1,
      '#max' => 9999,
    ];

    // Attach the JS library for show/hide toggling.
    $element['#attached'] = [
      'library' => ['date_ish/date-ish-widget'],
    ];

    return $element;
  }

  /**
   * Validation callback for the date-ish fieldset.
   */
  public static function validateElement(array &$element, FormStateInterface $form_state, array &$form): void {
    $accuracy = $element['accuracy']['#value'] ?? '';

    if ($accuracy === '') {
      // No accuracy selected — nothing to validate.
      return;
    }

    // Check if all date sub-inputs are empty. If so, the user hasn't entered
    // anything — treat as an empty field value (skip validation so that
    // non-required fields can be left blank and field config forms work).
    $hasAnyInput = FALSE;
    $hasAnyInput = $hasAnyInput || !empty($element['exact_wrapper']['date']['#value'] ?? '');
    $hasAnyInput = $hasAnyInput || !empty($element['month_wrapper']['year']['#value'] ?? '');
    $hasAnyInput = $hasAnyInput || !empty($element['month_wrapper']['month']['#value'] ?? '');
    $hasAnyInput = $hasAnyInput || !empty($element['quarter_wrapper']['year']['#value'] ?? '');
    $hasAnyInput = $hasAnyInput || !empty($element['quarter_wrapper']['quarter']['#value'] ?? '');
    $hasAnyInput = $hasAnyInput || !empty($element['year_half_wrapper']['year']['#value'] ?? '');
    $hasAnyInput = $hasAnyInput || !empty($element['year_half_wrapper']['half']['#value'] ?? '');
    $hasAnyInput = $hasAnyInput || !empty($element['year_wrapper']['year']['#value'] ?? '');

    if (!$hasAnyInput) {
      // No date sub-inputs filled — nothing to validate.
      return;
    }

    switch ($accuracy) {
      case 'exact':
        $date = $element['exact_wrapper']['date']['#value'] ?? '';
        if ($date === '' || $date === NULL) {
          $form_state->setError($element, t('Date is required.'));
          return;
        }
        // Validate the calendar date.
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
          $form_state->setError($element, t('The date is not valid.'));
          return;
        }
        $y = (int) $parts[0];
        $m = (int) $parts[1];
        $d = (int) $parts[2];
        if (!checkdate($m, $d, $y)) {
          $form_state->setError($element, t('The date is not valid.'));
        }
        break;

      case 'month':
        $year = $element['month_wrapper']['year']['#value'] ?? '';
        $month = $element['month_wrapper']['month']['#value'] ?? '';
        if ($year === '' || $year === NULL || $month === '' || $month === NULL) {
          $form_state->setError($element, t('Date is required.'));
        }
        break;

      case 'quarter':
        $year = $element['quarter_wrapper']['year']['#value'] ?? '';
        $quarter = $element['quarter_wrapper']['quarter']['#value'] ?? '';
        if ($year === '' || $year === NULL || $quarter === '' || $quarter === NULL) {
          $form_state->setError($element, t('Date is required.'));
        }
        break;

      case 'year_half':
        $year = $element['year_half_wrapper']['year']['#value'] ?? '';
        $half = $element['year_half_wrapper']['half']['#value'] ?? '';
        if ($year === '' || $year === NULL || $half === '' || $half === NULL) {
          $form_state->setError($element, t('Date is required.'));
        }
        break;

      case 'year':
        $year = $element['year_wrapper']['year']['#value'] ?? '';
        if ($year === '' || $year === NULL) {
          $form_state->setError($element, t('Date is required.'));
        }
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $result = [];

    foreach ($values as $delta => $value) {
      $accuracy = $value['accuracy'] ?? '';

      if ($accuracy === '') {
        continue;
      }

      try {
        switch ($accuracy) {
          case 'exact':
            $date = $value['exact_wrapper']['date'] ?? '';
            if ($date === '' || $date === NULL) {
              continue 2;
            }
            $parts = explode('-', $date);
            $year = (int) $parts[0];
            $month = (int) $parts[1];
            $day = (int) $parts[2];
            $storedDate = DateIshHelper::computeStoredDate($accuracy, $year, $month, $day);
            break;

          case 'month':
            $year = (int) ($value['month_wrapper']['year'] ?? 0);
            $month = (int) ($value['month_wrapper']['month'] ?? 0);
            if ($year === 0 || $month === 0) {
              continue 2;
            }
            $storedDate = DateIshHelper::computeStoredDate($accuracy, $year, $month);
            break;

          case 'quarter':
            $year = (int) ($value['quarter_wrapper']['year'] ?? 0);
            $quarter = (int) ($value['quarter_wrapper']['quarter'] ?? 0);
            if ($year === 0 || $quarter === 0) {
              continue 2;
            }
            $storedDate = DateIshHelper::computeStoredDate($accuracy, $year, quarter: $quarter);
            break;

          case 'year_half':
            $year = (int) ($value['year_half_wrapper']['year'] ?? 0);
            $half = (int) ($value['year_half_wrapper']['half'] ?? 0);
            if ($year === 0 || $half === 0) {
              continue 2;
            }
            $storedDate = DateIshHelper::computeStoredDate($accuracy, $year, half: $half);
            break;

          case 'year':
            $year = (int) ($value['year_wrapper']['year'] ?? 0);
            if ($year === 0) {
              continue 2;
            }
            $storedDate = DateIshHelper::computeStoredDate($accuracy, $year);
            break;

          default:
            continue 2;
        }

        $result[$delta] = [
          'accuracy_level' => $accuracy,
          'stored_date' => $storedDate,
        ];
      }
      catch (\InvalidArgumentException) {
        // Invalid input — skip this delta; validation should have caught it.
        continue;
      }
    }

    return $result;
  }

  /**
   * Returns month options for the month select.
   *
   * @return array
   *   Associative array of month number => month name.
   */
  protected function getMonthOptions(): array {
    $months = [];
    for ($i = 1; $i <= 12; $i++) {
      $months[$i] = date('F', mktime(0, 0, 0, $i, 1));
    }
    return $months;
  }

}

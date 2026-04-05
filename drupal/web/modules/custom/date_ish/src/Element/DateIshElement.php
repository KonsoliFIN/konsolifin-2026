<?php

declare(strict_types=1);

namespace Drupal\date_ish\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\date_ish\DateIshHelper;

/**
 * Helper for building a date_ish form element group.
 *
 * Not a Drupal render element plugin — just a static helper that builds
 * a fieldset with the same sub-inputs as the date_ish field widget.
 *
 * Usage in a form's buildForm():
 * @code
 * $form['release_date'] = DateIshElement::build(t('Release date'));
 * @endcode
 *
 * In submitForm(), call DateIshElement::extractValue() to get the storage array:
 * @code
 * $date_ish = DateIshElement::extractValue($form_state->getValue('release_date'));
 * // Returns ['accuracy_level' => '...', 'stored_date' => '...'] or [].
 * @endcode
 */
class DateIshElement {

  /**
   * Builds a date_ish fieldset render array.
   */
  public static function build(string|\Stringable $title, array $default = []): array {
    $accuracy = $default['accuracy_level'] ?? 'exact';
    $storedDate = $default['stored_date'] ?? '';

    $existingYear = '';
    $existingMonth = '';
    $existingDate = '';
    $existingQuarter = '';
    $existingHalf = '';

    if ($storedDate !== '' && $storedDate !== NULL) {
      $date = \DateTimeImmutable::createFromFormat('Y-m-d', $storedDate);
      if ($date !== FALSE) {
        $existingYear = (int) $date->format('Y');
        $existingMonth = (int) $date->format('n');
        $existingDate = $storedDate;
        $existingQuarter = (int) ceil($existingMonth / 3);
        $existingHalf = $existingMonth <= 6 ? 1 : 2;
      }
    }

    $element = [
      '#type' => 'fieldset',
      '#title' => $title,
      '#tree' => TRUE,
      '#attached' => ['library' => ['date_ish/date-ish-widget']],
    ];

    $element['accuracy'] = [
      '#type' => 'select',
      '#title' => t('Accuracy'),
      '#options' => [
        'exact' => t('Exact date'),
        'month' => t('Month'),
        'quarter' => t('Quarter'),
        'year_half' => t('Year half'),
        'year' => t('Year'),
      ],
      '#default_value' => $accuracy,
      '#attributes' => ['class' => ['date-ish-accuracy']],
    ];

    $element['exact_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['date-ish-exact-wrapper']],
    ];
    $element['exact_wrapper']['date'] = [
      '#type' => 'date',
      '#title' => t('Date'),
      '#default_value' => $existingDate,
    ];

    $element['month_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['date-ish-month-wrapper']],
    ];
    $element['month_wrapper']['year'] = [
      '#type' => 'number',
      '#title' => t('Year'),
      '#default_value' => ($accuracy === 'month' && $existingYear) ? $existingYear : '',
      '#min' => 1, '#max' => 9999,
    ];
    $element['month_wrapper']['month'] = [
      '#type' => 'select',
      '#title' => t('Month'),
      '#options' => ['' => t('- Select -')] + static::getMonthOptions(),
      '#default_value' => ($accuracy === 'month' && $existingMonth) ? $existingMonth : '',
    ];

    $element['quarter_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['date-ish-quarter-wrapper']],
    ];
    $element['quarter_wrapper']['year'] = [
      '#type' => 'number',
      '#title' => t('Year'),
      '#default_value' => ($accuracy === 'quarter' && $existingYear) ? $existingYear : '',
      '#min' => 1, '#max' => 9999,
    ];
    $element['quarter_wrapper']['quarter'] = [
      '#type' => 'select',
      '#title' => t('Quarter'),
      '#options' => ['' => t('- Select -'), '1' => t('Q1'), '2' => t('Q2'), '3' => t('Q3'), '4' => t('Q4')],
      '#default_value' => ($accuracy === 'quarter' && $existingQuarter) ? $existingQuarter : '',
    ];

    $element['year_half_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['date-ish-year-half-wrapper']],
    ];
    $element['year_half_wrapper']['year'] = [
      '#type' => 'number',
      '#title' => t('Year'),
      '#default_value' => ($accuracy === 'year_half' && $existingYear) ? $existingYear : '',
      '#min' => 1, '#max' => 9999,
    ];
    $element['year_half_wrapper']['half'] = [
      '#type' => 'select',
      '#title' => t('Half'),
      '#options' => ['' => t('- Select -'), '1' => t('H1'), '2' => t('H2')],
      '#default_value' => ($accuracy === 'year_half' && $existingHalf) ? $existingHalf : '',
    ];

    $element['year_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['date-ish-year-wrapper']],
    ];
    $element['year_wrapper']['year'] = [
      '#type' => 'number',
      '#title' => t('Year'),
      '#default_value' => ($accuracy === 'year' && $existingYear) ? $existingYear : '',
      '#min' => 1, '#max' => 9999,
    ];

    return $element;
  }

  /**
   * Extracts the storage-format value from submitted form values.
   *
   * @param array|null $values
   *   The raw form values for the date_ish fieldset.
   *
   * @return array
   *   ['accuracy_level' => string, 'stored_date' => string] or [].
   */
  public static function extractValue(array|null $values): array {
    if (empty($values) || empty($values['accuracy'])) {
      return [];
    }

    $accuracy = $values['accuracy'];

    try {
      $storedDate = match ($accuracy) {
        'exact' => static::resolveExact($values),
        'month' => static::resolveMonth($values),
        'quarter' => static::resolveQuarter($values),
        'year_half' => static::resolveYearHalf($values),
        'year' => static::resolveYear($values),
        default => NULL,
      };
    }
    catch (\InvalidArgumentException) {
      return [];
    }

    if ($storedDate === NULL) {
      return [];
    }

    return ['accuracy_level' => $accuracy, 'stored_date' => $storedDate];
  }

  private static function resolveExact(array $v): ?string {
    $d = $v['exact_wrapper']['date'] ?? '';
    if ($d === '' || $d === NULL) return NULL;
    $p = explode('-', (string) $d);
    if (count($p) !== 3 || !checkdate((int) $p[1], (int) $p[2], (int) $p[0])) return NULL;
    return DateIshHelper::computeStoredDate('exact', (int) $p[0], (int) $p[1], (int) $p[2]);
  }

  private static function resolveMonth(array $v): ?string {
    $y = (int) ($v['month_wrapper']['year'] ?? 0);
    $m = (int) ($v['month_wrapper']['month'] ?? 0);
    return ($y && $m) ? DateIshHelper::computeStoredDate('month', $y, $m) : NULL;
  }

  private static function resolveQuarter(array $v): ?string {
    $y = (int) ($v['quarter_wrapper']['year'] ?? 0);
    $q = (int) ($v['quarter_wrapper']['quarter'] ?? 0);
    return ($y && $q) ? DateIshHelper::computeStoredDate('quarter', $y, quarter: $q) : NULL;
  }

  private static function resolveYearHalf(array $v): ?string {
    $y = (int) ($v['year_half_wrapper']['year'] ?? 0);
    $h = (int) ($v['year_half_wrapper']['half'] ?? 0);
    return ($y && $h) ? DateIshHelper::computeStoredDate('year_half', $y, half: $h) : NULL;
  }

  private static function resolveYear(array $v): ?string {
    $y = (int) ($v['year_wrapper']['year'] ?? 0);
    return $y ? DateIshHelper::computeStoredDate('year', $y) : NULL;
  }

  private static function getMonthOptions(): array {
    $months = [];
    for ($i = 1; $i <= 12; $i++) {
      $months[$i] = date('F', mktime(0, 0, 0, $i, 1));
    }
    return $months;
  }

}

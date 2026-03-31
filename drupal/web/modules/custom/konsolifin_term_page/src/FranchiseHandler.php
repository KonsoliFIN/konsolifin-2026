<?php

declare(strict_types=1);

namespace Drupal\konsolifin_term_page;

use Drupal\date_ish\DateIshHelper;
use Drupal\taxonomy\TermInterface;

/**
 * Vocabulary handler for the 'franchise' vocabulary.
 */
class FranchiseHandler implements VocabularyHandlerInterface {

  /**
   * Accuracy level sort order (lower index = sorted first).
   */
  private const ACCURACY_ORDER = [
    'exact' => 0,
    'month' => 1,
    'quarter' => 2,
    'year_half' => 3,
    'year' => 4,
  ];

  /**
   * {@inheritdoc}
   */
  public function getVocabularyId(): string {
    return 'franchise';
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, TermInterface $term): void {
    $franchiseTid = (int) $term->id();

    // Set franchise description (rendered value or null if empty).
    $descriptionField = $term->get('description');
    $descriptionValue = $descriptionField->getValue();
    if (!empty($descriptionValue[0]['value'])) {
      $variables['franchise_description'] = check_markup(
        $descriptionValue[0]['value'],
        $descriptionValue[0]['format'] ?? 'plain_text'
      );
    }
    else {
      $variables['franchise_description'] = NULL;
    }

    // Load and build the games list.
    $gameTerms = $this->loadGames($franchiseTid);
    $games = $this->buildGamesList($gameTerms);
    $this->sortGames($games);

    $variables['franchise_games'] = $games;
    $variables['franchise_no_games_message'] = t('No games are associated with this franchise.');
  }

  /**
   * Loads published peli terms referencing the given franchise term.
   *
   * @param int $franchiseTid
   *   The franchise term ID.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Array of loaded peli term entities.
   */
  public function loadGames(int $franchiseTid): array {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $tids = $storage->getQuery()
      ->condition('vid', 'peli')
      ->condition('field_kuuluu_pelisarjaan', $franchiseTid)
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($tids)) {
      return [];
    }

    return $storage->loadMultiple($tids);
  }

  /**
   * Builds the structured games list from loaded peli term entities.
   *
   * @param \Drupal\taxonomy\TermInterface[] $gameTerms
   *   Array of loaded peli term entities.
   *
   * @return array
   *   Array of game entries, each with keys: name, url, date_display, stored_date.
   */
  public function buildGamesList(array $gameTerms): array {
    $games = [];

    foreach ($gameTerms as $term) {
      $name = $term->getName();
      $url = $term->toUrl()->toString();

      $dateField = $term->get('field_julkaisu_pvm');
      $dateValues = $dateField->getValue();

      if (!empty($dateValues[0]['accuracy_level']) && !empty($dateValues[0]['stored_date'])) {
        $accuracy = $dateValues[0]['accuracy_level'];
        $storedDate = $dateValues[0]['stored_date'];
        $dateDisplay = DateIshHelper::formatForDisplay($accuracy, $storedDate);
      }
      else {
        $accuracy = NULL;
        $storedDate = NULL;
        $dateDisplay = '';
      }

      $games[] = [
        'name' => $name,
        'url' => $url,
        'date_display' => $dateDisplay,
        'stored_date' => $storedDate,
        'accuracy_level' => $accuracy,
      ];
    }

    return $games;
  }

  /**
   * Sorts the games list in place.
   *
   * Sort order:
   * 1. Dated entries before dateless entries.
   * 2. Dated entries ordered by stored_date ascending.
   * 3. Ties broken by accuracy level: exact < month < quarter < year_half < year.
   *
   * @param array $games
   *   The games list to sort (by reference).
   */
  public function sortGames(array &$games): void {
    usort($games, function (array $a, array $b): int {
      $aHasDate = $a['stored_date'] !== NULL;
      $bHasDate = $b['stored_date'] !== NULL;

      // Dated entries come before dateless entries.
      if ($aHasDate !== $bHasDate) {
        return $aHasDate ? -1 : 1;
      }

      // Both dateless: equal ordering.
      if (!$aHasDate) {
        return 0;
      }

      // Both dated: sort by stored_date ascending.
      $dateCmp = strcmp($a['stored_date'], $b['stored_date']);
      if ($dateCmp !== 0) {
        return $dateCmp;
      }

      // Same stored_date: sort by accuracy level.
      $aOrder = self::ACCURACY_ORDER[$a['accuracy_level']] ?? 99;
      $bOrder = self::ACCURACY_ORDER[$b['accuracy_level']] ?? 99;

      return $aOrder <=> $bOrder;
    });
  }

}

<?php

declare(strict_types=1);

namespace Drupal\konsolifin_term_page;

use Drupal\date_ish\DateIshHelper;
use Drupal\taxonomy\TermInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Vocabulary handler for the 'peli' vocabulary.
 */
class PeliHandler implements VocabularyHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public function getVocabularyId(): string {
    return 'peli';
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, TermInterface $term): void {
    $peliTid = (int) $term->id();

    // Load julkaisu nodes.
    $julkaisuNodes = $this->loadJulkaisut($peliTid);

    // Group and format.
    $julkaisut = $this->buildJulkaisutList($julkaisuNodes);

    $variables['peli_julkaisut'] = $julkaisut;
    $variables['peli_no_julkaisut_message'] = t('No releases are associated with this game.');

    // Invalidate this page when any julkaisu node changes.
    $variables['#cache']['tags'][] = 'node_list:julkaisu';
    $variables['#cache']['contexts'][] = 'user.permissions';
    if (\Drupal::currentUser()->hasPermission('create julkaisu content')) {
      $variables['peli_add_julkaisu_url'] = Url::fromRoute('konsolifin_term_page.add_julkaisu_form', ['taxonomy_term' => $peliTid])->toString();
      // Attach the dialog AJAX library to the content render array so it
      // bubbles through the render pipeline and actually loads on the page.
      $variables['content']['#attached']['library'][] = 'core/drupal.dialog.ajax';
    }
  }

  /**
   * Loads published julkaisu nodes referencing the given peli term.
   *
   * @param int $peliTid
   *   The peli term ID.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of loaded julkaisu node entities.
   */
  public function loadJulkaisut(int $peliTid): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');

    $nids = $storage->getQuery()
      ->condition('type', 'julkaisu')
      ->condition('field_pelit', $peliTid)
      ->condition('status', NodeInterface::PUBLISHED)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Builds the structured julkaisut list grouped by tyyppi.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   Array of loaded julkaisu node entities.
   *
   * @return array
   *   Array of julkaisut groups.
   */
  public function buildJulkaisutList(array $nodes): array {
    $groups = [];

    $tyyppi_labels = [
      'early_access' => 'Early access',
      'ensijulkaisu' => 'Ensijulkaisu',
      'remaster' => 'Remaster',
      'remake' => 'Remake',
      'dlc' => 'DLC',
      'bundle' => 'Bundle',
    ];

    foreach ($nodes as $node) {
      $tyyppi_field = $node->get('field_tyyppi')->value;
      $tyyppi_key = $tyyppi_field ?: 'muu';
      $tyyppi_label = $tyyppi_labels[$tyyppi_key] ?? ucfirst($tyyppi_key);

      // Get platforms.
      $platforms = [];
      foreach ($node->get('field_alustat') as $alusta_item) {
        if ($alusta_item->entity) {
          $platforms[] = $alusta_item->entity->getName();
        }
      }

      // Get release date.
      $dateField = $node->get('field_julkaisuajankohta');
      $dateValues = $dateField->getValue();

      if (!empty($dateValues[0]['accuracy_level']) && !empty($dateValues[0]['stored_date'])) {
        $accuracy = $dateValues[0]['accuracy_level'];
        $storedDate = $dateValues[0]['stored_date'];
        $dateDisplay = DateIshHelper::formatForDisplay($accuracy, $storedDate);
      }
      else {
        $dateDisplay = '';
      }

      $groups[$tyyppi_key]['label'] = $tyyppi_label;
      $groups[$tyyppi_key]['items'][] = [
        'title' => $node->getTitle(),
        'url' => $node->toUrl()->toString(),
        'platforms' => $platforms,
        'date_display' => $dateDisplay,
      ];
    }

    // Sort groups. Ensijulkaisu first.
    $sorted_groups = [];
    if (isset($groups['ensijulkaisu'])) {
      $sorted_groups['ensijulkaisu'] = $groups['ensijulkaisu'];
      unset($groups['ensijulkaisu']);
    }

    // Sort rest of the groups by key alphabetically or predefined order.
    $order = ['early_access', 'remaster', 'remake', 'dlc', 'bundle', 'muu'];
    foreach ($order as $key) {
      if (isset($groups[$key])) {
        $sorted_groups[$key] = $groups[$key];
        unset($groups[$key]);
      }
    }

    // Add any remaining
    foreach ($groups as $key => $group) {
      $sorted_groups[$key] = $group;
    }

    return $sorted_groups;
  }
}

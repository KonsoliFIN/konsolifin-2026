<?php

declare(strict_types=1);

namespace Drupal\konsolifin_term_page\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\date_ish\DateIshHelper;
use Drupal\konsolifin_term_page\MatomoService;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the /pelit games listing page.
 */
class GamesPageController extends ControllerBase {

  /**
   * Constructs a GamesPageController object.
   *
   * @param \Drupal\konsolifin_term_page\MatomoService $matomoService
   *   The Matomo analytics service.
   */
  public function __construct(
    #[Autowire(service: 'konsolifin_term_page.matomo_service')]
    protected readonly MatomoService $matomoService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('konsolifin_term_page.matomo_service'),
    );
  }

  /**
   * Builds the games page render array.
   *
   * @return array
   *   Render array with #theme => 'games_page'.
   */
  public function build(): array {
    $topGames = $this->buildTopGames();
    $upcomingReleases = $this->buildUpcomingReleases();
    $mostDiscussed = $this->matomoService->getMostDiscussedGames();
    $searchForm = $this->formBuilder()->getForm('Drupal\konsolifin_term_page\Form\GamesPageSearchForm');

    // Determine if there was a Matomo error (empty result likely means error).
    $mostDiscussedError = NULL;
    if (empty($mostDiscussed)) {
      $config = $this->config('konsolifin_term_page.games_page_settings');
      $apiUrl = $config->get('matomo_api_url');
      $authToken = $config->get('matomo_auth_token');
      if (!empty($apiUrl) && !empty($authToken)) {
        $mostDiscussedError = $this->t('Most discussed games data is temporarily unavailable.');
      }
    }

    return [
      '#theme' => 'games_page',
      '#top_games' => $topGames,
      '#search_form' => $searchForm,
      '#upcoming_releases' => $upcomingReleases,
      '#most_discussed' => $mostDiscussed,
      '#most_discussed_error' => $mostDiscussedError,
      '#cache' => [
        'tags' => [
          'taxonomy_term_list:peli',
          'node_list:julkaisu',
          'config:konsolifin_term_page.games_page_settings',
        ],
        'max-age' => 3600,
        'contexts' => [],
      ],
    ];
  }

  /**
   * Loads the top games from config, returns structured data.
   *
   * Reads top_game_1, top_game_2, top_game_3 from config, loads each term,
   * filters to published peli terms, and returns an array of entries with
   * name, url, and hero_image.
   *
   * @return array
   *   Array of top game entries, each with 'name', 'url', 'hero_image'.
   *   Maximum 3 entries.
   */
  public function buildTopGames(): array {
    $config = $this->config('konsolifin_term_page.games_page_settings');
    $termStorage = $this->entityTypeManager()->getStorage('taxonomy_term');

    $topGames = [];
    foreach (['top_game_1', 'top_game_2', 'top_game_3'] as $configKey) {
      $tid = $config->get($configKey);
      if (empty($tid)) {
        continue;
      }

      $term = $termStorage->load($tid);
      if (!$term) {
        continue;
      }

      // Must be a published peli term.
      if ($term->bundle() !== 'peli' || !$term->isPublished()) {
        continue;
      }

      // Build hero image render array.
      $heroImage = [];
      if ($term->hasField('field_hero_kuva') && !$term->get('field_hero_kuva')->isEmpty()) {
        $mediaEntity = $term->get('field_hero_kuva')->entity;
        if ($mediaEntity) {
          $heroImage = $this->entityTypeManager()
            ->getViewBuilder('media')
            ->view($mediaEntity);
        }
      }

      $topGames[] = [
        'name' => $term->getName(),
        'url' => $term->toUrl()->toString(),
        'hero_image' => $heroImage,
      ];
    }

    return $topGames;
  }

  /**
   * Queries upcoming julkaisu nodes and returns structured data.
   *
   * Finds published julkaisu nodes with stored_date >= today, sorted by
   * stored_date ascending, limited to 10 results.
   *
   * @return array
   *   Array of upcoming release entries, each with 'title', 'url',
   *   'date_display'. Sorted by stored_date ascending.
   */
  public function buildUpcomingReleases(): array {
    $nodeStorage = $this->entityTypeManager()->getStorage('node');
    $today = date('Y-m-d');

    $nids = $nodeStorage->getQuery()
      ->condition('type', 'julkaisu')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_julkaisuajankohta.stored_date', $today, '>=')
      ->sort('field_julkaisuajankohta.stored_date', 'ASC')
      ->range(0, 10)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($nids)) {
      return [];
    }

    $nodes = $nodeStorage->loadMultiple($nids);
    $releases = [];

    foreach ($nodes as $node) {
      $dateField = $node->get('field_julkaisuajankohta');
      $dateValues = $dateField->getValue();

      $dateDisplay = '';
      if (!empty($dateValues[0]['accuracy_level']) && !empty($dateValues[0]['stored_date'])) {
        $dateDisplay = DateIshHelper::formatForDisplay(
          $dateValues[0]['accuracy_level'],
          $dateValues[0]['stored_date'],
        );
      }

      $releases[] = [
        'title' => $node->getTitle(),
        'url' => $node->toUrl()->toString(),
        'date_display' => $dateDisplay,
      ];
    }

    return $releases;
  }

}

<?php

declare (strict_types = 1);

namespace Drupal\konsolifin_term_page\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
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
   * @param \Drupal\Core\Session\AccountInterface|null $currentUser
   *   The current user account.
   */
  public function __construct(
    #[Autowire(service: 'konsolifin_term_page.matomo_service')]
    protected readonly MatomoService $matomoService,
    ?AccountInterface $currentUser = NULL,
  ) {
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('konsolifin_term_page.matomo_service'),
      $container->get('current_user'),
    );
  }

  /**
   * Builds the games page render array.
   *
   * @return array
   *   Render array with #theme => 'games_page'.
   */
  public function build(): array {
    $topGames         = $this->buildTopGames();
    $upcomingReleases = $this->buildUpcomingReleases();
    $mostDiscussed    = $this->matomoService->getMostDiscussedGames();
    $searchForm       = $this->formBuilder()->getForm('Drupal\konsolifin_term_page\Form\GamesPageSearchForm');

    // Determine if there was a Matomo error (empty result likely means error).
    $mostDiscussedError = NULL;
    if (empty($mostDiscussed)) {
      $config    = $this->config('konsolifin_term_page.games_page_settings');
      $apiUrl    = $config->get('matomo_api_url');
      $authToken = $config->get('matomo_auth_token');
      if (! empty($apiUrl) && ! empty($authToken)) {
        $mostDiscussedError = $this->t('Most discussed games data is temporarily unavailable.');
      }
    }

    $adminUrl = NULL;
    if ($this->currentUser()->hasPermission('administer konsolifin games page')) {
      $adminUrl = Url::fromRoute('konsolifin_term_page.games_page_settings')->toString();
    }

    $upcomingReleasesUrl = Url::fromRoute('konsolifin_term_page.upcoming_releases')->toString();

    return [
      '#theme'                 => 'games_page',
      '#admin_url'             => $adminUrl,
      '#upcoming_releases_url' => $upcomingReleasesUrl,
      '#top_games'             => $topGames,
      '#top_games_heading'     => $this->t('Pinnalla juuri nyt'),
      '#search_form'           => $searchForm,
      '#upcoming_releases'     => $upcomingReleases,
      '#most_discussed'        => $mostDiscussed,
      '#most_discussed_error'  => $mostDiscussedError,
      '#cache'                 => [
        'tags'     => [
          'taxonomy_term_list:peli',
          'node_list:julkaisu',
          'config:konsolifin_term_page.games_page_settings',
        ],
        'max-age'  => 3600,
        'contexts' => ['user.permissions'],
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
    $config      = $this->config('konsolifin_term_page.games_page_settings');
    $termStorage = $this->entityTypeManager()->getStorage('taxonomy_term');

    $topGames = [];
    foreach (['top_game_1', 'top_game_2', 'top_game_3'] as $configKey) {
      $tid = $config->get($configKey);
      if (empty($tid)) {
        continue;
      }

      $term = $termStorage->load($tid);
      if (! $term) {
        continue;
      }

      // Must be a published peli term.
      if ($term->bundle() !== 'peli' || ! $term->isPublished()) {
        continue;
      }

      // Build hero image URL using 'large' image style.
      $heroImage = [];
      if ($term->hasField('field_hero_kuva') && ! $term->get('field_hero_kuva')->isEmpty()) {
        $mediaEntity = $term->get('field_hero_kuva')->entity;
        if ($mediaEntity && $mediaEntity->hasField('field_media_image') && ! $mediaEntity->get('field_media_image')->isEmpty()) {
          $fileEntity = $mediaEntity->get('field_media_image')->entity;
          if ($fileEntity) {
            $imageStyle = $this->entityTypeManager()->getStorage('image_style')->load('large');
            if ($imageStyle) {
              $heroImage = [
                'url' => $imageStyle->buildUrl($fileEntity->getFileUri()),
                'alt' => $mediaEntity->get('field_media_image')->alt ?? '',
              ];
            }
          }
        }
      }

      $topGames[] = [
        'name'       => $term->getName(),
        'url'        => $term->toUrl()->toString(),
        'hero_image' => $heroImage,
      ];
    }

    return $topGames;
  }

  /**
   * Builds the full upcoming releases page render array.
   *
   * @return array
   *   Render array with #theme => 'upcoming_releases_page'.
   */
  public function buildUpcomingReleasesPage(): array {
    $releases = $this->buildUpcomingReleases(NULL);
    $backUrl  = Url::fromRoute('konsolifin_term_page.games_page')->toString();

    return [
      '#theme'    => 'upcoming_releases_page',
      '#releases' => $releases,
      '#back_url' => $backUrl,
      '#cache'    => [
        'tags'    => [
          'node_list:julkaisu',
          'taxonomy_term_list:peli',
        ],
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Queries upcoming julkaisu nodes and returns structured data.
   *
   * Finds published julkaisu nodes with stored_date >= today, sorted by
   * stored_date ascending.
   *
   * @param int|null $limit
   *   Optional limit on number of results. If NULL, returns all upcoming releases.
   *
   * @return array
   *   Array of upcoming release entries, each with 'title', 'url',
   *   'platforms', 'date_display', 'pelit', 'tyyppi', and 'hero_image'.
   *   Sorted by stored_date ascending.
   */
  public function buildUpcomingReleases(?int $limit = 10): array {
    $nodeStorage = $this->entityTypeManager()->getStorage('node');
    $today       = date('Y-m-d');

    $query = $nodeStorage->getQuery()
      ->condition('type', 'julkaisu')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('field_julkaisuajankohta.stored_date', $today, '>=')
      ->sort('field_julkaisuajankohta.stored_date', 'ASC')
      ->accessCheck(FALSE);

    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    $nids = $query->execute();

    if (empty($nids)) {
      return [];
    }

    $nodes    = $nodeStorage->loadMultiple($nids);
    $releases = [];

    foreach ($nodes as $node) {
      $dateField  = $node->get('field_julkaisuajankohta');
      $dateValues = $dateField->getValue();

      $dateDisplay = '';
      if (! empty($dateValues[0]['accuracy_level']) && ! empty($dateValues[0]['stored_date'])) {
        $dateDisplay = DateIshHelper::formatForDisplay(
          $dateValues[0]['accuracy_level'],
          $dateValues[0]['stored_date'],
        );
      }

      $peli_url = $node->toUrl()->toString();

      // Get games.
      $pelit = [];
      foreach ($node->get('field_pelit') as $peli_item) {
        if ($peli_item->entity) {
          $pelit[$peli_item->entity->id()] = [
            'name' => $peli_item->entity->getName(),
            'url'  => $peli_item->entity->toUrl()->toString(),
          ];
          $peli_url = $peli_item->entity->toUrl()->toString();
        }
      }

      // Get platforms.
      $platforms = [];
      foreach ($node->get('field_alustat') as $alusta_item) {
        if ($alusta_item->entity) {
          $platforms[] = $alusta_item->entity->getName();
        }
      }

      $tyyppi_value = $node->get('field_tyyppi')->value;
      $tyyppi_key   = $tyyppi_value ?: 'muu';
      $tyyppi_label = \Drupal\konsolifin_term_page\PeliHandler::TYYPPI_LABELS[$tyyppi_key] ?? ucfirst($tyyppi_key);

      $heroImage = $this->buildReleaseHeroThumbnail($node);

      $releases[] = [
        'title'        => $node->getTitle(),
        'url'          => sizeof($pelit) > 1 ? NULL : $peli_url,
        'platforms'    => $platforms,
        'date_display' => $dateDisplay,
        'pelit'        => $pelit,
        'tyyppi'       => $tyyppi_label,
        'hero_image'   => $heroImage,
      ];
    }

    return $releases;
  }

  /**
   * Builds a hero image thumbnail render array for a release node.
   *
   * Checks the node's field_hero, falls back to referenced peli field_hero_kuva,
   * and falls back to media ID 10 if available.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The julkaisu node.
   *
   * @return array|null
   *   Render array with #theme => 'image', or NULL if not found.
   */
  public function buildReleaseHeroThumbnail(NodeInterface $node): ?array {
    $media = NULL;

    // 1. Check julkaisu node field_hero.
    if ($node->hasField('field_hero') && ! $node->get('field_hero')->isEmpty()) {
      $media = $node->get('field_hero')->entity;
    }

    // 2. Check referenced peli terms for field_hero_kuva.
    if (! $media && $node->hasField('field_pelit')) {
      foreach ($node->get('field_pelit') as $peliItem) {
        if ($peliItem->entity && $peliItem->entity->hasField('field_hero_kuva') && ! $peliItem->entity->get('field_hero_kuva')->isEmpty()) {
          $media = $peliItem->entity->get('field_hero_kuva')->entity;
          break;
        }
      }
    }

    // 3. Fallback to media ID 10 if media storage is available.
    if (! $media && $this->entityTypeManager()->hasDefinition('media')) {
      $mediaStorage = $this->entityTypeManager()->getStorage('media');
      if ($mediaStorage) {
        $media = $mediaStorage->load(10);
      }
    }

    // Build image render array with 'game_hero_thumbnail' style.
    if ($media && $media->hasField('field_media_image') && ! $media->get('field_media_image')->isEmpty()) {
      $file = $media->get('field_media_image')->entity;
      if ($file && $this->entityTypeManager()->hasDefinition('image_style')) {
        $imageStyleStorage = $this->entityTypeManager()->getStorage('image_style');
        if ($imageStyleStorage) {
          $imageStyle = $imageStyleStorage->load('game_hero_thumbnail');
          if ($imageStyle) {
            return [
              '#theme' => 'image',
              '#uri'   => $imageStyle->buildUrl($file->getFileUri()),
              '#alt'   => $media->get('field_media_image')->alt ?? $node->getTitle(),
            ];
          }
        }
      }
    }

    return NULL;
  }

}

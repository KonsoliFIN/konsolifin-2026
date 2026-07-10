<?php

declare (strict_types = 1);

namespace Drupal\konsolifin_term_page;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;

/**
 * Service for fetching most discussed games from Matomo analytics.
 */
class MatomoService {

  /**
   * The logger channel.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs a MatomoService object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    protected readonly ClientInterface $httpClient,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('konsolifin_term_page');
  }

  /**
   * Fetches most discussed games from Matomo and maps to peli terms.
   *
   * @return array
   *   Array of game entries, each with 'name', 'url', 'view_count'.
   *   Ordered by view_count descending. Empty array on error.
   */
  public function getMostDiscussedGames(): array {
    try {
      $config    = $this->configFactory->get('konsolifin_term_page.games_page_settings');
      $apiUrl    = $config->get('matomo_api_url');
      $authToken = $config->get('matomo_auth_token');

      if (empty($apiUrl) || empty($authToken)) {
        $this->logger->warning('Matomo API URL or authentication token is not configured.');
        return [];
      }

      $threadViewCounts = $this->fetchThreadViewCounts($apiUrl, $authToken);
      if (empty($threadViewCounts)) {
        return [];
      }

      return $this->mapThreadsToGames($threadViewCounts);
    } catch (\Exception $e) {
      $this->logger->warning('Failed to fetch most discussed games from Matomo: @message', [
        '@message' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Queries the Matomo API for most viewed forum thread pages.
   *
   * @param string $apiUrl
   *   The Matomo API endpoint URL.
   * @param string $authToken
   *   The Matomo authentication token.
   *
   * @return array
   *   Array of [thread_id => view_count] pairs.
   */
  private function fetchThreadViewCounts(string $apiUrl, string $authToken): array {
    $response = $this->httpClient->request('POST', $apiUrl, [
      'form_params' => [
        'token_auth' => $authToken,
      ],
      'timeout'     => 10,
    ]);

    $body = (string) $response->getBody();

    $data = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);

    if (! is_array($data)) {
      return [];
    }

    $threadViewCounts = [];
    foreach ($data as $item) {
      if (isset($item['label'], $item['nb_visits'])) {
        // Extract thread ID from the label/URL.
        // Expected format contains the thread ID is at the end of the page path.
        if (preg_match('/\.(\d+)\/?$/', $item['label'], $matches)) {
          $threadId                    = $matches[1];
          $threadViewCounts[$threadId] = (int) $item['nb_visits'];
        }
      }
    }

    return $threadViewCounts;
  }

  /**
   * Maps thread IDs to published peli terms via field_forum_ketju.
   *
   * @param array $threadViewCounts
   *   Array of [thread_id => view_count].
   *
   * @return array
   *   Array of game entries with 'name', 'url', 'view_count'.
   *   Sorted by view_count descending.
   */
  private function mapThreadsToGames(array $threadViewCounts): array {
    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
    $threadIds   = array_keys($threadViewCounts);

    $tids = $termStorage->getQuery()
      ->condition('vid', 'peli')
      ->condition('field_forum_ketju', $threadIds, 'IN')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    if (empty($tids)) {
      return [];
    }

    $terms   = $termStorage->loadMultiple($tids);
    $results = [];

    // Load the fallback media entity and extract its file URI.
    $fallbackMedia = $this->entityTypeManager->getStorage('media')->load(10);
    $fallbackImage = null;
    if ($fallbackMedia && $fallbackMedia->hasField('field_media_image') && ! $fallbackMedia->get('field_media_image')->isEmpty()) {
      $fallbackFile = $fallbackMedia->get('field_media_image')->entity;
      if ($fallbackFile) {
        $imageStyle = $this->entityTypeManager->getStorage('image_style')->load('game_hero_thumbnail');
        if ($imageStyle) {
          $fallbackImage = [
            '#theme' => 'image',
            '#uri'   => $imageStyle->buildUrl($fallbackFile->getFileUri()),
            '#alt'   => "Pelin otsikkokuva ei saatavilla",
          ];
        }
      }
    }

    foreach ($terms as $term) {
      $heroImage     = null;
      $forumThreadId = $term->get('field_forum_ketju')->value;
      if ($forumThreadId && isset($threadViewCounts[$forumThreadId])) {
        $mediaEntity = $term->get('field_hero_kuva')->entity;
        if ($mediaEntity && $mediaEntity->hasField('field_media_image') && ! $mediaEntity->get('field_media_image')->isEmpty()) {
          $fileEntity = $mediaEntity->get('field_media_image')->entity;
          if ($fileEntity) {
            $imageStyle = $this->entityTypeManager->getStorage('image_style')->load('game_hero_thumbnail');
            if ($imageStyle) {
              $heroImage = [
                '#theme' => 'image',
                '#uri'   => $imageStyle->buildUrl($fileEntity->getFileUri()),
                '#alt'   => $mediaEntity->get('field_media_image')->alt ?? "Pelin otsikkokuva",
              ];
            }
          }
        }

        if (! $heroImage && $fallbackImage) { // Fallback to fallback image if no valid image was found
          $heroImage = $fallbackImage;
        }
      }

      $results[] = [
        'name'            => $term->getName(),
        'url'             => $term->toUrl()->toString(),
        'hero_image'      => $heroImage,
        'forum_thread_id' => $term->get('field_forum_ketju')->value,
        'view_count'      => $threadViewCounts[$forumThreadId],
      ];
    }

    // Sort by view_count descending.
    usort($results, function (array $a, array $b): int {
      return $b['view_count'] <=> $a['view_count'];
    });
    
    // Only return the first 10 results.
    $results = array_slice($results, 0, 10);

    return $results;
  }

}
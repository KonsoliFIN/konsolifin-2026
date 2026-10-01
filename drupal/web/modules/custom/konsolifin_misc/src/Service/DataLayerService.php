<?php

declare(strict_types=1);

namespace Drupal\konsolifin_misc\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\Markup;
use Drupal\node\NodeInterface;

/**
 * Service for generating Google Analytics / Tag Manager dataLayer metadata.
 */
class DataLayerService {

  /**
   * Constructs a DataLayerService object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(
    protected DateFormatterInterface $dateFormatter,
  ) {
  }

  /**
   * Builds the data array for the dataLayer.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array<string, string>
   *   The dataLayer data array.
   */
  public function buildDataLayerData(NodeInterface $node): array {
    return [
      'platform' => 'drupal',
      'site_section' => $this->getSiteSection($node),
      'content_type' => $node->bundle(),
      'content_id' => (string) $node->id(),
      'author' => $this->getAuthorName($node),
      'game_title' => $this->getGameTitle($node),
      'publication_date' => $this->getPublicationDate($node),
    ];
  }

  /**
   * Builds the JavaScript snippet for the dataLayer.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   The JavaScript snippet.
   */
  public function buildDataLayerSnippet(NodeInterface $node): string {
    $data = $this->buildDataLayerData($node);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Escape closing tags and comments to prevent HTML parser breaking out of script.
    $json = str_replace(
      ['</', '<!--'],
      ['<\/', '<\!--'],
      $json
    );

    return "window.dataLayer = window.dataLayer || [];\nwindow.dataLayer.push(" . $json . ");";
  }

  /**
   * Attaches the dataLayer script to page attachments and merges cache tags.
   *
   * @param array $attachments
   *   The attachments array.
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   */
  public function attachDataLayer(array &$attachments, NodeInterface $node): void {
    $snippet = $this->buildDataLayerSnippet($node);

    $attachments['#attached']['html_head'][] = [
      [
        '#tag' => 'script',
        '#value' => Markup::create($snippet),
        '#weight' => -100,
      ],
      'konsolifin_misc_datalayer',
    ];

    $cache_tags = $node->getCacheTags();
    $author = $node->getOwner();
    if ($author) {
      $cache_tags = Cache::mergeTags($cache_tags, $author->getCacheTags());
    }
    if ($node->hasField('field_pelit') && !$node->get('field_pelit')->isEmpty()) {
      foreach ($node->get('field_pelit') as $item) {
        if ($item->entity) {
          $cache_tags = Cache::mergeTags($cache_tags, $item->entity->getCacheTags());
        }
      }
    }

    $attachments['#cache']['tags'] = Cache::mergeTags(
      $attachments['#cache']['tags'] ?? [],
      $cache_tags
    );
  }

  /**
   * Gets the site section for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   The site section.
   */
  public function getSiteSection(NodeInterface $node): string {
    return 'editorial';
  }

  /**
   * Gets the author's name for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   The author name.
   */
  public function getAuthorName(NodeInterface $node): string {
    $author = $node->getOwner();
    if (!$author) {
      return '';
    }

    if (isset($author->realname) && !empty($author->realname)) {
      return (string) $author->realname;
    }

    if (method_exists($author, 'getDisplayName')) {
      $name = (string) $author->getDisplayName();
      if ($name !== '') {
        return $name;
      }
    }

    return (string) $author->label();
  }

  /**
   * Gets the game title associated with a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   The game title.
   */
  public function getGameTitle(NodeInterface $node): string {
    $games = [];

    if ($node->hasField('field_pelit') && !$node->get('field_pelit')->isEmpty()) {
      foreach ($node->get('field_pelit') as $item) {
        if ($item->entity) {
          $label = trim((string) $item->entity->label());
          if ($label !== '') {
            $games[] = $label;
          }
        }
      }
    }

    $games = array_unique($games);
    $game_title = implode(', ', $games);

    if ($node->hasField('field_pelin_nimi') && !$node->get('field_pelin_nimi')->isEmpty()) {
      $pelin_nimi = trim((string) $node->get('field_pelin_nimi')->value);
      if ($pelin_nimi !== '') {
        $game_title = $game_title !== '' ? $game_title . ' ' . $pelin_nimi : $pelin_nimi;
      }
    }

    return $game_title;
  }

  /**
   * Gets the publication date formatted as YYYY-MM-DD.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   The formatted publication date.
   */
  public function getPublicationDate(NodeInterface $node): string {
    $created_time = (int) $node->getCreatedTime();
    if ($created_time <= 0) {
      return '';
    }

    return $this->dateFormatter->format($created_time, 'custom', 'Y-m-d');
  }

}

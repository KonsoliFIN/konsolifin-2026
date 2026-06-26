<?php

namespace Drupal\konsolifin_misc\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds RSS 2.0 feeds for KonsoliFIN content.
 */
class RssFeedService {

  /**
   * Feed mode: include only text before first double newline.
   */
  public const MODE_FIRST_PARAGRAPH = 'first_paragraph';

  /**
   * Feed mode: include full processed HTML body.
   */
  public const MODE_FULL_BODY = 'full_body';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AliasManagerInterface $aliasManager,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Builds the RSS feed and returns an HTTP Response.
   *
   * @param string $mode
   *   One of self::MODE_FIRST_PARAGRAPH or self::MODE_FULL_BODY.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function buildFeed(string $mode): Response {
    $request = $this->requestStack->getCurrentRequest();
    $baseUrl = $request->getSchemeAndHttpHost();

    // 1. Query published + promoted nodes, sorted by created DESC, limit 25.
    $nodes = $this->queryNodes();

    // 2. Build channel XML header.
    $xml = $this->buildChannelXml($baseUrl);

    // 3. Iterate nodes and build item XML.
    foreach ($nodes as $node) {
      $xml .= $this->buildItemXml($node, $mode, $baseUrl);
    }

    // 4. Close channel and rss elements.
    $xml .= "</channel>\n</rss>";

    // 5. Return Response with correct Content-Type.
    return new Response($xml, 200, [
      'Content-Type' => 'application/rss+xml; charset=utf-8',
    ]);
  }

  /**
   * Generates the XML declaration and channel header.
   *
   * @param string $baseUrl
   *   The base URL of the site (scheme + host).
   *
   * @return string
   *   The XML string up to and including the channel opening elements.
   */
  protected function buildChannelXml(string $baseUrl): string {
    $request = $this->requestStack->getCurrentRequest();
    $selfUrl = $baseUrl . $request->getRequestUri();

    $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
    $xml .= "<channel>\n";
    $xml .= "<title>KonsoliFIN</title>\n";
    $xml .= "<link>" . $baseUrl . "</link>\n";
    $xml .= "<description>KonsoliFIN - Pair of Kings</description>\n";
    $xml .= "<language>fi</language>\n";
    $xml .= "<ttl>5</ttl>\n";
    $xml .= '<atom:link href="' . htmlspecialchars($selfUrl) . '" rel="self" type="application/rss+xml" />' . "\n";

    return $xml;
  }

  /**
   * Generates a single <item> XML element for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param string $mode
   *   One of self::MODE_FIRST_PARAGRAPH or self::MODE_FULL_BODY.
   * @param string $baseUrl
   *   The base URL of the site (scheme + host).
   *
   * @return string
   *   The XML string for one <item> element.
   */
  protected function buildItemXml(NodeInterface $node, string $mode, string $baseUrl): string {
    // Resolve path alias.
    $alias = $this->aliasManager->getAliasByPath('/node/' . $node->id());
    $link = $baseUrl . $alias . '?utm_medium=rss';
    $comments = $baseUrl . $alias . '?utm_medium=rss#comments';

    // Build title with game name and series prefix.
    $title = htmlspecialchars($this->buildItemTitle($node));

    // Build description.
    $description = htmlspecialchars($this->buildItemDescription($node, $mode));

    // Publication date in RFC 2822 format.
    $pubDate = gmdate('D, d M Y H:i:s O', $node->getCreatedTime());

    // Author.
    $creator = htmlspecialchars($node->getOwner()->getDisplayName());

    // GUID.
    $guid = $node->id() . ' at http://www.konsolifin.net';

    $xml = "<item>\n";
    $xml .= "<title>" . $title . "</title>\n";
    $xml .= "<link>" . $link . "</link>\n";
    $xml .= "<description>" . $description . "</description>\n";
    $xml .= "<pubDate>" . $pubDate . "</pubDate>\n";
    $xml .= "<dc:creator>" . $creator . "</dc:creator>\n";
    $xml .= '<guid isPermaLink="false">' . $guid . "</guid>\n";
    $xml .= "<comments>" . $comments . "</comments>\n";
    $xml .= "</item>\n";

    return $xml;
  }

  /**
   * Queries the 25 most recent published and promoted nodes.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of loaded node entities.
   */
  protected function queryNodes(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('status', 1)
      ->condition('promote', 1)
      ->sort('created', 'DESC')
      ->range(0, 25)
      ->accessCheck(TRUE)
      ->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Constructs the item title with game name suffix and series prefix.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   The constructed title.
   */
  protected function buildItemTitle(NodeInterface $node): string {
    $title = $node->label();

    // Append game name for reviews (peliarvostelu bundle).
    if ($node->bundle() === 'peliarvostelu') {
      $gameName = '';
      if ($node->hasField('field_pelin_nimi') && !$node->get('field_pelin_nimi')->isEmpty()
          && $node->get('field_pelin_nimi')->value !== '') {
        $gameName = $node->get('field_pelin_nimi')->value;
      }
      elseif ($node->hasField('field_pelit') && !$node->get('field_pelit')->isEmpty()) {
        $term = $node->get('field_pelit')->entity;
        $gameName = $term ? $term->label() : '';
      }
      if ($gameName !== '') {
        $title .= ' - Arvostelussa ' . $gameName;
      }
    }

    // Prepend series name.
    if ($node->hasField('field_sarja') && !$node->get('field_sarja')->isEmpty()) {
      $series = $node->get('field_sarja')->entity;
      if ($series) {
        $title = $series->label() . ': ' . $title;
      }
    }

    return $title;
  }

  /**
   * Gets the hero image URL for a node using the hero_banner image style.
   *
   * Traverses field_hero → media entity → source field → file entity → URI,
   * then generates a styled URL via the hero_banner image style.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string|null
   *   The image URL or NULL if no hero image is available.
   */
  protected function getHeroImageUrl(NodeInterface $node): ?string {
    if (!$node->hasField('field_hero') || $node->get('field_hero')->isEmpty()) {
      return NULL;
    }

    /** @var \Drupal\media\MediaInterface $media */
    $media = $node->get('field_hero')->entity;
    if (!$media) {
      return NULL;
    }

    // Get the source field (field_media_image for image media type).
    $source_field = $media->getSource()->getSourceFieldDefinition($media->get('bundle')->entity);
    $file = $media->get($source_field->getName())->entity;
    if (!$file) {
      return NULL;
    }

    $uri = $file->getFileUri();

    /** @var \Drupal\image\ImageStyleInterface|null $style */
    $style = $this->entityTypeManager->getStorage('image_style')->load('hero_banner');
    if ($style) {
      return $style->buildUrl($uri);
    }

    // Fallback: absolute URL to original file.
    return $this->fileUrlGenerator->generateAbsoluteString($uri);
  }

  /**
   * Builds the description for an RSS feed item.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param string $mode
   *   One of self::MODE_FIRST_PARAGRAPH or self::MODE_FULL_BODY.
   *
   * @return string
   *   The description markup.
   */
  protected function buildItemDescription(NodeInterface $node, string $mode): string {
    $description = '';

    // Hero image.
    $imageUrl = $this->getHeroImageUrl($node);
    if ($imageUrl) {
      $description .= '<img src="' . $imageUrl . '"><br />';
    }

    // Body content.
    $body = $node->get('body');
    if (!$body->isEmpty()) {
      if ($mode === self::MODE_FULL_BODY) {
        $description .= $body->processed;
      }
      else {
        // First paragraph: text before first double newline.
        $plainText = strip_tags($body->value);
        $parts = preg_split('/\n/', $plainText, 2);
        $description .= $parts[0] ?? '';
      }
    }

    return $description;
  }

}

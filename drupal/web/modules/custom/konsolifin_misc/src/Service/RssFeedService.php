<?php

namespace Drupal\konsolifin_misc\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
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
    protected CacheBackendInterface $cacheBackend,
    protected FileSystemInterface $fileSystem,
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
   * Builds an iTunes-compatible podcast RSS feed.
   *
   * Queries published podcast nodes and outputs an RSS 2.0 feed with
   * iTunes/podcast namespace extensions. Channel-level metadata is
   * configurable via the $metadata parameter.
   *
   * @param array $metadata
   *   Podcast channel metadata with keys:
   *   - title: (string) Podcast title.
   *   - description: (string) Podcast description.
   *   - author: (string) Author/owner name.
   *   - email: (string) Owner email for itunes:owner.
   *   - image: (string) Podcast artwork URL (1400x1400 – 3000x3000 recommended).
   *   - category: (string) iTunes category (e.g. "Leisure").
   *   - subcategory: (string|null) iTunes subcategory (e.g. "Video Games").
   *   - explicit: (string) "true", "false", or "clean". Defaults to "false".
   *   - language: (string) Language code. Defaults to "fi".
   *   - copyright: (string|null) Copyright notice.
   *   - limit: (int) Max episodes. Defaults to 50.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function buildPodcastFeed(array $metadata = []): Response {
    $request = $this->requestStack->getCurrentRequest();
    $baseUrl = $request->getSchemeAndHttpHost();

    // Defaults.
    $metadata += [
      'title' => 'KonsoliFIN Podcast',
      'description' => 'KonsoliFIN Podcast',
      'author' => 'KonsoliFIN',
      'email' => 'toimitus@konsolifin.net',
      'image' => $baseUrl . '/themes/custom/flavor/logo.png',
      'category' => 'Leisure',
      'subcategory' => 'Video Games',
      'explicit' => 'false',
      'language' => 'fi',
      'copyright' => '© KonsoliFIN',
      'limit' => 50,
    ];

    $nodes = $this->queryPodcastNodes((int) $metadata['limit']);

    $selfUrl = $baseUrl . $request->getRequestUri();

    // XML declaration and <rss> with iTunes namespace.
    $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
    $xml .= '<rss version="2.0"'
      . ' xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"'
      . ' xmlns:atom="http://www.w3.org/2005/Atom"'
      . ' xmlns:content="http://purl.org/rss/1.0/modules/content/"'
      . ' xmlns:podcast="https://podcastindex.org/namespace/1.0">' . "\n";

    // Channel.
    $xml .= "<channel>\n";
    $xml .= '<title>' . htmlspecialchars($metadata['title']) . "</title>\n";
    $xml .= '<link>' . $baseUrl . "</link>\n";
    $xml .= '<description>' . htmlspecialchars($metadata['description']) . "</description>\n";
    $xml .= '<language>' . htmlspecialchars($metadata['language']) . "</language>\n";
    $xml .= '<copyright>' . htmlspecialchars($metadata['copyright'] ?? '') . "</copyright>\n";
    $xml .= '<atom:link href="' . htmlspecialchars($selfUrl) . '" rel="self" type="application/rss+xml" />' . "\n";
    $xml .= '<itunes:author>' . htmlspecialchars($metadata['author']) . "</itunes:author>\n";
    $xml .= '<itunes:summary>' . htmlspecialchars($metadata['description']) . "</itunes:summary>\n";
    $xml .= '<itunes:explicit>' . htmlspecialchars($metadata['explicit']) . "</itunes:explicit>\n";
    $xml .= "<itunes:owner>\n";
    $xml .= '  <itunes:name>' . htmlspecialchars($metadata['author']) . "</itunes:name>\n";
    $xml .= '  <itunes:email>' . htmlspecialchars($metadata['email']) . "</itunes:email>\n";
    $xml .= "</itunes:owner>\n";
    $xml .= '<itunes:image href="' . htmlspecialchars($metadata['image']) . '" />' . "\n";

    // Category.
    if (!empty($metadata['subcategory'])) {
      $xml .= '<itunes:category text="' . htmlspecialchars($metadata['category']) . '">' . "\n";
      $xml .= '  <itunes:category text="' . htmlspecialchars($metadata['subcategory']) . '" />' . "\n";
      $xml .= "</itunes:category>\n";
    }
    else {
      $xml .= '<itunes:category text="' . htmlspecialchars($metadata['category']) . '" />' . "\n";
    }

    $xml .= '<itunes:type>episodic</itunes:type>' . "\n";

    // Episodes.
    foreach ($nodes as $node) {
      $xml .= $this->buildPodcastItemXml($node, $baseUrl);
    }

    $xml .= "</channel>\n</rss>";

    return new Response($xml, 200, [
      'Content-Type' => 'application/rss+xml; charset=utf-8',
    ]);
  }

  /**
   * Queries published podcast nodes.
   *
   * @param int $limit
   *   Maximum number of episodes to include.
   *
   * @return \Drupal\node\NodeInterface[]
   */
  protected function queryPodcastNodes(int $limit = 50): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->condition('status', 1)
      ->condition('type', 'podcast')
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->accessCheck(TRUE)
      ->execute();

    if (empty($nids)) {
      return [];
    }

    return $storage->loadMultiple($nids);
  }

  /**
   * Builds a single podcast <item> element with iTunes extensions.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The podcast node.
   * @param string $baseUrl
   *   The base URL.
   *
   * @return string
   */
  protected function buildPodcastItemXml(NodeInterface $node, string $baseUrl): string {
    $alias = $this->aliasManager->getAliasByPath('/node/' . $node->id());
    $link = $baseUrl . $alias;
    $title = $node->label();
    $pubDate = gmdate('D, d M Y H:i:s O', $node->getCreatedTime());

    // Episode description from body field.
    $description = '';
    if (!$node->get('body')->isEmpty()) {
      $description = $node->get('body')->processed;
    }

    // Audio enclosure from field_mp3 (media reference → audio file).
    $enclosureUrl = '';
    $enclosureLength = 0;
    $enclosureType = 'audio/mpeg';
    $durationFormatted = '';
    if ($node->hasField('field_mp3') && !$node->get('field_mp3')->isEmpty()) {
      $audioFile = $this->getAudioFileFromMedia($node);
      if ($audioFile) {
        $enclosureUrl = $this->fileUrlGenerator->generateAbsoluteString($audioFile->getFileUri());
        $enclosureLength = (int) $audioFile->getSize();
        $enclosureType = $audioFile->getMimeType() ?: 'audio/mpeg';
        $durationSeconds = $this->getAudioDuration($audioFile);
        if ($durationSeconds > 0) {
          $durationFormatted = $this->formatDuration($durationSeconds);
        }
      }
    }

    // Episode image from field_hero (falls back to nothing).
    $episodeImage = $this->getHeroImageUrl($node);

    $xml = "<item>\n";
    $xml .= '<title>' . htmlspecialchars($title) . "</title>\n";
    $xml .= '<link>' . htmlspecialchars($link) . "</link>\n";
    $xml .= '<guid isPermaLink="false">' . htmlspecialchars($enclosureUrl) . "</guid>\n";
    $xml .= '<pubDate>' . $pubDate . "</pubDate>\n";
    $xml .= '<description>' . strip_tags($description) . "</description>\n";
    $xml .= '<content:encoded><![CDATA[' . $description . "]]></content:encoded>\n";

    if ($enclosureUrl) {
      $xml .= '<enclosure url="' . htmlspecialchars($enclosureUrl) . '"'
        . ' length="' . $enclosureLength . '"'
        . ' type="' . htmlspecialchars($enclosureType) . '" />' . "\n";
    }

    // iTunes episode tags.
    $xml .= '<itunes:title>' . htmlspecialchars($title) . "</itunes:title>\n";
    $xml .= '<itunes:summary>' . htmlspecialchars(strip_tags($description)) . "</itunes:summary>\n";
    $xml .= '<itunes:explicit>false</itunes:explicit>' . "\n";
    $xml .= '<itunes:episodeType>full</itunes:episodeType>' . "\n";
    if ($durationFormatted !== '') {
      $xml .= '<itunes:duration>' . $durationFormatted . "</itunes:duration>\n";
    }

    if ($episodeImage) {
      $xml .= '<itunes:image href="' . htmlspecialchars($episodeImage) . '" />' . "\n";
    }

    // Author from node owner.
    $author = $node->getOwner()->getDisplayName();
    $xml .= '<itunes:author>' . htmlspecialchars($author) . "</itunes:author>\n";

    $xml .= "</item>\n";

    return $xml;
  }

  /**
   * Extracts the audio file entity from a podcast node's field_mp3 media ref.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The podcast node.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file entity or NULL.
   */
  protected function getAudioFileFromMedia(NodeInterface $node): ?\Drupal\file\FileInterface {
    /** @var \Drupal\media\MediaInterface|null $media */
    $media = $node->get('field_mp3')->entity;
    if (!$media) {
      return NULL;
    }

    // The audio media type uses field_media_audio_file as source field.
    $source_field = $media->getSource()->getSourceFieldDefinition($media->get('bundle')->entity);
    $file = $media->get($source_field->getName())->entity;

    return $file instanceof \Drupal\file\FileInterface ? $file : NULL;
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

  /**
   * Resolves the audio duration of an MP3 file, caching the result.
   *
   * @param \Drupal\file\FileInterface $audioFile
   *   The audio file entity.
   *
   * @return int
   *   The duration of the audio in seconds, or 0 if it cannot be determined.
   */
  protected function getAudioDuration(FileInterface $audioFile): int {
    $uri = $audioFile->getFileUri();
    $cid = 'konsolifin_misc:mp3_duration:' . $audioFile->id() . ':' . $audioFile->getChangedTime();

    if ($cache = $this->cacheBackend->get($cid)) {
      return (int) $cache->data;
    }

    $duration = 0;
    $filePath = $this->fileSystem->realpath($uri);
    if ($filePath && file_exists($filePath)) {
      try {
        $getID3 = new \getID3();
        $info = $getID3->analyze($filePath);
        if (!empty($info['playtime_seconds'])) {
          $duration = (int) round($info['playtime_seconds']);
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('konsolifin_misc')->error('Error parsing MP3 file @file: @message', [
          '@file' => $filePath,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    $this->cacheBackend->set($cid, $duration, CacheBackendInterface::CACHE_PERMANENT);

    return $duration;
  }

  /**
   * Formats the duration in seconds to iTunes-compatible format HH:MM:SS or MM:SS.
   *
   * @param int $seconds
   *   The duration in seconds.
   *
   * @return string
   *   Formatted duration.
   */
  protected function formatDuration(int $seconds): string {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds / 60) % 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
      return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%02d:%02d', $minutes, $secs);
  }

}

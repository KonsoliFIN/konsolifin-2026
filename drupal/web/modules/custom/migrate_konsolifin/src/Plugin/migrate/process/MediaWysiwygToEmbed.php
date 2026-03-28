<?php

declare(strict_types=1);

namespace Drupal\migrate_konsolifin\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Converts D7 media WYSIWYG JSON tokens to D11 drupal-media embed tags.
 *
 * D7 tokens look like: [[{"fid":"123","view_mode":"default","type":"media",...}]]
 * D11 embeds look like: <drupal-media data-entity-type="media" data-entity-uuid="..." data-view-mode="full"></drupal-media>
 *
 * Usage in migration YAML:
 * @code
 * process:
 *   body/value:
 *     -
 *       plugin: media_wysiwyg_to_embed
 *       source: body/0/value
 *       media_migration: konsolifin_media_images
 * @endcode
 *
 * Configuration:
 *   - media_migration: (required) The migration ID(s) used to look up the
 *     destination media entity from the source fid. Can be a string or array.
 *   - view_mode_map: (optional) Map of D7 view_mode values to D11 view modes.
 *     Defaults to: { default: full, media_large: full, media_original: full,
 *     teaser: full }
 */
#[MigrateProcess('media_wysiwyg_to_embed')]
class MediaWysiwygToEmbed extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Regex matching D7 media WYSIWYG JSON tokens.
   *
   * Matches [[{...}]] allowing nested braces via a recursive sub-pattern.
   * The (?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})* pattern handles up to 3 levels
   * of brace nesting, which covers all known D7 media token structures.
   */
  private const TOKEN_PATTERN = '/\[\[\s*(\{(?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})*\})\s*\]\]/s';

  /**
   * Default D7 → D11 view mode mapping.
   */
  private const DEFAULT_VIEW_MODE_MAP = [
    'default' => 'full',
    'media_large' => 'full',
    'media_original' => 'full',
    'teaser' => 'full',
  ];

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly MigrateLookupInterface $migrateLookup,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('migrate.lookup'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('migrate_konsolifin'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): mixed {
    if (!is_string($value) || $value === '') {
      return $value;
    }

    // Quick check: skip regex if no token present.
    if (!str_contains($value, '[[{')) {
      return $value;
    }

    $mediaMigrations = $this->configuration['media_migration'] ?? [];
    if (is_string($mediaMigrations)) {
      $mediaMigrations = [$mediaMigrations];
    }

    $viewModeMap = ($this->configuration['view_mode_map'] ?? []) + self::DEFAULT_VIEW_MODE_MAP;

    return preg_replace_callback(self::TOKEN_PATTERN, function (array $matches) use ($mediaMigrations, $viewModeMap) {
      return $this->convertToken($matches[1], $mediaMigrations, $viewModeMap);
    }, $value);
  }

  /**
   * Converts a single D7 media JSON token to a drupal-media tag.
   */
  private function convertToken(string $json, array $mediaMigrations, array $viewModeMap): string {
    $data = json_decode($json, TRUE);
    if (!is_array($data) || empty($data['fid']) || ($data['type'] ?? '') !== 'media') {
      // Not a media token or can't parse — return unchanged.
      return '[[' . $json . ']]';
    }

    $fid = (int) $data['fid'];
    $uuid = $this->lookupMediaUuid($fid, $mediaMigrations);

    if ($uuid === NULL) {
      $this->logger->warning('Media WYSIWYG token: could not find media entity for source fid @fid', ['@fid' => $fid]);
      return '[[' . $json . ']]';
    }

    $d7ViewMode = $data['view_mode'] ?? 'default';
    $viewMode = $viewModeMap[$d7ViewMode] ?? 'full';

    $attrs = [
      'data-entity-type' => 'media',
      'data-entity-uuid' => $uuid,
      'data-view-mode' => $viewMode,
    ];

    // Preserve alignment if present.
    if (!empty($data['attributes']['class'])) {
      $classes = $data['attributes']['class'];
      if (is_string($classes)) {
        $classes = explode(' ', $classes);
      }
      foreach ($classes as $class) {
        if (str_starts_with($class, 'media-wysiwyg-align-')) {
          $align = str_replace('media-wysiwyg-align-', '', $class);
          if (in_array($align, ['left', 'right', 'center'], TRUE)) {
            $attrs['data-align'] = $align;
          }
        }
      }
    }

    $attrString = '';
    foreach ($attrs as $name => $val) {
      $attrString .= ' ' . $name . '="' . htmlspecialchars($val, ENT_QUOTES) . '"';
    }

    return '<drupal-media' . $attrString . '></drupal-media>';
  }

  /**
   * Looks up the D11 media entity UUID from a D7 file fid.
   */
  private function lookupMediaUuid(int $fid, array $mediaMigrations): ?string {
    foreach ($mediaMigrations as $migration) {
      try {
        $lookup = $this->migrateLookup->lookup([$migration], [$fid]);
        if (!empty($lookup[0]['mid'])) {
          $media = $this->entityTypeManager->getStorage('media')->load($lookup[0]['mid']);
          if ($media) {
            return $media->uuid();
          }
        }
      }
      catch (\Exception $e) {
        // Try next migration.
      }
    }
    return NULL;
  }

}

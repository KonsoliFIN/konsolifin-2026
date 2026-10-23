<?php

declare(strict_types=1);

namespace Drupal\konsolifin_misc\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook implementations for konsolifin_misc.
 */
class KonsolifinMiscThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'konsolifin_two_week_calendar' => [
        'variables' => [
          'weeks' => [],
          'unscheduled_content' => [],
          'later_scheduled_content' => [],
          'overdue_scheduled_content' => [],
          'total_scheduled_count' => 0,
          'total_unscheduled_count' => 0,
          'current_time_formatted' => NULL,
          'timezone' => NULL,
        ],
        'template' => 'konsolifin-two-week-calendar',
      ],
      'konsolifin_create_content_links' => [
        'variables' => [
          'content_types' => [],
        ],
        'template' => 'konsolifin-create-content-links',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_HOOK() for node templates.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'];
    $author = $node->getOwner();

    // Load the author's profile picture URL.
    if ($author && $author->hasField('field_kuva') && !$author->get('field_kuva')->isEmpty() && $author->field_kuva->entity) {
      /** @var \Drupal\media\MediaInterface $media */
      $media = $author->field_kuva->entity;
      if ($media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
        $file = $media->get('field_media_image')->entity;
        if ($file) {
          $variables['author_headshot'] = [
            '#theme' => 'image_style',
            '#style_name' => 'teaser_thumbnail',
            '#uri' => $file->getFileUri(),
            '#alt' => $author->getDisplayName(),
            '#cache' => [
              'tags' => array_merge($author->getCacheTags(), $media->getCacheTags()),
            ],
          ];
        }
      }
    }

    // Load the bio/intro.
    if ($author && $author->hasField('field_esittely')) {
      $variables['author_bio'] = $author->field_esittely->view('default');
      // Limit the bio for only the first paragraph.
      if (isset($variables['author_bio'][0]['#text'])) {
        $bio_text = $variables['author_bio'][0]['#text'];
        $paragraphs = explode("</p>", $bio_text);
        $variables['author_bio'][0]['#text'] = $paragraphs[0] . '</p>';
      } else {
        // If the field is empty, set it to an empty string to avoid rendering issues.
        $variables['author_bio'][0]['#text'] = '';
      }
    }

    // Load social links.
    if ($author && $author->hasField('field_some_linkit')) {
      $variables['author_social'] = $author->field_some_linkit->view('default');
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for username templates.
   */
  #[Hook('preprocess_username')]
  public function preprocessUsername(array &$variables): void {
    $account = $variables['account'];

    // Haetaan koko nimi Realname-moduulilta.
    if (isset($account->realname) && !empty($account->realname)) {
      $variables['name'] = $account->realname;
    } elseif (method_exists($account, 'getDisplayName')) {
      $variables['name'] = $account->getDisplayName();
    }

    // Estetään Drupalia lyhentämään nimeä.
    $variables['truncated'] = FALSE;
  }

}

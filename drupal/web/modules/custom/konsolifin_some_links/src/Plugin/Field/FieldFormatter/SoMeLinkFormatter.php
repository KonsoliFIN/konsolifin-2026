<?php

namespace Drupal\konsolifin_some_links\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'konsolifin_some_link_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "konsolifin_some_link_formatter",
 *   label = @Translation("SoMe Link with Icon"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class SoMeLinkFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    $module_path = \Drupal::service('extension.list.module')->getPath('konsolifin_some_links');
    $base_path = \Drupal::request()->getBasePath();

    foreach ($items as $delta => $item) {
      $url = $item->getUrl();
      if (!$url || !$url->isRouted() && !$url->isExternal()) {
         continue;
      }

      $uri = $url->getUri();
      $profile_id = '';
      $icon_filename = '';

      if (preg_match('/^(?:https?:\/\/)?(?:www\.)?instagram\.com\/([a-zA-Z0-9_\.]+)\/?/i', $uri, $matches)) {
        $profile_id = '@' . $matches[1];
        $icon_filename = 'instagram.png';
      }
      elseif (preg_match('/^(?:https?:\/\/)?(?:www\.)?threads\.(?:net|com)\/@?([a-zA-Z0-9_\.]+)\/?/i', $uri, $matches)) {
        $profile_id = '@' . $matches[1];
        $icon_filename = 'threads.png';
      }
      elseif (preg_match('/^(?:https?:\/\/)?(?:www\.)?bsky\.app\/profile\/([a-zA-Z0-9_\.-]+)\/?/i', $uri, $matches)) {
        $profile_id = $matches[1];
        $icon_filename = 'bluesky.png';
      }
      elseif (preg_match('/^(?:https?:\/\/)?(?:www\.)?linkedin\.com\/in\/([a-zA-Z0-9_-]+)\/?/i', $uri, $matches)) {
        $profile_id = $matches[1];
        $icon_filename = 'linkedin.png';
      }

      if ($profile_id) {
        // Build the icon path using base_path()
        $icon_path = $base_path . '/' . $module_path . '/images/' . $icon_filename;
        $icon_markup = '<img src="' . $icon_path . '" alt="icon" class="some-link-icon" />';
        
        $elements[$delta]['#title'] = [
          '#markup' => $icon_markup . $profile_id,
          '#allowed_tags' => ['img', 'span'],
        ];
      }
      else {
        // If no match, use the default link title
        $elements[$delta]['#title'] = "Virheellinen SoMe-linkki: ".$uri;
      }
    }

    $elements['#attached']['library'][] = 'konsolifin_some_links/konsolifin_some_links.formatter';

    return $elements;
  }

}

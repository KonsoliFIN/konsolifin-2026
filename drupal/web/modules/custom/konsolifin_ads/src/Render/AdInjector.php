<?php

namespace Drupal\konsolifin_ads\Render;

/**
 * Helper class for injecting ads into rendered content.
 */
class AdInjector {

  /**
   * Post-render callback to inject ad after 3rd paragraph.
   */
  public static function postRenderNodeBody($html, array $elements) {
    if (empty(trim($html))) {
      return $html;
    }

    $unique_suffix = substr(md5(uniqid(mt_rand(), TRUE)), 0, 8);
    $ad_render_array = [
      '#theme' => 'konsolifin_ad',
      '#base_id' => 'konsolifin_content',
      '#unique_suffix' => $unique_suffix,
    ];
    $ad_html = \Drupal::service('renderer')->renderPlain($ad_render_array);

    // Split html by paragraph boundaries
    $paragraphs = preg_split('#(</p>\s*)#i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

    // preg_split with PREG_SPLIT_DELIM_CAPTURE keeps the delimiter as a separate element.
    // E.g., [ "para1", "</p>\n", "para2", "</p>\n", "para3", "</p>\n", "para4" ]

    $p_count = 0;
    $injected = FALSE;
    $output = '';

    // If there are no </p> tags, just append.
    if (count($paragraphs) <= 1) {
      return $html . $ad_html;
    }

    foreach ($paragraphs as $index => $part) {
      $output .= $part;
      // If this part is a closing p tag, we count it as one paragraph processed.
      if (preg_match('#</p>#i', $part)) {
        $p_count++;
        if ($p_count === 3) {
          $output .= $ad_html;
          $injected = TRUE;
        }
      }
    }

    if (!$injected) {
      $output .= $ad_html;
    }

    return $output;
  }

}

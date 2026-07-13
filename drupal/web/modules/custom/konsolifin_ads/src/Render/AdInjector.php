<?php

namespace Drupal\konsolifin_ads\Render;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\konsolifin_ads\TwigExtension\AdExtension;

/**
 * Helper class for injecting ads into rendered content.
 */
class AdInjector implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['postRenderNodeBody', 'postRenderView'];
  }

  /**
   * Post-render callback to inject ad after 3rd paragraph.
   */
  public static function postRenderNodeBody($html, array $elements) {
    if (empty(trim($html))) {
      return $html;
    }

    $ad_extension = \Drupal::service('konsolifin_ads.twig_extension');
    $ad_render_array = $ad_extension->renderAd('content');
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

  /**
   * Post-render callback to inject ads after every 10th view row.
   */
  public static function postRenderView($html, array $elements) {
    if (empty(trim($html))) {
      return $html;
    }

    $pattern = '#<div\s+[^>]*class="[^"]*(views-row|frontpage-featured-card|frontpage-duo-card|frontpage-standard-card|frontpage-compact-card)[^"]*"#i';
    if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
      return $html;
    }

    $rows = [];
    foreach ($matches[0] as $match) {
      $start_pos = $match[1];
      $closing_pos = self::findClosingDiv($html, $start_pos + strlen($match[0]));
      if ($closing_pos !== FALSE) {
        $rows[] = [
          'start' => $start_pos,
          'end' => $closing_pos,
        ];
      }
    }

    $num_rows = count($rows);
    $output = $html;

    $ad_extension = \Drupal::service('konsolifin_ads.twig_extension');

    // Process from end to start to avoid shifting indices.
    for ($i = $num_rows - 1; $i >= 0; $i--) {
      if (($i + 1) % 10 === 0) {
        $ad_render_array = $ad_extension->renderAd('content');;
        $ad_html = \Drupal::service('renderer')->renderPlain($ad_render_array);

        // Insert ad html after the row
        $insert_pos = $rows[$i]['end'];
        $output = substr_replace($output, $ad_html, $insert_pos, 0);
      }
    }

    return $output;
  }

  /**
   * Helper to find the matching closing tag of a div.
   */
  private static function findClosingDiv($html, $start_pos) {
    $length = strlen($html);
    $depth = 1;
    $pos = $start_pos;
    while ($depth > 0 && $pos < $length) {
      $next_open = stripos($html, '<div', $pos);
      $next_close = stripos($html, '</div>', $pos);
      if ($next_close === FALSE) {
        break;
      }
      if ($next_open !== FALSE && $next_open < $next_close) {
        $depth++;
        $pos = $next_open + 4;
      } else {
        $depth--;
        $pos = $next_close + 6;
      }
    }
    return $depth === 0 ? $pos : FALSE;
  }

}

<?php


namespace Drupal\konsolifin_ads\TwigExtension;

use Drupal\Core\Render\Markup;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Custom Twig extension for rendering ads.
 */
class AdExtension extends AbstractExtension {

  /**
   * A counter to keep track of ad invocations.
   *
   * @var int
   */
  private static int $adCounter = 1;

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('konsolifin_ad', [$this, 'renderAd'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * Renders the ad template.
   *
   * @param string $base_id
   *   The base ID for the ad elements.
   *
   * @return array
   *   A render array.
   */
  public function renderAd($base_id) {
    // Write $base_id in a log
    \Drupal::logger('konsolifin_ads')->notice('renderAd called with base_id: ' . $base_id);

    if ($base_id ==='top') {
      $unique_suffix = "";
    } else {
      // We add an incrementing unique suffix so multiple calls on the same page don't clash.
      $unique_suffix = "_" . AdExtension::$adCounter++;
    }

    // Special handling for a campaign from July 17th through July 31st
    $now = \Drupal::time()->getCurrentTime();
    $start_date = strtotime('2026-07-13');
    $end_date = strtotime('2026-07-31');
    if ($now >= $start_date && $now <= $end_date) {
      $ad_id = '';
      if ($base_id === 'top') {
        $ad_id = 'gtao_primary';
        $image_url = '/sites/default/files/testdata/greedy_publisher_exec_scene.png';
        $destination_url = 'https://example.com/campaign-click';
        $alt_text = 'Campaign Banner';
      } else if ($base_id === 'content' && $unique_suffix === '_1') {
        $ad_id = 'gtao_secondary';
        $image_url = '/sites/default/files/testdata/greedy_publisher_exec_scene.png';
        $destination_url = 'https://example.com/campaign-click';
        $alt_text = 'Campaign Banner';
      }
      if ($ad_id !== '') {
        return [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['gta_online_banner'],
            'id' => $ad_id,
          ],
          'ad_link' => [
            '#type' => 'link',
            '#title' => [
              '#theme' => 'image',
              '#uri' => $image_url,
              '#alt' => $alt_text,
            ],
            '#url' => Url::fromUri($destination_url),
            '#options' => [
              'html' => TRUE,
            ],
          ],
          'analytics_script' => [
            '#type' => 'html_tag',
            '#tag' => 'script',
            '#attributes' => [
              'type' => 'text/javascript',
              'async' => TRUE,
            ],
            '#value' => Markup::create("
window._mtm = window._mtm || [];
window._mtm.push({
  'event':        'banner_view',
  'destination':  '".$ad_id."'
});
"),
          ],
        ];
      }
    }

    // In development environment, render a placeholder
    if (Settings::get('dev_environment', FALSE)) {
      return [
        '#type' => 'markup',
        '#markup' => '<div class="konsolifin_ad_wrapper">Ad Placeholder '.$base_id.$unique_suffix.'</div>',
      ];
    }

    return [
      '#theme' => 'konsolifin_ad',
      '#base_id' => $base_id,
      '#unique_suffix' => $unique_suffix,
    ];
  }

}

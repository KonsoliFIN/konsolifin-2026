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
    if ($base_id ==='top') {
      $unique_suffix = "";
    } else {
      // We add an incrementing unique suffix so multiple calls on the same page don't clash.
      $unique_suffix = "_" . AdExtension::$adCounter++;
    }

    // Dynamic image campaign handling
    $config = \Drupal::config('konsolifin_ads.settings');
    $now = \Drupal::time()->getCurrentTime();
    $start_date_str = $config->get('image_start_date');
    $end_date_str = $config->get('image_end_date');
    $start_date = $start_date_str ? strtotime($start_date_str) : 0;
    $end_date = $end_date_str ? strtotime($end_date_str) : 0;

    $destination_url = $config->get('destination_url');

    if ($start_date && $end_date && $now >= $start_date && $now <= $end_date && !empty($destination_url)) {
      $image_url_oversize = $config->get('image_url_oversize');
      $image_url_desktop = $config->get('image_url_desktop');
      $image_url_mobile = $config->get('image_url_mobile');
      $breakpoint_oversize = $config->get('breakpoint_oversize') ?: 1600;
      $breakpoint_desktop = $config->get('breakpoint_desktop') ?: 800;
      $alt_text = $config->get('alt_text') ?: '';

      $ad_id = '';
      if ($base_id === 'top' && !empty($image_url_oversize)) {
        $ad_id = 'campaign_primary';
        $picture_markup = Markup::create(sprintf(
          '<picture>' .
          '<source media="(max-width: %dpx)" srcset="%s">' .
          '<source media="(max-width: %dpx)" srcset="%s">' .
          '<img src="%s" alt="%s">' .
          '</picture>',
          $breakpoint_desktop,
          $image_url_mobile,
          $breakpoint_oversize,
          $image_url_desktop,
          $image_url_oversize,
          htmlspecialchars($alt_text, ENT_QUOTES, 'UTF-8')
        ));
      } else if ($base_id === 'content' && $unique_suffix === '_2' && !empty($image_url_desktop)) {
        $ad_id = 'campaign_secondary';
        $picture_markup = Markup::create(sprintf(
          '<picture>' .
          '<source media="(max-width: %dpx)" srcset="%s">' .
          '<img src="%s" alt="%s">' .
          '</picture>',
          $breakpoint_desktop,
          $image_url_mobile,
          $image_url_desktop,
          htmlspecialchars($alt_text, ENT_QUOTES, 'UTF-8')
        ));
      }
      if ($ad_id !== '') {
        return [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['gta_online_banner'],
            'id' => $ad_id . '_banner',
          ],
          'ad_link' => [
            '#type' => 'link',
            '#title' => [
              '#markup' => $picture_markup,
            ],
            '#url' => Url::fromUri($destination_url),
            '#options' => [
              'html' => TRUE,
            ],
            '#attributes' => [
              'id' => $ad_id,
              'data-track-content' => '',
              'data-content-name' => 'Image Banner',
              'data-content-piece' => $ad_id,
            ],
          ],
        ];
      }
    }

    // In development environment, render a placeholder
    if (Settings::get('dev_environment', FALSE)) {
      return [
        '#type' => 'markup',
        '#markup' => '<div class="konsolifin_ad_wrapper">
          <div class="konsolifin_ad_top_bar"></div>
          <div class="konsolifin-ad-container">Ad Placeholder '.$base_id.$unique_suffix.'</div>
          <div class="konsolifin_ad_bottom_bar"></div>
        </div>',
      ];
    }

    return [
      '#theme' => 'konsolifin_ad',
      '#base_id' => $base_id,
      '#unique_suffix' => $unique_suffix,
    ];
  }

}

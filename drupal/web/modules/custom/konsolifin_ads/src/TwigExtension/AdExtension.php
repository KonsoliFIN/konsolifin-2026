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
    $start_date = strtotime('2026-07-17');
    $end_date = strtotime('2026-07-31');
    if ($now >= $start_date && $now <= $end_date) {
      $destination_url = 'https://www.rockstargames.com/newswire/article/2525o93834o413/the-kortz-center-heist-now-available-in-gta-online?&utm_source=konsolfin&utm_medium=p_sitedisplay&utm_campaign=00:emea-endemic-20260714&utm_content=fi-eng';
      $image_url_oversize = 'https://www.konsolifin.net/sites/default/files/2026-07/GTAO_TKCH_LaunchPM_Multi_1920x1080_R01_fi_fi.jpg';
      $image_url_desktop = 'https://www.konsolifin.net/sites/default/files/2026-07/GTAO_TKCH_LaunchPM_Multi_970x250_R01_fi_fi.jpg';
      $image_url_mobile = 'https://www.konsolifin.net/sites/default/files/2026-07/GTAO_TKCH_LaunchPM_Multi_300x600_R01_fi_fi.jpg';
      $alt_text = 'GTA Online: The Kortz Center Heist pelattavissa nyt!';
      $ad_id = '';
      if ($base_id === 'top') {
        $ad_id = 'gtao_primary';
        $picture_markup = Markup::create(sprintf(
          '<picture>' .
          '<source media="(max-width: 800px)" srcset="%s">' .
          '<source media="(max-width: 1600px)" srcset="%s">' .
          '<img src="%s" alt="%s">' .
          '</picture>',
          $image_url_mobile,
          $image_url_desktop,
          $image_url_oversize,
          htmlspecialchars($alt_text, ENT_QUOTES, 'UTF-8')
        ));
      } else if ($base_id === 'content' && $unique_suffix === '_2') {
        $ad_id = 'gtao_secondary';
        $picture_markup = Markup::create(sprintf(
          '<picture>' .
          '<source media="(max-width: 800px)" srcset="%s">' .
          '<img src="%s" alt="%s">' .
          '</picture>',
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
              'data-content-name' => 'GTA Online Banner',
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

<?php

namespace Drupal\konsolifin_ads\TwigExtension;

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
  private int $adCounter = 1;

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
      $unique_suffix = "_" . $this->adCounter++;
    }

    return [
      '#theme' => 'konsolifin_ad',
      '#base_id' => $base_id,
      '#unique_suffix' => $unique_suffix,
    ];
  }

}

<?php

namespace Drupal\konsolifin_ads\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure KonsoliFIN Ads settings for this site.
 */
class AdsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'konsolifin_ads_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['konsolifin_ads.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('konsolifin_ads.settings');

    $form['image_campaign'] = [
      '#type' => 'details',
      '#title' => $this->t('Image Banner Campaign'),
      '#open' => TRUE,
    ];

    $form['image_campaign']['image_start_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Start Date'),
      '#default_value' => $config->get('image_start_date') ? \Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp(strtotime($config->get('image_start_date'))) : NULL,
    ];

    $form['image_campaign']['image_end_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('End Date'),
      '#default_value' => $config->get('image_end_date') ? \Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp(strtotime($config->get('image_end_date'))) : NULL,
    ];

    $form['image_campaign']['destination_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Destination URL'),
      '#default_value' => $config->get('destination_url'),
    ];

    $form['image_campaign']['image_url_oversize'] = [
      '#type' => 'url',
      '#title' => $this->t('Oversize Image URL'),
      '#default_value' => $config->get('image_url_oversize'),
      '#description' => $this->t('Used for large desktop screens.'),
    ];

    $form['image_campaign']['breakpoint_oversize'] = [
      '#type' => 'number',
      '#title' => $this->t('Oversize Breakpoint (max-width in px)'),
      '#default_value' => $config->get('breakpoint_oversize') ?: 1600,
    ];

    $form['image_campaign']['image_url_desktop'] = [
      '#type' => 'url',
      '#title' => $this->t('Desktop Image URL'),
      '#default_value' => $config->get('image_url_desktop'),
    ];

    $form['image_campaign']['breakpoint_desktop'] = [
      '#type' => 'number',
      '#title' => $this->t('Desktop Breakpoint (max-width in px)'),
      '#default_value' => $config->get('breakpoint_desktop') ?: 800,
    ];

    $form['image_campaign']['image_url_mobile'] = [
      '#type' => 'url',
      '#title' => $this->t('Mobile Image URL'),
      '#default_value' => $config->get('image_url_mobile'),
    ];

    $form['image_campaign']['alt_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alt Text'),
      '#default_value' => $config->get('alt_text'),
    ];

    $form['video_campaign'] = [
      '#type' => 'details',
      '#title' => $this->t('Video Interstitial Campaign'),
      '#open' => TRUE,
    ];

    $form['video_campaign']['video_start_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Start Date'),
      '#default_value' => $config->get('video_start_date') ? \Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp(strtotime($config->get('video_start_date'))) : NULL,
    ];

    $form['video_campaign']['video_end_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('End Date'),
      '#default_value' => $config->get('video_end_date') ? \Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp(strtotime($config->get('video_end_date'))) : NULL,
    ];

    $form['video_campaign']['youtube_url'] = [
      '#type' => 'url',
      '#title' => $this->t('YouTube URL'),
      '#default_value' => $config->get('youtube_url'),
      '#description' => $this->t('e.g., https://www.youtube.com/watch?v=dQw4w9WgXcQ or https://www.youtube.com/embed/dQw4w9WgXcQ'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('konsolifin_ads.settings');

    $image_start = $form_state->getValue('image_start_date');
    $image_end = $form_state->getValue('image_end_date');
    $video_start = $form_state->getValue('video_start_date');
    $video_end = $form_state->getValue('video_end_date');

    $config->set('image_start_date', $image_start ? $image_start->format('Y-m-d H:i:s') : NULL)
      ->set('image_end_date', $image_end ? $image_end->format('Y-m-d H:i:s') : NULL)
      ->set('destination_url', $form_state->getValue('destination_url'))
      ->set('image_url_oversize', $form_state->getValue('image_url_oversize'))
      ->set('breakpoint_oversize', $form_state->getValue('breakpoint_oversize'))
      ->set('image_url_desktop', $form_state->getValue('image_url_desktop'))
      ->set('breakpoint_desktop', $form_state->getValue('breakpoint_desktop'))
      ->set('image_url_mobile', $form_state->getValue('image_url_mobile'))
      ->set('alt_text', $form_state->getValue('alt_text'))
      ->set('video_start_date', $video_start ? $video_start->format('Y-m-d H:i:s') : NULL)
      ->set('video_end_date', $video_end ? $video_end->format('Y-m-d H:i:s') : NULL)
      ->set('youtube_url', $form_state->getValue('youtube_url'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

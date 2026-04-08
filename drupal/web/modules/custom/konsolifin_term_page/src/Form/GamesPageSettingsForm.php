<?php

declare(strict_types=1);

namespace Drupal\konsolifin_term_page\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Configuration form for the games page settings.
 *
 * Allows administrators to select three featured peli terms and configure
 * Matomo API credentials for the games page at /pelit.
 */
class GamesPageSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['konsolifin_term_page.games_page_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'konsolifin_term_page_games_page_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('konsolifin_term_page.games_page_settings');

    $form['#attached']['library'][] = 'konsolifin_term_page/admin-form';

    $form['top_games'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Top Games'),
    ];

    for ($i = 1; $i <= 3; $i++) {
      $default_value = NULL;
      $tid = $config->get("top_game_{$i}");
      if ($tid) {
        $term = Term::load($tid);
        if ($term) {
          $default_value = $term;
        }
      }

      $form['top_games']["top_game_{$i}"] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'taxonomy_term',
        '#selection_settings' => [
          'target_bundles' => ['peli' => 'peli'],
        ],
        '#title' => $this->t('Top game @number', ['@number' => $i]),
        '#default_value' => $default_value,
        '#attributes' => [
          'autocomplete' => 'off',
          'data-1p-ignore' => '',
        ],
      ];
    }

    $form['matomo'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Matomo API Settings'),
    ];

    $form['matomo']['matomo_api_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Matomo API URL'),
      '#description' => $this->t('The Matomo API endpoint URL.'),
      '#default_value' => $config->get('matomo_api_url') ?? '',
    ];

    $form['matomo']['matomo_auth_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Matomo Auth Token'),
      '#description' => $this->t('The Matomo authentication token.'),
      '#default_value' => $config->get('matomo_auth_token') ?? '',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    for ($i = 1; $i <= 3; $i++) {
      $tid = $form_state->getValue("top_game_{$i}");
      if (!empty($tid)) {
        $term = Term::load($tid);
        if (!$term) {
          $form_state->setErrorByName("top_game_{$i}", $this->t('The selected term for top game @number does not exist.', ['@number' => $i]));
        }
        elseif ($term->bundle() !== 'peli') {
          $form_state->setErrorByName("top_game_{$i}", $this->t('The selected term for top game @number must belong to the peli vocabulary.', ['@number' => $i]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('konsolifin_term_page.games_page_settings')
      ->set('top_game_1', $form_state->getValue('top_game_1'))
      ->set('top_game_2', $form_state->getValue('top_game_2'))
      ->set('top_game_3', $form_state->getValue('top_game_3'))
      ->set('matomo_api_url', $form_state->getValue('matomo_api_url'))
      ->set('matomo_auth_token', $form_state->getValue('matomo_auth_token'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

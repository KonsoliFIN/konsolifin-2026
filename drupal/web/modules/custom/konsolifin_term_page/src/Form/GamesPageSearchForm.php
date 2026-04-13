<?php

declare(strict_types=1);

namespace Drupal\konsolifin_term_page\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Search form for the games page with entity autocomplete on peli vocabulary.
 */
class GamesPageSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'konsolifin_games_page_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'konsolifin_term_page/games-search';

    $form['input_group'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['games-search-input-group'],
      ],
    ];

    $form['input_group']['search'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['peli' => 'peli'],
      ],
      '#title' => $this->t('Etsi peliä'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Kirjoita pelin nimi...'),
      '#attributes' => [
        'class' => ['games-search-input-group__input'],
      ],
    ];

    $form['input_group']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Siirry'),
      '#attributes' => [
        'class' => ['games-search-input-group__button'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $tid = $form_state->getValue('search');
    if ($tid) {
      $term = Term::load($tid);
      if ($term) {
        $form_state->setRedirectUrl($term->toUrl());
      }
    }
  }

}

<?php

declare(strict_types=1);

namespace Drupal\konsolifin_term_page\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\date_ish\Element\DateIshElement;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Form for adding a julkaisu node from the peli term page.
 */
class AddJulkaisuModalForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'konsolifin_term_page_add_julkaisu_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $taxonomy_term = NULL): array {
    $term = Term::load($taxonomy_term);
    if (!$term) {
      return $form;
    }

    $form['#prefix'] = '<div id="add-julkaisu-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['peli_tid'] = [
      '#type' => 'value',
      '#value' => $term->id(),
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $term->getName(),
      '#required' => TRUE,
    ];

    $form['alustat'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['alustat'],
      ],
      '#title' => $this->t('Platforms'),
      '#tags' => TRUE,
      '#required' => TRUE,
    ];

    $form['field_tyyppi'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        'ensijulkaisu' => $this->t('Ensijulkaisu'),
        'early_access' => $this->t('Early access'),
        'remaster' => $this->t('Remaster'),
        'remake' => $this->t('Remake'),
        'dlc' => $this->t('DLC'),
        'bundle' => $this->t('Bundle'),
      ],
      '#default_value' => 'ensijulkaisu',
      '#required' => TRUE,
    ];

    $form['field_julkaisuajankohta'] = DateIshElement::build($this->t('Release time'));

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#ajax' => [
        'callback' => '::ajaxSubmitCallback',
        'wrapper' => 'add-julkaisu-form-wrapper',
      ],
    ];

    return $form;
  }

  /**
   * Ajax callback for the form submission.
   */
  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#add-julkaisu-form-wrapper', $form));
    }
    else {
      $response->addCommand(new CloseModalDialogCommand());
      // Redirect back to the current page to show the new julkaisu.
      $peliTid = $form_state->getValue('peli_tid');
      $term = Term::load($peliTid);
      $url = $term ? $term->toUrl()->toString() : \Drupal::request()->getRequestUri();
      $response->addCommand(new RedirectCommand($url));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $title = $form_state->getValue('title');
      $peli_tid = $form_state->getValue('peli_tid');
      $alustat = $form_state->getValue('alustat');

      $alustat_tids = [];
      if (!empty($alustat) && is_array($alustat)) {
        foreach ($alustat as $item) {
          if (isset($item['target_id'])) {
            $alustat_tids[] = ['target_id' => $item['target_id']];
          }
        }
      }

      $node = Node::create([
        'type' => 'julkaisu',
        'title' => $title,
        'field_pelit' => [['target_id' => $peli_tid]],
        'field_tyyppi' => $form_state->getValue('field_tyyppi'),
        'field_alustat' => $alustat_tids,
      ]);

      // Extract date_ish value using the helper.
      $date_ish = DateIshElement::extractValue($form_state->getValue('field_julkaisuajankohta'));
      if (!empty($date_ish)) {
        $node->set('field_julkaisuajankohta', $date_ish);
      }

      $node->save();
    }
    catch (\Exception $e) {
      \Drupal::logger('konsolifin_term_page')->error('Failed to create julkaisu: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger()->addError($this->t('An error occurred while saving. Please try again.'));
    }
  }

}

<?php

declare(strict_types=1);

namespace Drupal\konsolifin_term_page\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\ReloadCommand;

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

    // Reference the taxonomy field for alustat.
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

    // date_ish field. We will use a standard date ish widget structure or just let user input date manually.
    // However, the cleanest way to embed a field widget in a custom form is by attaching it to an entity,
    // but since we want a simple form, we can use the date_ish form element if it exists, or just provide inputs.
    // Let's create a dummy node to use entity form display for date_ish.
    $node = Node::create([
      'type' => 'julkaisu',
    ]);
    $form_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('node.julkaisu.default');

    if ($form_display) {
      $widget = $form_display->getRenderer('field_julkaisuajankohta');
      if ($widget) {
        $items = $node->get('field_julkaisuajankohta');
        $form['field_julkaisuajankohta'] = $widget->form($items, $form, $form_state);
        // We only want the widget for field_julkaisuajankohta. The $widget->form might return nested structure.
      }
    }

    // If the widget extraction fails or is too complex, we fall back to a simpler approach:
    if (!isset($form['field_julkaisuajankohta'])) {
      // Fallback: manually provide date_ish fields if the widget is not straightforward.
      // Wait, date_ish module defines a 'date_ish' Element type.
      $form['field_julkaisuajankohta'] = [
        '#type' => 'date_ish',
        '#title' => $this->t('Release time'),
      ];
    }

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
  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#add-julkaisu-form-wrapper', $form));
    }
    else {
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new ReloadCommand());
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $title = $form_state->getValue('title');
    $peli_tid = $form_state->getValue('peli_tid');
    $alustat = $form_state->getValue('alustat');

    // Extract date_ish value. If we used the widget from entity_form_display, it's nested in field_julkaisuajankohta[0].
    $date_value = $form_state->getValue('field_julkaisuajankohta');
    if (isset($date_value[0])) {
      $date_ish_data = $date_value[0];
    } else {
      $date_ish_data = $date_value; // If it's directly the element.
    }

    $alustat_tids = array_map(function($item) {
      return ['target_id' => $item['target_id']];
    }, $alustat);

    $node = Node::create([
      'type' => 'julkaisu',
      'title' => $title,
      'field_pelit' => [
        ['target_id' => $peli_tid],
      ],
      'field_tyyppi' => 'ensijulkaisu',
      'field_alustat' => $alustat_tids,
    ]);

    // Handle date_ish data properly.
    if (!empty($date_ish_data)) {
      $node->set('field_julkaisuajankohta', $date_ish_data);
    }

    $node->save();
  }

}

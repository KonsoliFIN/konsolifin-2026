<?php

declare(strict_types=1);

namespace Drupal\konsolifin_misc\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;

/**
 * Form hook implementations for konsolifin_misc.
 */
class KonsolifinMiscFormHooks {

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for user_form.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(array &$form, FormStateInterface $form_state, string $form_id = ''): void {
    $form_object = $form_state->getFormObject();
    if (!method_exists($form_object, 'getEntity')) {
      return;
    }

    $user = $form_object->getEntity();
    if (!$user instanceof UserInterface) {
      return;
    }

    if (isset($form['field_slack_id']) && !$user->hasRole('toimitus')) {
      $form['field_slack_id']['#access'] = FALSE;
    }
  }

}

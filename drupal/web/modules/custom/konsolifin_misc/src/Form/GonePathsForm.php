<?php

declare(strict_types=1);

namespace Drupal\konsolifin_misc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\konsolifin_misc\EventSubscriber\GonePathSubscriber;

/**
 * Configuration form for managing HTTP 410 Gone path prefixes.
 */
class GonePathsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['konsolifin_misc.gone_paths'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'konsolifin_misc_gone_paths';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('konsolifin_misc.gone_paths');
    $prefixes = $config->get('prefixes');
    if ($prefixes === NULL) {
      $prefixes = GonePathSubscriber::DEFAULT_PREFIXES;
    }
    $prefixesText = implode("\n", $prefixes);

    $form['help'] = [
      '#type' => 'item',
      '#markup' => '<p>' . $this->t('Määritä polut tai polkualkuosat, jotka ovat pysyvästi poistuneet sivustolta (esim. vanhan sivustoalustan polut tai vanha foorumi). Näihin polkuihin osuvat pyynnöt palauttavat välittömästi <strong>HTTP 410 Gone</strong> -tilakoodin ilman reitityskuormaa tai 404-virhelokimerkintöjä. Hakukoneet poistavat 410-polut indeksistään nopeasti.') . '</p>',
    ];

    $form['prefixes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Poistetut polkualkuosat (HTTP 410 Gone)'),
      '#description' => $this->t('Syötä yksi polkualkuosa riviä kohden (esim. <code>/bbs/</code>, <code>/konsolifin.php</code>, <code>/arviolista.php</code>).<br>Polut ovat kirjainkoosta riippumattomia (case-insensitive). Hakemistopolkujen (kuten <code>/bbs/</code>) osalta huomioidaan sekä tarkka polku että kaikki sen alasivut.'),
      '#default_value' => $prefixesText,
      '#rows' => 10,
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $raw = (string) $form_state->getValue('prefixes');
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    if (!is_array($lines)) {
      return;
    }

    $reservedPaths = ['/admin', '/user', '/system', '/core', '/sites', '/themes', '/modules'];

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }

      if ($line === '/') {
        $form_state->setErrorByName('prefixes', $this->t('Pelkkä juuripolku (/) ei voi olla 410-polku, koska se estäisi pääsyn koko sivustolle.'));
        return;
      }

      $normalized = '/' . ltrim($line, '/');
      $cleanBase = mb_strtolower(rtrim($normalized, '/'));
      if (in_array($cleanBase, $reservedPaths, TRUE)) {
        $form_state->setErrorByName('prefixes', $this->t('Polkualkuosa @path on varattu Drupalin järjestelmälle eikä sitä voi asettaa poistetuksi.', ['@path' => $line]));
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $raw = (string) $form_state->getValue('prefixes');
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $clean = [];

    if (is_array($lines)) {
      foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
          continue;
        }
        if (!str_starts_with($line, '/')) {
          $line = '/' . $line;
        }
        $clean[] = $line;
      }
    }

    $clean = array_values(array_unique($clean));

    $this->config('konsolifin_misc.gone_paths')
      ->set('prefixes', $clean)
      ->save();

    parent::submitForm($form, $form_state);
  }

}

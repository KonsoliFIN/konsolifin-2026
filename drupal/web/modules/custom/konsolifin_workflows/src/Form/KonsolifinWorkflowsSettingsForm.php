<?php

declare(strict_types=1);

namespace Drupal\konsolifin_workflows\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\konsolifin_workflows\SlackNotificationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for KonsoliFIN workflow Slack settings.
 */
class KonsolifinWorkflowsSettingsForm extends ConfigFormBase {

  /**
   * The Slack notification service.
   */
  protected ?SlackNotificationService $slackNotificationService = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    if ($container->has('konsolifin_workflows.slack_notifier')) {
      $instance->slackNotificationService = $container->get('konsolifin_workflows.slack_notifier');
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['konsolifin_workflows.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'konsolifin_workflows_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('konsolifin_workflows.settings');

    $form['slack'] = [
      '#type' => 'details',
      '#title' => $this->t('Slack-integraation asetukset'),
      '#open' => TRUE,
    ];

    $form['slack']['slack_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ota Slack-ilmoitukset käyttöön'),
      '#description' => $this->t('Kun valittuna, työnkulkujen tilamuutokset lähettävät automaattisesti ilmoituksen Slackiin.'),
      '#default_value' => (bool) $config->get('slack_enabled'),
    ];

    $form['slack']['slack_api_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Slack Bot Token tai Webhook URL'),
      '#description' => $this->t('Syötä Slack Bot User OAuth Token (esim. <code>xoxb-...</code>) tai Incoming Webhook URL.'),
      '#default_value' => $config->get('slack_api_token') ?? '',
    ];

    $form['channels'] = [
      '#type' => 'details',
      '#title' => $this->t('Kohdekanavat'),
      '#open' => TRUE,
    ];

    $form['channels']['channel_oikoluku'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Oikoluvun kanava'),
      '#description' => $this->t('Kanava sisällöille, jotka siirtyvät Oikoluku-tilaan (<code>yleinen_julkaisuputki_oikoluettavana</code>). Esim. <code>#oikoluku</code> tai kanava-ID (esim. <code>C0123456789</code>) tai kanavakohtainen webhook-osoite.'),
      '#default_value' => $config->get('channel_oikoluku') ?? '',
    ];

    $form['channels']['channel_news_published'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Julkaistujen uutisten kanava'),
      '#description' => $this->t('Kanava julkaistuille uutisille (<code>uutisputki_julkaistu</code>). Esim. <code>#uutiset</code> tai kanava-ID tai kanavakohtainen webhook-osoite.'),
      '#default_value' => $config->get('channel_news_published') ?? '',
    ];

    $form['channels']['channel_other_published'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Muiden julkaistujen sisältöjen kanava'),
      '#description' => $this->t('Kanava muille julkaistuille sisällöille, kuten artikkeleille ja arvosteluille (<code>yleinen_julkaisuputki_julkaistu</code>). Esim. <code>#julkaisut</code> tai kanava-ID tai kanavakohtainen webhook-osoite.'),
      '#default_value' => $config->get('channel_other_published') ?? '',
    ];

    $form['actions']['test_notification'] = [
      '#type' => 'submit',
      '#value' => $this->t('Lähetä testi-ilmoitukset'),
      '#submit' => ['::submitTestNotification'],
      '#button_type' => 'secondary',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Submits a test notification to configured channels.
   */
  public function submitTestNotification(array &$form, FormStateInterface $form_state): void {
    // Save current values first.
    $this->submitForm($form, $form_state);

    if (!$this->slackNotificationService) {
      $this->messenger()->addError($this->t('Slack-ilmoituspalvelua ei ole alustettu.'));
      return;
    }

    $token = trim((string) $form_state->getValue('slack_api_token'));
    if (empty($token)) {
      $this->messenger()->addWarning($this->t('Slack API Token tai Webhook URL puuttuu. Testiviestiä ei voitu lähettää.'));
      return;
    }

    $channels = [
      'Oikoluku' => trim((string) $form_state->getValue('channel_oikoluku')),
      'Uutiset' => trim((string) $form_state->getValue('channel_news_published')),
      'Muut sisällöt' => trim((string) $form_state->getValue('channel_other_published')),
    ];

    $sentCount = 0;
    foreach ($channels as $label => $channel) {
      if (!empty($channel)) {
        $message = "🛠️ KonsoliFIN Workflows - Testiviesti kanavalle: {$label} (" . date('d.m.Y H:i:s') . ")";
        $success = $this->slackNotificationService->sendMessage($channel, $message);
        if ($success) {
          $this->messenger()->addStatus($this->t('Testiviesti lähetetty onnistuneesti kohteeseen @label (@channel).', [
            '@label' => $label,
            '@channel' => $channel,
          ]));
          $sentCount++;
        }
        else {
          $this->messenger()->addError($this->t('Testiviestin lähetys epäonnistui kohteeseen @label (@channel). Tarkista lokit.', [
            '@label' => $label,
            '@channel' => $channel,
          ]));
        }
      }
    }

    if ($sentCount === 0) {
      $this->messenger()->addWarning($this->t('Yhtään kanavaa ei ollut määritelty testattavaksi.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('konsolifin_workflows.settings')
      ->set('slack_enabled', (bool) $form_state->getValue('slack_enabled'))
      ->set('slack_api_token', trim((string) $form_state->getValue('slack_api_token')))
      ->set('channel_oikoluku', trim((string) $form_state->getValue('channel_oikoluku')))
      ->set('channel_news_published', trim((string) $form_state->getValue('channel_news_published')))
      ->set('channel_other_published', trim((string) $form_state->getValue('channel_other_published')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

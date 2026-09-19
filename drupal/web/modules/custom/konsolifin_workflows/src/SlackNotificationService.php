<?php

declare(strict_types=1);

namespace Drupal\konsolifin_workflows;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for sending Slack notifications on workflow state transitions.
 */
class SlackNotificationService {

  /**
   * The logger channel.
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs a SlackNotificationService object.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    protected ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->logger = $loggerFactory->get('konsolifin_workflows');
  }

  /**
   * Checks if Slack notifications are globally enabled.
   */
  public function isEnabled(): bool {
    return (bool) $this->configFactory->get('konsolifin_workflows.settings')->get('slack_enabled');
  }

  /**
   * Sends a message to a designated Slack channel or webhook.
   *
   * Supports:
   * 1. Direct Webhook URL passed as $channel.
   * 2. Global Incoming Webhook URL configured in slack_api_token.
   * 3. Bot User OAuth Token (xoxb-...) with #channel or channel ID.
   *
   * @param string $channel
   *   The channel name (#channel), channel ID (C123...), or a Webhook URL.
   * @param string $text
   *   Fallback or plain notification text.
   * @param array $blocks
   *   Optional Slack Block Kit blocks array.
   *
   * @return bool
   *   TRUE if sending succeeded, FALSE otherwise.
   */
  public function sendMessage(string $channel, string $text, array $blocks = []): bool {
    if (!$this->isEnabled()) {
      return FALSE;
    }

    $channel = trim($channel);
    if (empty($channel)) {
      $this->logger->warning('Slack notification skipped: target channel is empty.');
      return FALSE;
    }

    $tokenOrWebhook = trim((string) $this->configFactory->get('konsolifin_workflows.settings')->get('slack_api_token'));
    $payload = ['text' => $text];
    if (!empty($blocks)) {
      $payload['blocks'] = $blocks;
    }

    try {
      // Case 1: The channel string itself is a direct webhook URL.
      if (str_starts_with($channel, 'http://') || str_starts_with($channel, 'https://')) {
        $response = $this->httpClient->request('POST', $channel, [
          'headers' => ['Content-Type' => 'application/json'],
          'json' => $payload,
          'timeout' => 8,
        ]);
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
      }

      // Case 2: The configured API token is an Incoming Webhook URL.
      if (str_starts_with($tokenOrWebhook, 'http://') || str_starts_with($tokenOrWebhook, 'https://')) {
        $payload['channel'] = $channel;
        $response = $this->httpClient->request('POST', $tokenOrWebhook, [
          'headers' => ['Content-Type' => 'application/json'],
          'json' => $payload,
          'timeout' => 8,
        ]);
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
      }

      // Case 3: Bot token calling Slack chat.postMessage API.
      if (empty($tokenOrWebhook)) {
        $this->logger->warning('Slack notification skipped: slack_api_token is not configured.');
        return FALSE;
      }

      $payload['channel'] = $channel;
      $response = $this->httpClient->request('POST', 'https://slack.com/api/chat.postMessage', [
        'headers' => [
          'Authorization' => 'Bearer ' . $tokenOrWebhook,
          'Content-Type' => 'application/json; charset=utf-8',
        ],
        'json' => $payload,
        'timeout' => 8,
      ]);

      if ($response->getStatusCode() !== 200) {
        $this->logger->error('Slack API returned HTTP status @status', ['@status' => $response->getStatusCode()]);
        return FALSE;
      }

      $body = (string) $response->getBody();
      $data = json_decode($body, TRUE);
      if (is_array($data) && empty($data['ok'])) {
        $this->logger->error('Slack API error: @error', ['@error' => $data['error'] ?? 'unknown']);
        return FALSE;
      }

      return TRUE;
    }
    catch (GuzzleException $e) {
      $this->logger->error('Guzzle error while sending Slack message to @channel: @message', [
        '@channel' => $channel,
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
    catch (\Throwable $e) {
      $this->logger->error('Unexpected error while sending Slack message to @channel: @message', [
        '@channel' => $channel,
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Notifies the configured proofreading channel that a node moved to Oikoluku.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return bool
   *   TRUE if notification was sent, FALSE otherwise.
   */
  public function notifyOikoluku(NodeInterface $node): bool {
    $channel = trim((string) $this->configFactory->get('konsolifin_workflows.settings')->get('channel_oikoluku'));
    if (empty($channel)) {
      $this->logger->info('Oikoluku Slack notification skipped: channel_oikoluku is not configured.');
      return FALSE;
    }

    $title = $node->getTitle();
    $bundleLabel = $this->getBundleLabel($node);
    $author = $this->formatAuthor($node);
    $viewUrl = $this->getNodeUrl($node, 'canonical');
    $editUrl = $this->getNodeUrl($node, 'edit-form');

    $titleLink = $viewUrl ? "<{$viewUrl}|{$title}>" : $title;
    $text = "📝 *Uusi sisältö siirretty oikoluettavaksi:*\n• *Otsikko:* {$titleLink} ({$bundleLabel})\n• *Kirjoittaja:* {$author}";
    if ($editUrl) {
      $text .= "\n• *Toiminto:* <{$editUrl}|Avaa muokkauslomake>";
    }

    $blocks = [
      [
        'type' => 'header',
        'text' => [
          'type' => 'plain_text',
          'text' => '📝 Sisältö odottaa oikolukua',
          'emoji' => TRUE,
        ],
      ],
      [
        'type' => 'section',
        'fields' => [
          [
            'type' => 'mrkdwn',
            'text' => "*Otsikko:*\n{$titleLink}",
          ],
          [
            'type' => 'mrkdwn',
            'text' => "*Tyyppi:*\n{$bundleLabel}",
          ],
          [
            'type' => 'mrkdwn',
            'text' => "*Kirjoittaja:*\n{$author}",
          ],
        ],
      ],
    ];

    if ($editUrl) {
      $blocks[] = [
        'type' => 'actions',
        'elements' => [
          [
            'type' => 'button',
            'text' => [
              'type' => 'plain_text',
              'text' => 'Avaa oikoluettavaksi ✏️',
              'emoji' => TRUE,
            ],
            'url' => $editUrl,
            'action_id' => 'open_edit_form',
          ],
        ],
      ];
    }

    return $this->sendMessage($channel, $text, $blocks);
  }

  /**
   * Notifies the configured Slack channel that a node moved to Published state.
   *
   * Routes news to channel_news_published and other content to channel_other_published.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return bool
   *   TRUE if notification was sent, FALSE otherwise.
   */
  public function notifyPublished(NodeInterface $node): bool {
    $config = $this->configFactory->get('konsolifin_workflows.settings');
    $isNews = ($node->bundle() === 'uutinen') || $node->hasField('field_tyonkulku_uutinen');

    $channel = $isNews
      ? trim((string) $config->get('channel_news_published'))
      : trim((string) $config->get('channel_other_published'));

    if (empty($channel)) {
      $this->logger->info('Publication Slack notification skipped: channel is not configured for @type.', [
        '@type' => $isNews ? 'news' : 'other content',
      ]);
      return FALSE;
    }

    $title = $node->getTitle();
    $bundleLabel = $this->getBundleLabel($node);
    $author = $this->formatAuthor($node);
    $canonicalUrl = $this->getNodeUrl($node, 'canonical');

    $titleLink = $canonicalUrl ? "<{$canonicalUrl}|{$title}>" : $title;
    $text = "🚀 *Uusi sisältö julkaistu:* {$titleLink} ({$bundleLabel})\n• *Kirjoittaja:* {$author}";
    if ($canonicalUrl) {
      $text .= "\n• *Linkki:* <{$canonicalUrl}|Lue KonsoliFINissä>";
    }

    $blocks = [
      [
        'type' => 'header',
        'text' => [
          'type' => 'plain_text',
          'text' => '🚀 Uusi sisältö julkaistu!',
          'emoji' => TRUE,
        ],
      ],
      [
        'type' => 'section',
        'fields' => [
          [
            'type' => 'mrkdwn',
            'text' => "*Otsikko:*\n{$titleLink}",
          ],
          [
            'type' => 'mrkdwn',
            'text' => "*Tyyppi:*\n{$bundleLabel}",
          ],
          [
            'type' => 'mrkdwn',
            'text' => "*Kirjoittaja:*\n{$author}",
          ],
        ],
      ],
    ];

    if ($canonicalUrl) {
      $blocks[] = [
        'type' => 'actions',
        'elements' => [
          [
            'type' => 'button',
            'text' => [
              'type' => 'plain_text',
              'text' => 'Lue sisältö 🎮',
              'emoji' => TRUE,
            ],
            'url' => $canonicalUrl,
            'action_id' => 'view_published_node',
          ],
        ],
      ];
    }

    return $this->sendMessage($channel, $text, $blocks);
  }

  /**
   * Helper to format author name, tagging Slack ID if present.
   */
  protected function formatAuthor(NodeInterface $node): string {
    $owner = $node->getOwner();
    if (!$owner) {
      return (string) $node->getOwnerId();
    }

    if ($owner->hasField('field_slack_id') && !$owner->get('field_slack_id')->isEmpty()) {
      $field = $owner->get('field_slack_id');
      $slackId = trim((string) ($field->value ?? $field->getString()));
      if (!empty($slackId)) {
        return "<@{$slackId}>";
      }
    }

    $displayName = $owner->getDisplayName();
    return !empty($displayName) ? $displayName : (string) $node->getOwnerId();
  }

  /**
   * Helper to resolve bundle label.
   */
  protected function getBundleLabel(NodeInterface $node): string {
    $bundle = $node->bundle();
    try {
      $bundleEntity = $this->entityTypeManager->getStorage('node_type')->load($bundle);
      if ($bundleEntity) {
        return (string) $bundleEntity->label();
      }
    }
    catch (\Exception $e) {
    }
    return ucfirst($bundle);
  }

  /**
   * Helper to get absolute entity URL.
   */
  protected function getNodeUrl(NodeInterface $node, string $rel): string {
    try {
      if ($node->hasLinkTemplate($rel)) {
        return $node->toUrl($rel, ['absolute' => TRUE])->toString();
      }
    }
    catch (\Exception $e) {
    }
    return '';
  }

}

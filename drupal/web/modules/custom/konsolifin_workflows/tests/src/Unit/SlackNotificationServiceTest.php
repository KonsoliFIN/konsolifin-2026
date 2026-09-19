<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_workflows\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\konsolifin_workflows\SlackNotificationService;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests the SlackNotificationService.
 *
 * @coversDefaultClass \Drupal\konsolifin_workflows\SlackNotificationService
 */
#[AllowMockObjectsWithoutExpectations]
class SlackNotificationServiceTest extends TestCase {

  protected ClientInterface $httpClient;
  protected ConfigFactoryInterface $configFactory;
  protected ImmutableConfig $config;
  protected LoggerChannelFactoryInterface $loggerFactory;
  protected LoggerChannelInterface $logger;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityStorageInterface $nodeTypeStorage;

  protected function setUp(): void {
    parent::setUp();

    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->config = $this->createMock(ImmutableConfig::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->nodeTypeStorage = $this->createMock(EntityStorageInterface::class);

    $this->loggerFactory->method('get')->with('konsolifin_workflows')->willReturn($this->logger);
    $this->configFactory->method('get')->with('konsolifin_workflows.settings')->willReturn($this->config);
    $this->entityTypeManager->method('getStorage')->with('node_type')->willReturn($this->nodeTypeStorage);
  }

  /**
   * Helper to configure standard settings.
   */
  protected function configureSettings(array $settings): void {
    $this->config->method('get')->willReturnCallback(function ($key) use ($settings) {
      return $settings[$key] ?? NULL;
    });
  }

  /**
   * Tests when Slack notifications are disabled.
   */
  public function testDisabledByDefaultDoesNotSend(): void {
    $this->configureSettings([
      'slack_enabled' => FALSE,
      'slack_api_token' => 'xoxb-test',
    ]);

    $this->httpClient->expects($this->never())->method('request');

    $service = new SlackNotificationService(
      $this->httpClient,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
    );

    $this->assertFalse($service->isEnabled());
    $this->assertFalse($service->sendMessage('#test', 'Hello world'));
  }

  /**
   * Tests when channel is empty.
   */
  public function testEmptyChannelReturnsFalse(): void {
    $this->configureSettings([
      'slack_enabled' => TRUE,
      'slack_api_token' => 'xoxb-test',
    ]);

    $this->httpClient->expects($this->never())->method('request');

    $service = new SlackNotificationService(
      $this->httpClient,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
    );

    $this->assertFalse($service->sendMessage('', 'Hello world'));
  }

  /**
   * Tests sending message via Bot User OAuth token to Slack API chat.postMessage.
   */
  public function testSendMessageViaBotTokenSuccess(): void {
    $this->configureSettings([
      'slack_enabled' => TRUE,
      'slack_api_token' => 'xoxb-my-bot-token',
    ]);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        'https://slack.com/api/chat.postMessage',
        $this->callback(function ($options) {
          return $options['headers']['Authorization'] === 'Bearer xoxb-my-bot-token'
            && $options['json']['channel'] === '#oikoluku'
            && $options['json']['text'] === 'Test message';
        })
      )
      ->willReturn(new Response(200, [], json_encode(['ok' => TRUE])));

    $service = new SlackNotificationService(
      $this->httpClient,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
    );

    $this->assertTrue($service->sendMessage('#oikoluku', 'Test message'));
  }

  /**
   * Tests sending message when channel is a direct Webhook URL.
   */
  public function testSendMessageViaDirectWebhookUrl(): void {
    $this->configureSettings([
      'slack_enabled' => TRUE,
      'slack_api_token' => '',
    ]);

    $webhookUrl = 'https://hooks.slack.com/services/T00/B00/XXXX';

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        $webhookUrl,
        $this->callback(function ($options) {
          return $options['json']['text'] === 'Webhook test';
        })
      )
      ->willReturn(new Response(200, [], 'ok'));

    $service = new SlackNotificationService(
      $this->httpClient,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
    );

    $this->assertTrue($service->sendMessage($webhookUrl, 'Webhook test'));
  }

  /**
   * Tests sending message when token is an Incoming Webhook URL.
   */
  public function testSendMessageViaConfiguredWebhookUrl(): void {
    $webhookUrl = 'https://hooks.slack.com/services/T00/B00/YYYY';
    $this->configureSettings([
      'slack_enabled' => TRUE,
      'slack_api_token' => $webhookUrl,
    ]);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        $webhookUrl,
        $this->callback(function ($options) {
          return $options['json']['channel'] === '#general'
            && $options['json']['text'] === 'Global webhook test';
        })
      )
      ->willReturn(new Response(200, [], 'ok'));

    $service = new SlackNotificationService(
      $this->httpClient,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
    );

    $this->assertTrue($service->sendMessage('#general', 'Global webhook test'));
  }

  /**
   * Tests when Slack API returns ok: false.
   */
  public function testSendMessageSlackApiError(): void {
    $this->configureSettings([
      'slack_enabled' => TRUE,
      'slack_api_token' => 'xoxb-invalid',
    ]);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('POST', 'https://slack.com/api/chat.postMessage', $this->anything())
      ->willReturn(new Response(200, [], json_encode(['ok' => FALSE, 'error' => 'channel_not_found'])));

    $service = new SlackNotificationService(
      $this->httpClient,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
    );

    $this->assertFalse($service->sendMessage('#nonexistent', 'Will fail'));
  }

  /**
   * Tests graceful Guzzle exception handling.
   */
  public function testSendMessageGuzzleExceptionHandling(): void {
    $this->configureSettings([
      'slack_enabled' => TRUE,
      'slack_api_token' => 'xoxb-test',
    ]);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('POST', 'https://slack.com/api/chat.postMessage', $this->anything())
      ->willThrowException(new ConnectException('Connection timed out', new Request('POST', 'https://slack.com')));

    $service = new SlackNotificationService(
      $this->httpClient,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
    );

    $this->assertFalse($service->sendMessage('#general', 'Network timeout'));
  }

  /**
   * Tests notifyOikoluku formatting, tagging author Slack ID, and sending.
   */
  public function testNotifyOikoluku(): void {
    $this->configureSettings([
      'slack_enabled' => TRUE,
      'slack_api_token' => 'xoxb-test',
      'channel_oikoluku' => '#oikoluku-kanava',
    ]);

    $user = $this->createMock(UserInterface::class);
    $user->method('hasField')->with('field_slack_id')->willReturn(TRUE);
    $slackField = $this->createMock(FieldItemListInterface::class);
    $slackField->method('isEmpty')->willReturn(FALSE);
    $slackField->method('getString')->willReturn('U12345678');
    $user->method('get')->with('field_slack_id')->willReturn($slackField);

    $nodeType = $this->createMock(ConfigEntityInterface::class);
    $nodeType->method('label')->willReturn('Peliarvostelu');
    $this->nodeTypeStorage->method('load')->with('peliarvostelu')->willReturn($nodeType);

    $node = $this->createMock(NodeInterface::class);
    $node->method('getTitle')->willReturn('Zelda: Echoes of Wisdom');
    $node->method('bundle')->willReturn('peliarvostelu');
    $node->method('getOwner')->willReturn($user);
    $node->method('hasLinkTemplate')->willReturn(FALSE);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        'https://slack.com/api/chat.postMessage',
        $this->callback(function ($options) {
          $json = $options['json'];
          return $json['channel'] === '#oikoluku-kanava'
            && str_contains($json['text'], 'Zelda: Echoes of Wisdom')
            && str_contains($json['text'], '<@U12345678>')
            && str_contains($json['text'], 'Peliarvostelu');
        })
      )
      ->willReturn(new Response(200, [], json_encode(['ok' => TRUE])));

    $service = new SlackNotificationService(
      $this->httpClient,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
    );

    $this->assertTrue($service->notifyOikoluku($node));
  }

  /**
   * Tests notifyPublished routing news to news channel.
   */
  public function testNotifyPublishedRoutesNewsToNewsChannel(): void {
    $this->configureSettings([
      'slack_enabled' => TRUE,
      'slack_api_token' => 'xoxb-test',
      'channel_news_published' => '#kf-uutiset',
      'channel_other_published' => '#kf-julkaisut',
    ]);

    $user = $this->createMock(UserInterface::class);
    $user->method('hasField')->with('field_slack_id')->willReturn(FALSE);
    $user->method('getDisplayName')->willReturn('Matti Meikäläinen');

    $node = $this->createMock(NodeInterface::class);
    $node->method('getTitle')->willReturn('Nintendo Direct tulossa');
    $node->method('bundle')->willReturn('uutinen');
    $node->method('hasField')->with('field_tyonkulku_uutinen')->willReturn(TRUE);
    $node->method('getOwner')->willReturn($user);
    $node->method('hasLinkTemplate')->willReturn(FALSE);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        'https://slack.com/api/chat.postMessage',
        $this->callback(function ($options) {
          $json = $options['json'];
          return $json['channel'] === '#kf-uutiset'
            && str_contains($json['text'], 'Nintendo Direct tulossa')
            && str_contains($json['text'], 'Matti Meikäläinen');
        })
      )
      ->willReturn(new Response(200, [], json_encode(['ok' => TRUE])));

    $service = new SlackNotificationService(
      $this->httpClient,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
    );

    $this->assertTrue($service->notifyPublished($node));
  }

  /**
   * Tests notifyPublished routing non-news content to other published channel.
   */
  public function testNotifyPublishedRoutesOtherContentToOtherChannel(): void {
    $this->configureSettings([
      'slack_enabled' => TRUE,
      'slack_api_token' => 'xoxb-test',
      'channel_news_published' => '#kf-uutiset',
      'channel_other_published' => '#kf-julkaisut',
    ]);

    $user = $this->createMock(UserInterface::class);
    $user->method('hasField')->with('field_slack_id')->willReturn(FALSE);
    $user->method('getDisplayName')->willReturn('Maija Meikäläinen');

    $node = $this->createMock(NodeInterface::class);
    $node->method('getTitle')->willReturn('PlayStation 30 vuotta -muistelo');
    $node->method('bundle')->willReturn('artikkeli');
    $node->method('hasField')->with('field_tyonkulku_uutinen')->willReturn(FALSE);
    $node->method('getOwner')->willReturn($user);
    $node->method('hasLinkTemplate')->willReturn(FALSE);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with(
        'POST',
        'https://slack.com/api/chat.postMessage',
        $this->callback(function ($options) {
          $json = $options['json'];
          return $json['channel'] === '#kf-julkaisut'
            && str_contains($json['text'], 'PlayStation 30 vuotta -muistelo')
            && str_contains($json['text'], 'Maija Meikäläinen');
        })
      )
      ->willReturn(new Response(200, [], json_encode(['ok' => TRUE])));

    $service = new SlackNotificationService(
      $this->httpClient,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
    );

    $this->assertTrue($service->notifyPublished($node));
  }

}

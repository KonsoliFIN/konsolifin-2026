<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_workflows\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\konsolifin_workflows\EventSubscriber\WorkflowTransitionSubscriber;
use Drupal\konsolifin_workflows\SlackNotificationService;
use Drupal\node\NodeInterface;
use Drupal\workflow\Entity\WorkflowTransitionInterface;
use Drupal\workflow\Event\WorkflowEvents;
use Drupal\workflow\Event\WorkflowTransitionEvent;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests the WorkflowTransitionSubscriber.
 *
 * @coversDefaultClass \Drupal\konsolifin_workflows\EventSubscriber\WorkflowTransitionSubscriber
 */
#[AllowMockObjectsWithoutExpectations]
class WorkflowTransitionSubscriberTest extends TestCase {

  protected SlackNotificationService $slackNotificationService;
  protected WorkflowTransitionSubscriber $subscriber;

  protected function setUp(): void {
    parent::setUp();
    $this->slackNotificationService = $this->createMock(SlackNotificationService::class);
    $this->subscriber = new WorkflowTransitionSubscriber($this->slackNotificationService);
  }

  /**
   * Helper to create a mock WorkflowTransitionEvent.
   */
  protected function createMockEvent(
    ?EntityInterface $entity,
    string $fromSid,
    string $toSid,
    bool $isScheduled = FALSE,
  ): WorkflowTransitionEvent {
    $transition = $this->createMock(WorkflowTransitionInterface::class);
    $transition->method('getTargetEntity')->willReturn($entity);
    $transition->method('getFromSid')->willReturn($fromSid);
    $transition->method('getToSid')->willReturn($toSid);
    $transition->method('isScheduled')->willReturn($isScheduled);

    return new WorkflowTransitionEvent($transition);
  }

  /**
   * Tests subscribed events registration.
   */
  public function testSubscribedEvents(): void {
    $events = WorkflowTransitionSubscriber::getSubscribedEvents();
    $this->assertArrayHasKey(WorkflowEvents::POST_TRANSITION, $events);
    $this->assertEquals(['onPostTransition', 0], $events[WorkflowEvents::POST_TRANSITION]);
  }

  /**
   * Tests that transition to Oikoluku triggers notifyOikoluku.
   */
  public function testTransitionToOikolukuTriggersNotify(): void {
    $node = $this->createMock(NodeInterface::class);
    $event = $this->createMockEvent(
      $node,
      'yleinen_julkaisuputki_tyon_alla',
      WorkflowTransitionSubscriber::STATE_OIKOLUETTAVANA,
      FALSE
    );

    $this->slackNotificationService->expects($this->once())
      ->method('notifyOikoluku')
      ->with($node);
    $this->slackNotificationService->expects($this->never())
      ->method('notifyPublished');

    $this->subscriber->onPostTransition($event);
  }

  /**
   * Tests that transition to general published triggers notifyPublished.
   */
  public function testTransitionToGeneralPublishedTriggersNotify(): void {
    $node = $this->createMock(NodeInterface::class);
    $event = $this->createMockEvent(
      $node,
      'yleinen_julkaisuputki_julkaisematta',
      WorkflowTransitionSubscriber::STATE_GENERAL_PUBLISHED,
      FALSE
    );

    $this->slackNotificationService->expects($this->once())
      ->method('notifyPublished')
      ->with($node);
    $this->slackNotificationService->expects($this->never())
      ->method('notifyOikoluku');

    $this->subscriber->onPostTransition($event);
  }

  /**
   * Tests that transition to news published triggers notifyPublished.
   */
  public function testTransitionToNewsPublishedTriggersNotify(): void {
    $node = $this->createMock(NodeInterface::class);
    $event = $this->createMockEvent(
      $node,
      'uutisputki_tyon_alla',
      WorkflowTransitionSubscriber::STATE_NEWS_PUBLISHED,
      FALSE
    );

    $this->slackNotificationService->expects($this->once())
      ->method('notifyPublished')
      ->with($node);
    $this->slackNotificationService->expects($this->never())
      ->method('notifyOikoluku');

    $this->subscriber->onPostTransition($event);
  }

  /**
   * Tests that scheduled transitions are ignored until actual execution.
   */
  public function testScheduledTransitionIsIgnored(): void {
    $node = $this->createMock(NodeInterface::class);
    $event = $this->createMockEvent(
      $node,
      'yleinen_julkaisuputki_tyon_alla',
      WorkflowTransitionSubscriber::STATE_GENERAL_PUBLISHED,
      TRUE // scheduled!
    );

    $this->slackNotificationService->expects($this->never())->method('notifyOikoluku');
    $this->slackNotificationService->expects($this->never())->method('notifyPublished');

    $this->subscriber->onPostTransition($event);
  }

  /**
   * Tests that transitions where from_sid equals to_sid are ignored.
   */
  public function testSameStateTransitionIsIgnored(): void {
    $node = $this->createMock(NodeInterface::class);
    $event = $this->createMockEvent(
      $node,
      WorkflowTransitionSubscriber::STATE_OIKOLUETTAVANA,
      WorkflowTransitionSubscriber::STATE_OIKOLUETTAVANA,
      FALSE
    );

    $this->slackNotificationService->expects($this->never())->method('notifyOikoluku');
    $this->slackNotificationService->expects($this->never())->method('notifyPublished');

    $this->subscriber->onPostTransition($event);
  }

  /**
   * Tests that transitions to other intermediate states (e.g. tyon_alla) are ignored.
   */
  public function testOtherTransitionsAreIgnored(): void {
    $node = $this->createMock(NodeInterface::class);
    $event = $this->createMockEvent(
      $node,
      'yleinen_julkaisuputki_creation',
      'yleinen_julkaisuputki_tyon_alla',
      FALSE
    );

    $this->slackNotificationService->expects($this->never())->method('notifyOikoluku');
    $this->slackNotificationService->expects($this->never())->method('notifyPublished');

    $this->subscriber->onPostTransition($event);
  }

  /**
   * Tests that transitions on non-node entities are ignored.
   */
  public function testNonNodeEntityIsIgnored(): void {
    $nonNode = $this->createMock(EntityInterface::class);
    $event = $this->createMockEvent(
      $nonNode,
      'yleinen_julkaisuputki_tyon_alla',
      WorkflowTransitionSubscriber::STATE_GENERAL_PUBLISHED,
      FALSE
    );

    $this->slackNotificationService->expects($this->never())->method('notifyOikoluku');
    $this->slackNotificationService->expects($this->never())->method('notifyPublished');

    $this->subscriber->onPostTransition($event);
  }

}

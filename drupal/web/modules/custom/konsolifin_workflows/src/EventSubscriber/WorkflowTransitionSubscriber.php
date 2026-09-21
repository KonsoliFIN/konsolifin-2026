<?php

declare(strict_types=1);

namespace Drupal\konsolifin_workflows\EventSubscriber;

use Drupal\node\NodeInterface;
use Drupal\konsolifin_workflows\SlackNotificationService;
use Drupal\workflow\Event\WorkflowEvents;
use Drupal\workflow\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to workflow state transitions and dispatches Slack notifications.
 */
class WorkflowTransitionSubscriber implements EventSubscriberInterface {

  /**
   * Target state for Oikoluku (proofreading).
   */
  public const STATE_OIKOLUETTAVANA = 'yleinen_julkaisuputki_oikoluettavana';

  /**
   * Target state for published general workflow.
   */
  public const STATE_GENERAL_PUBLISHED = 'yleinen_julkaisuputki_julkaistu';

  /**
   * Target state for published news workflow.
   */
  public const STATE_NEWS_PUBLISHED = 'uutisputki_julkaistu';

  /**
   * Constructs a WorkflowTransitionSubscriber object.
   */
  public function __construct(
    protected SlackNotificationService $slackNotificationService,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      WorkflowEvents::POST_TRANSITION => ['onPostTransition', 0],
    ];
  }

  /**
   * Responds to post-transition events.
   *
   * @param \Drupal\workflow\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function onPostTransition(WorkflowTransitionEvent $event): void {
    $transition = $event->getTransition();
    if (!$transition) {
      return;
    }

    // Ignore transitions that are merely scheduled for the future.
    if ($transition->isScheduled()) {
      return;
    }

    $entity = $transition->getTargetEntity();
    if (!$entity instanceof NodeInterface) {
      return;
    }

    $fromSid = $transition->getFromSid();
    $toSid = $transition->getToSid();

    // Only react when an actual state change has taken place.
    if ($fromSid === $toSid) {
      return;
    }

    // Trigger 1: Moved to Oikoluku (yleinen_julkaisuputki_oikoluettavana).
    if ($toSid === self::STATE_OIKOLUETTAVANA) {
      $this->slackNotificationService->notifyOikoluku($entity);
      return;
    }

    // Trigger 2: Moved to a published state (yleinen_julkaisuputki_julkaistu or uutisputki_julkaistu).
    if ($toSid === self::STATE_GENERAL_PUBLISHED || $toSid === self::STATE_NEWS_PUBLISHED) {
      $this->slackNotificationService->notifyPublished($entity);
    }
  }

}

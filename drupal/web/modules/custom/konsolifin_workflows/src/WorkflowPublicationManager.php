<?php

declare(strict_types=1);

namespace Drupal\konsolifin_workflows;

use Drupal\node\NodeInterface;

/**
 * Manages publishing status of nodes based on their workflow states.
 */
class WorkflowPublicationManager {

  /**
   * Field name for general content workflow.
   */
  public const FIELD_GENERAL = 'field_tyonkulku';

  /**
   * Published state machine name for general workflow.
   */
  public const STATE_GENERAL_PUBLISHED = 'yleinen_julkaisuputki_julkaistu';

  /**
   * Field name for news workflow.
   */
  public const FIELD_NEWS = 'field_tyonkulku_uutinen';

  /**
   * Published state machine name for news workflow.
   */
  public const STATE_NEWS_PUBLISHED = 'uutisputki_julkaistu';

  /**
   * Checks if the node has any workflow field managed by this module.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if the node has a managed workflow field, FALSE otherwise.
   */
  public function hasWorkflowField(NodeInterface $node): bool {
    return $node->hasField(self::FIELD_GENERAL) || $node->hasField(self::FIELD_NEWS);
  }

  /**
   * Determines whether a node should be published based on its workflow state.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to evaluate.
   *
   * @return bool|null
   *   TRUE if the node should be published, FALSE if unpublished,
   *   or NULL if the node is not controlled by workflow fields.
   */
  public function shouldBePublished(NodeInterface $node): ?bool {
    if ($node->hasField(self::FIELD_GENERAL)) {
      $field = $node->get(self::FIELD_GENERAL);
      $state = !$field->isEmpty() ? $field->value : NULL;
      return $state === self::STATE_GENERAL_PUBLISHED;
    }

    if ($node->hasField(self::FIELD_NEWS)) {
      $field = $node->get(self::FIELD_NEWS);
      $state = !$field->isEmpty() ? $field->value : NULL;
      return $state === self::STATE_NEWS_PUBLISHED;
    }

    return NULL;
  }

  /**
   * Synchronizes the node publishing status based on its workflow state.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node whose publishing status should be synchronized.
   *
   * @return bool
   *   TRUE if a workflow rule was applied to the node, FALSE otherwise.
   */
  public function syncPublishingStatus(NodeInterface $node): bool {
    $should_publish = $this->shouldBePublished($node);

    if ($should_publish === NULL) {
      return FALSE;
    }

    if ($should_publish) {
      $node->setPublished();
    }
    else {
      $node->setUnpublished();
    }

    return TRUE;
  }

}

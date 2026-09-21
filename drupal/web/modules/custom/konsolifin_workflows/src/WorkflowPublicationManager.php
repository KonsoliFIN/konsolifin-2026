<?php

declare(strict_types=1);

namespace Drupal\konsolifin_workflows;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\node\NodeInterface;

/**
 * Manages publishing status and publication timestamps of nodes based on workflow states.
 */
class WorkflowPublicationManager {

  /**
   * Field name for general content workflow.
   */
  public const FIELD_GENERAL = 'field_tyonkulku';

  /**
   * Unpublished ready state machine name for general workflow.
   */
  public const STATE_GENERAL_UNPUBLISHED_READY = 'yleinen_julkaisuputki_julkaisematta';

  /**
   * Published state machine name for general workflow.
   */
  public const STATE_GENERAL_PUBLISHED = 'yleinen_julkaisuputki_julkaistu';

  /**
   * Field name for news workflow.
   */
  public const FIELD_NEWS = 'field_tyonkulku_uutinen';

  /**
   * Work in progress state machine name for news workflow.
   */
  public const STATE_NEWS_WORK_IN_PROGRESS = 'uutisputki_tyon_alla';

  /**
   * Published state machine name for news workflow.
   */
  public const STATE_NEWS_PUBLISHED = 'uutisputki_julkaistu';

  /**
   * Constructs a WorkflowPublicationManager object.
   */
  public function __construct(
    protected ?TimeInterface $time = NULL,
  ) {
  }

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

  /**
   * Determines whether the node's publishing timestamp should be updated.
   *
   * Only returns TRUE in two exact situations:
   * 1. Transition from 'yleinen_julkaisuputki_julkaisematta' to 'yleinen_julkaisuputki_julkaistu'
   * 2. Transition from 'uutisputki_tyon_alla' to 'uutisputki_julkaistu'
   *
   * Saving an already published node, legacy nodes, or transitions between any other states
   * will return FALSE.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity being saved.
   *
   * @return bool
   *   TRUE if publishing timestamp should be updated, FALSE otherwise.
   */
  public function shouldUpdatePublishingTimestamp(NodeInterface $node): bool {
    // If there is no original entity (e.g. newly created node), it is not a transition
    // from julkaisematta or tyon_alla.
    $original = method_exists($node, 'getOriginal') ? $node->getOriginal() : ($node->original ?? NULL);
    if (!$original instanceof NodeInterface) {
      return FALSE;
    }

    // Case 1: General workflow (field_tyonkulku)
    // From yleinen_julkaisuputki_julkaisematta to yleinen_julkaisuputki_julkaistu
    if ($node->hasField(self::FIELD_GENERAL) && $original->hasField(self::FIELD_GENERAL)) {
      $origField = $original->get(self::FIELD_GENERAL);
      $newField = $node->get(self::FIELD_GENERAL);
      $origState = !$origField->isEmpty() ? (string) $origField->value : '';
      $newState = !$newField->isEmpty() ? (string) $newField->value : '';

      if ($origState === self::STATE_GENERAL_UNPUBLISHED_READY && $newState === self::STATE_GENERAL_PUBLISHED) {
        return TRUE;
      }
    }

    // Case 2: News workflow (field_tyonkulku_uutinen)
    // From uutisputki_tyon_alla to uutisputki_julkaistu
    if ($node->hasField(self::FIELD_NEWS) && $original->hasField(self::FIELD_NEWS)) {
      $origField = $original->get(self::FIELD_NEWS);
      $newField = $node->get(self::FIELD_NEWS);
      $origState = !$origField->isEmpty() ? (string) $origField->value : '';
      $newState = !$newField->isEmpty() ? (string) $newField->value : '';

      if ($origState === self::STATE_NEWS_WORK_IN_PROGRESS && $newState === self::STATE_NEWS_PUBLISHED) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Resolves the transition timestamp from the active workflow field or request time.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return int
   *   Unix timestamp of the transition.
   */
  public function resolveTransitionTimestamp(NodeInterface $node): int {
    foreach ([self::FIELD_GENERAL, self::FIELD_NEWS] as $fieldName) {
      if ($node->hasField($fieldName)) {
        $field = $node->get($fieldName);
        if (method_exists($field, 'getTransition')) {
          $transition = $field->getTransition();
          if ($transition && method_exists($transition, 'getTimestamp')) {
            $ts = (int) $transition->getTimestamp();
            if ($ts > 0) {
              return $ts;
            }
          }
        }
      }
    }

    return $this->time ? $this->time->getRequestTime() : time();
  }

  /**
   * Synchronizes the publishing timestamp (created time) if transition criteria are met.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity being saved.
   * @param int|null $timestamp
   *   Optional explicit timestamp. If not provided, resolves from transition or current request time.
   *
   * @return bool
   *   TRUE if timestamp was updated, FALSE otherwise.
   */
  public function syncPublishingTimestamp(NodeInterface $node, ?int $timestamp = NULL): bool {
    if (!$this->shouldUpdatePublishingTimestamp($node)) {
      return FALSE;
    }

    if ($timestamp === NULL || $timestamp <= 0) {
      $timestamp = $this->resolveTransitionTimestamp($node);
    }

    $node->setCreatedTime($timestamp);
    return TRUE;
  }

  /**
   * Removes editorial proofreading comments (<span class="sisalto-oikoluku">...</span>) from HTML.
   *
   * @param string $html
   *   The HTML string to sanitize.
   *
   * @return string
   *   The sanitized HTML string.
   */
  public function stripProofreadingHtml(string $html): string {
    if (strpos($html, 'sisalto-oikoluku') === FALSE) {
      return $html;
    }

    $dom = new \DOMDocument();
    libxml_use_internal_errors(TRUE);

    // Wrap in a div and convert encoding to preserve UTF-8 characters properly.
    $wrapped = '<?xml encoding="utf-8" ?><div>' . $html . '</div>';
    $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $xpath = new \DOMXPath($dom);
    // Query for span elements with class 'sisalto-oikoluku'.
    $spans = $xpath->query('//span[contains(concat(" ", normalize-space(@class), " "), " sisalto-oikoluku ")]');

    if ($spans && $spans->length > 0) {
      $to_remove = [];
      foreach ($spans as $span) {
        $to_remove[] = $span;
      }
      foreach ($to_remove as $span) {
        if ($span->parentNode) {
          $span->parentNode->removeChild($span);
        }
      }

      $root = $dom->getElementsByTagName('div')->item(0);
      $new_html = '';
      if ($root) {
        foreach ($root->childNodes as $child) {
          $new_html .= $dom->saveHTML($child);
        }
      }
      libxml_clear_errors();
      return $new_html;
    }

    libxml_clear_errors();
    return $html;
  }

  /**
   * Removes editorial proofreading comments from the node's body and summary if published.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity being saved.
   *
   * @return bool
   *   TRUE if comments were found and stripped, FALSE otherwise.
   */
  public function stripProofreadingComments(NodeInterface $node): bool {
    if (!$node->isPublished() || !$node->hasField('body') || $node->get('body')->isEmpty()) {
      return FALSE;
    }

    $body_field = $node->get('body');
    $html = (string) $body_field->value;
    $summary = isset($body_field->summary) ? (string) $body_field->summary : NULL;

    $has_in_body = strpos($html, 'sisalto-oikoluku') !== FALSE;
    $has_in_summary = $summary !== NULL && strpos($summary, 'sisalto-oikoluku') !== FALSE;

    if (!$has_in_body && !$has_in_summary) {
      return FALSE;
    }

    $cleaned_html = $has_in_body ? $this->stripProofreadingHtml($html) : $html;
    $cleaned_summary = $has_in_summary ? $this->stripProofreadingHtml($summary) : $summary;

    if ($cleaned_html !== $html || $cleaned_summary !== $summary) {
      $values = [
        'value' => $cleaned_html,
        'format' => $body_field->format,
      ];
      if ($summary !== NULL) {
        $values['summary'] = $cleaned_summary;
      }
      $node->set('body', $values);
      return TRUE;
    }

    return FALSE;
  }

}


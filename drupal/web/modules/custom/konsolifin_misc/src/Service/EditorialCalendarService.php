<?php

namespace Drupal\konsolifin_misc\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;

/**
 * Service for editorial calendar data and scheduling calculations.
 */
class EditorialCalendarService
{

  use StringTranslationTrait;

  /**
   * Published state machine name for general workflow.
   */
  public const STATE_GENERAL_PUBLISHED = 'yleinen_julkaisuputki_julkaistu';

  /**
   * Published state machine name for news workflow.
   */
  public const STATE_NEWS_PUBLISHED = 'uutisputki_julkaistu';

  /**
   * Rejected state machine name for general workflow.
   */
  public const STATE_GENERAL_REJECTED = 'yleinen_julkaisuputki_hylatty';

  /**
   * Rejected state machine name for news workflow.
   */
  public const STATE_NEWS_REJECTED = 'uutisputki_hylatty';

  /**
   * Constructs an EditorialCalendarService object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DateFormatterInterface $dateFormatter,
    protected TimeInterface $time,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Returns workflow state IDs that represent published content.
   *
   * @return string[]
   */
  public function getPublishedWorkflowStates(): array
  {
    return [
      self::STATE_GENERAL_PUBLISHED,
      self::STATE_NEWS_PUBLISHED,
    ];
  }

  /**
   * Returns workflow state IDs that represent rejected content.
   *
   * @return string[]
   */
  public function getRejectedWorkflowStates(): array
  {
    return [
      self::STATE_GENERAL_REJECTED,
      self::STATE_NEWS_REJECTED,
    ];
  }

  /**
   * Gets the configured site timezone.
   */
  public function getTimezone(): \DateTimeZone
  {
    $timezoneName = $this->configFactory->get('system.date')->get('timezone.default');
    if (empty($timezoneName)) {
      $timezoneName = @date_default_timezone_get() ?: 'Europe/Helsinki';
    }
    return new \DateTimeZone($timezoneName);
  }

  /**
   * Builds calendar data for the current and next calendar week.
   *
   * @param array|null $bundles
   *   Optional list of node bundle machine names to filter by.
   * @param int|null $referenceTimestamp
   *   Optional timestamp to treat as 'now' (useful for testing).
   *
   * @return array
   *   Calendar data array containing weeks, unscheduled content, and metadata.
   */
  public function getCalendarData(?array $bundles = NULL, ?int $referenceTimestamp = NULL): array
  {
    $tz = $this->getTimezone();
    $timestamp = $referenceTimestamp ?? $this->time->getRequestTime();
    $now = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($tz);

    // Current week Monday (ISO-8601: 1 = Monday, 7 = Sunday).
    $dayOfWeek = (int) $now->format('N');
    $currentWeekMonday = $now->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
    $nextWeekSunday = $currentWeekMonday->modify('+13 days')->setTime(23, 59, 59);

    $startTs = $currentWeekMonday->getTimestamp();
    $endTs = $nextWeekSunday->getTimestamp();

    // Initialize 14-day map.
    $daysMap = [];
    $shortDayNames = [
      1 => 'Ma',
      2 => 'Ti',
      3 => 'Ke',
      4 => 'To',
      5 => 'Pe',
      6 => 'La',
      7 => 'Su',
    ];
    $fullDayNames = [
      1 => $this->t('Maanantai'),
      2 => $this->t('Tiistai'),
      3 => $this->t('Keskiviikko'),
      4 => $this->t('Torstai'),
      5 => $this->t('Perjantai'),
      6 => $this->t('Lauantai'),
      7 => $this->t('Sunnuntai'),
    ];

    for ($i = 0; $i < 14; $i++) {
      $dateObj = $currentWeekMonday->modify("+{$i} days");
      $dateStr = $dateObj->format('Y-m-d');
      $dayN = (int) $dateObj->format('N');

      $daysMap[$dateStr] = [
        'date' => $dateStr,
        'day_number' => (int) $dateObj->format('j'),
        'month_number' => (int) $dateObj->format('n'),
        'formatted_date' => $dateObj->format('j.n.'),
        'day_name_short' => $shortDayNames[$dayN] ?? '',
        'day_name_full' => $fullDayNames[$dayN] ?? '',
        'is_today' => ($dateStr === $now->format('Y-m-d')),
        'is_past' => ($dateStr < $now->format('Y-m-d')),
        'is_weekend' => ($dayN >= 6),
        'items' => [],
      ];
    }

    // Query scheduled content within the 2-week window.
    $scheduledItems = $this->queryScheduledItems($startTs, $endTs, $bundles);
    $totalScheduledCount = count($scheduledItems);

    foreach ($scheduledItems as $scheduledItem) {
      $node = $scheduledItem['node'];
      $scheduledTs = $scheduledItem['timestamp'];
      $scheduledDateTime = (new \DateTimeImmutable('@' . $scheduledTs))->setTimezone($tz);
      $dateKey = $scheduledDateTime->format('Y-m-d');

      $item = $this->formatNodeItem($node, $scheduledDateTime);

      if (isset($daysMap[$dateKey])) {
        $daysMap[$dateKey]['items'][] = $item;
      }
    }

    // Split days into Week 1 (Current) and Week 2 (Next).
    $allDays = array_values($daysMap);
    $week1Days = array_slice($allDays, 0, 7);
    $week2Days = array_slice($allDays, 7, 7);

    $nextWeekMonday = $currentWeekMonday->modify('+7 days');

    $weeks = [
      'current' => [
        'id' => 'current',
        'title' => $this->t('Kuluva viikko (vko @num)', ['@num' => $currentWeekMonday->format('W')]),
        'week_number' => (int) $currentWeekMonday->format('W'),
        'year' => (int) $currentWeekMonday->format('o'),
        'is_current_week' => TRUE,
        'days' => $week1Days,
      ],
      'next' => [
        'id' => 'next',
        'title' => $this->t('Seuraava viikko (vko @num)', ['@num' => $nextWeekMonday->format('W')]),
        'week_number' => (int) $nextWeekMonday->format('W'),
        'year' => (int) $nextWeekMonday->format('o'),
        'is_current_week' => FALSE,
        'days' => $week2Days,
      ],
    ];

    // Query non-scheduled and non-published content.
    $unscheduledNodes = $this->queryUnscheduledNodes($bundles, array_keys($scheduledItems));
    $unscheduledContent = [];
    foreach ($unscheduledNodes as $node) {
      $unscheduledContent[] = $this->formatNodeItem($node);
    }

    // Query content scheduled beyond next week.
    $laterScheduledItems = $this->queryScheduledItems($endTs + 1, NULL, $bundles);
    $laterScheduledContent = [];
    foreach ($laterScheduledItems as $laterItem) {
      $node = $laterItem['node'];
      $scheduledTs = $laterItem['timestamp'];
      $scheduledDateTime = (new \DateTimeImmutable('@' . $scheduledTs))->setTimezone($tz);
      $laterScheduledContent[] = $this->formatNodeItem($node, $scheduledDateTime);
    }

    // Query overdue scheduled items (scheduled before current week Monday but still unpublished).
    $overdueScheduledItems = $this->queryScheduledItems(NULL, $startTs - 1, $bundles);
    $overdueScheduledContent = [];
    foreach ($overdueScheduledItems as $overdueItem) {
      $node = $overdueItem['node'];
      $scheduledTs = $overdueItem['timestamp'];
      $scheduledDateTime = (new \DateTimeImmutable('@' . $scheduledTs))->setTimezone($tz);
      $overdueScheduledContent[] = $this->formatNodeItem($node, $scheduledDateTime);
    }

    return [
      'weeks' => $weeks,
      'unscheduled_content' => $unscheduledContent,
      'later_scheduled_content' => $laterScheduledContent,
      'overdue_scheduled_content' => $overdueScheduledContent,
      'total_scheduled_count' => $totalScheduledCount,
      'total_unscheduled_count' => count($unscheduledContent),
      'current_time_formatted' => $this->dateFormatter->format($timestamp, 'short'),
      'timezone' => $tz->getName(),
    ];
  }

  /**
   * Queries scheduled items between optional timestamps.
   *
   * Combines workflow scheduled transitions targeting published states and
   * any non-workflow nodes scheduled via Scheduler publish_on.
   *
   * @param int|null $minTs
   *   Optional minimum timestamp (inclusive).
   * @param int|null $maxTs
   *   Optional maximum timestamp (inclusive).
   * @param array|null $bundles
   *   Optional bundle filter.
   *
   * @return array<int, array{node: \Drupal\node\NodeInterface, timestamp: int}>
   *   Array of scheduled items keyed by node ID.
   */
  protected function queryScheduledItems(?int $minTs, ?int $maxTs, ?array $bundles = NULL): array
  {
    $items = [];
    $seenNids = [];

    // 1. Query workflow scheduled transitions targeting published status.
    try {
      $transitionStorage = $this->entityTypeManager->getStorage('workflow_scheduled_transition');
      if ($transitionStorage) {
        $query = $transitionStorage->getQuery()
          ->accessCheck(FALSE)
          ->condition('entity_type', 'node')
          ->condition('to_sid', $this->getPublishedWorkflowStates(), 'IN');

        if ($minTs !== NULL) {
          $query->condition('timestamp', $minTs, '>=');
        }
        if ($maxTs !== NULL) {
          $query->condition('timestamp', $maxTs, '<=');
        }

        $query->sort('timestamp', 'ASC');
        $tids = $query->execute();

        if (!empty($tids)) {
          $transitions = $transitionStorage->loadMultiple($tids);
          foreach ($transitions as $transition) {
            $node = $transition->getTargetEntity();
            if (!$node instanceof NodeInterface || $node->isPublished()) {
              continue;
            }
            $nid = (int) $node->id();
            if (isset($seenNids[$nid])) {
              continue;
            }
            if (!empty($bundles) && !in_array($node->bundle(), $bundles, TRUE)) {
              continue;
            }

            $ts = (int) $transition->getTimestamp();
            $seenNids[$nid] = $nid;
            $items[$nid] = [
              'node' => $node,
              'timestamp' => $ts,
            ];
          }
        }
      }
    }
    catch (\Exception $e) {
      // Storage not available or not configured yet.
    }

    // 2. Query nodes scheduled via publish_on (for non-workflow nodes or fallback).
    try {
      $nodeStorage = $this->entityTypeManager->getStorage('node');
      if ($nodeStorage) {
        $query = $nodeStorage->getQuery()
          ->accessCheck(FALSE)
          ->condition('status', 0);

        if ($minTs !== NULL) {
          $query->condition('publish_on', $minTs, '>=');
        }
        else {
          $query->condition('publish_on', 0, '>');
        }

        if ($maxTs !== NULL) {
          $query->condition('publish_on', $maxTs, '<=');
        }

        if (!empty($bundles)) {
          $query->condition('type', $bundles, 'IN');
        }

        $query->sort('publish_on', 'ASC');
        $nids = $query->execute();

        if (!empty($nids)) {
          $nodes = $nodeStorage->loadMultiple($nids);
          foreach ($nodes as $node) {
            $nid = (int) $node->id();
            if (isset($seenNids[$nid])) {
              continue;
            }
            // For workflow-managed content, only workflow scheduled transitions to published status count.
            if ($node->hasField('field_tyonkulku') || $node->hasField('field_tyonkulku_uutinen')) {
              continue;
            }

            $publishOn = (int) $node->get('publish_on')->value;
            $seenNids[$nid] = $nid;
            $items[$nid] = [
              'node' => $node,
              'timestamp' => $publishOn,
            ];
          }
        }
      }
    }
    catch (\Exception $e) {
    }

    // Sort items by timestamp ASC.
    uasort($items, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
    return $items;
  }

  /**
   * Queries unpublished nodes scheduled for publication between timestamps.
   *
   * @param int $startTs
   *   Start timestamp.
   * @param int $endTs
   *   End timestamp.
   * @param array|null $bundles
   *   Optional bundle filter.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of loaded node entities.
   */
  protected function queryScheduledNodes(int $startTs, int $endTs, ?array $bundles = NULL): array
  {
    $items = $this->queryScheduledItems($startTs, $endTs, $bundles);
    return array_column($items, 'node');
  }

  /**
   * Queries unpublished nodes that have no scheduled publication date.
   *
   * Excludes any nodes currently in 'Hylätty' state and any nodes scheduled to be published.
   *
   * @param array|null $bundles
   *   Optional bundle filter.
   * @param array $excludedNids
   *   Optional array of node IDs to exclude (e.g. scheduled items).
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of loaded node entities.
   */
  protected function queryUnscheduledNodes(?array $bundles = NULL, array $excludedNids = []): array
  {
    $storage = $this->entityTypeManager->getStorage('node');
    if (!$storage) {
      return [];
    }

    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 0);

    if (!empty($excludedNids)) {
      $query->condition('nid', $excludedNids, 'NOT IN');
    }

    // Also condition on publish_on = 0 or missing for non-workflow nodes.
    $orGroup = $query->orConditionGroup()
      ->notExists('publish_on')
      ->condition('publish_on', 0);
    $query->condition($orGroup);

    if (!empty($bundles)) {
      $query->condition('type', $bundles, 'IN');
    }

    $query->sort('changed', 'DESC');

    $nids = $query->execute();
    $nodes = !empty($nids) ? $storage->loadMultiple($nids) : [];

    // Filter out any nodes that might be in Hylätty state in memory.
    $filteredNodes = [];
    $rejectedStates = $this->getRejectedWorkflowStates();
    foreach ($nodes as $node) {
      $state = $this->getNodeWorkflowStateId($node);
      if ($state && in_array($state, $rejectedStates, TRUE)) {
        continue;
      }
      $filteredNodes[] = $node;
    }

    return $filteredNodes;
  }

  /**
   * Queries unpublished nodes scheduled after the 2-week window.
   *
   * @param int $endTs
   *   End of the 2-week window timestamp.
   * @param array|null $bundles
   *   Optional bundle filter.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of loaded node entities.
   */
  protected function queryLaterScheduledNodes(int $endTs, ?array $bundles = NULL): array
  {
    $items = $this->queryScheduledItems($endTs + 1, NULL, $bundles);
    return array_column($items, 'node');
  }

  /**
   * Queries overdue scheduled nodes (scheduled before window but still unpublished).
   *
   * @param int $startTs
   *   Start of the 2-week window timestamp.
   * @param array|null $bundles
   *   Optional bundle filter.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of loaded node entities.
   */
  protected function queryOverdueScheduledNodes(int $startTs, ?array $bundles = NULL): array
  {
    $items = $this->queryScheduledItems(NULL, $startTs - 1, $bundles);
    return array_column($items, 'node');
  }

  /**
   * Retrieves the current workflow state ID of a node, if any.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string|null
   *   The workflow state machine name, or NULL if none.
   */
  public function getNodeWorkflowStateId(NodeInterface $node): ?string
  {
    if ($node->hasField('field_tyonkulku') && !$node->get('field_tyonkulku')->isEmpty()) {
      return (string) $node->get('field_tyonkulku')->value;
    }
    if ($node->hasField('field_tyonkulku_uutinen') && !$node->get('field_tyonkulku_uutinen')->isEmpty()) {
      return (string) $node->get('field_tyonkulku_uutinen')->value;
    }
    return NULL;
  }

  /**
   * Retrieves the human-readable workflow state label of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   The workflow state label, or empty string if no workflow.
   */
  public function getNodeWorkflowStateLabel(NodeInterface $node): string
  {
    $stateId = $this->getNodeWorkflowStateId($node);
    if (!empty($stateId)) {
      try {
        $stateStorage = $this->entityTypeManager->getStorage('workflow_state');
        if ($stateStorage) {
          $stateEntity = $stateStorage->load($stateId);
          if ($stateEntity) {
            return (string) $stateEntity->label();
          }
        }
      }
      catch (\Exception $e) {
      }
      return $stateId;
    }

    if ($node->hasField('field_tyonkulku') || $node->hasField('field_tyonkulku_uutinen')) {
      return (string) $this->t('Ei määritelty');
    }

    return '';
  }

  /**
   * Formats a node entity into a structured item array for template rendering.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   * @param \DateTimeImmutable|null $scheduledDateTime
   *   The scheduled DateTime object, if scheduled.
   *
   * @return array
   *   Structured node data.
   */
  public function formatNodeItem(NodeInterface $node, ?\DateTimeImmutable $scheduledDateTime = NULL): array
  {
    $bundle = $node->bundle();
    $bundleLabel = $bundle;

    $bundleEntity = $this->entityTypeManager->getStorage('node_type')->load($bundle);
    if ($bundleEntity) {
      $bundleLabel = $bundleEntity->label();
    }

    $authorName = '';
    $owner = $node->getOwner();
    if ($owner) {
      $authorName = $owner->getDisplayName();
    }

    $url = '';
    $editUrl = '';
    try {
      if ($node->hasLinkTemplate('canonical')) {
        $url = $node->toUrl('canonical')->toString();
      }
      if ($node->hasLinkTemplate('edit-form')) {
        $editUrl = $node->toUrl('edit-form')->toString();
      }
    } catch (\Exception $e) {
      // Fallback if routes cannot be generated.
    }

    $scheduledTs = $scheduledDateTime
      ? $scheduledDateTime->getTimestamp()
      : ($node->hasField('publish_on') ? (int) $node->get('publish_on')->value : 0);

    $workflowStateId = $this->getNodeWorkflowStateId($node) ?? '';
    $workflowStateLabel = $this->getNodeWorkflowStateLabel($node);
    $hasWorkflow = $node->hasField('field_tyonkulku') || $node->hasField('field_tyonkulku_uutinen');

    return [
      'id' => (int) $node->id(),
      'title' => $node->getTitle(),
      'bundle' => $bundle,
      'bundle_label' => $bundleLabel,
      'author' => $authorName,
      'author_id' => (int) $node->getOwnerId(),
      'created' => (int) $node->getCreatedTime(),
      'created_formatted' => $this->dateFormatter->format($node->getCreatedTime(), 'short'),
      'changed' => (int) $node->getChangedTime(),
      'changed_formatted' => $this->dateFormatter->format($node->getChangedTime(), 'short'),
      'publish_on' => $scheduledTs,
      'publish_time' => $scheduledDateTime ? $scheduledDateTime->format('H:i') : ($scheduledTs > 0 ? $this->dateFormatter->format($scheduledTs, 'custom', 'H:i') : ''),
      'publish_date_formatted' => $scheduledTs > 0 ? $this->dateFormatter->format($scheduledTs, 'short') : '',
      'workflow_state_id' => $workflowStateId,
      'workflow_state_label' => $workflowStateLabel,
      'has_workflow' => $hasWorkflow,
      'url' => $url,
      'edit_url' => $editUrl,
    ];
  }

}

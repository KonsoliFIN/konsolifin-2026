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
    $scheduledNodes = $this->queryScheduledNodes($startTs, $endTs, $bundles);
    $totalScheduledCount = count($scheduledNodes);

    foreach ($scheduledNodes as $node) {
      $publishOn = (int) $node->get('publish_on')->value;
      $scheduledDateTime = (new \DateTimeImmutable('@' . $publishOn))->setTimezone($tz);
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
    $unscheduledNodes = $this->queryUnscheduledNodes($bundles);
    $unscheduledContent = [];
    foreach ($unscheduledNodes as $node) {
      $unscheduledContent[] = $this->formatNodeItem($node);
    }

    // Query content scheduled beyond next week.
    $laterNodes = $this->queryLaterScheduledNodes($endTs, $bundles);
    $laterScheduledContent = [];
    foreach ($laterNodes as $node) {
      $publishOn = (int) $node->get('publish_on')->value;
      $scheduledDateTime = (new \DateTimeImmutable('@' . $publishOn))->setTimezone($tz);
      $laterScheduledContent[] = $this->formatNodeItem($node, $scheduledDateTime);
    }

    // Query overdue scheduled items (scheduled before current week Monday but still unpublished).
    $overdueNodes = $this->queryOverdueScheduledNodes($startTs, $bundles);
    $overdueScheduledContent = [];
    foreach ($overdueNodes as $node) {
      $publishOn = (int) $node->get('publish_on')->value;
      $scheduledDateTime = (new \DateTimeImmutable('@' . $publishOn))->setTimezone($tz);
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
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 0)
      ->condition('publish_on', $startTs, '>=')
      ->condition('publish_on', $endTs, '<=')
      ->sort('publish_on', 'ASC');

    if (!empty($bundles)) {
      $query->condition('type', $bundles, 'IN');
    }

    $nids = $query->execute();
    return !empty($nids) ? $storage->loadMultiple($nids) : [];
  }

  /**
   * Queries unpublished nodes that have no scheduled publication date.
   *
   * @param array|null $bundles
   *   Optional bundle filter.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Array of loaded node entities.
   */
  protected function queryUnscheduledNodes(?array $bundles = NULL): array
  {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 0);

    $orGroup = $query->orConditionGroup()
      ->notExists('publish_on')
      ->condition('publish_on', 0);
    $query->condition($orGroup);

    if (!empty($bundles)) {
      $query->condition('type', $bundles, 'IN');
    }

    $query->sort('changed', 'DESC');

    $nids = $query->execute();
    return !empty($nids) ? $storage->loadMultiple($nids) : [];
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
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 0)
      ->condition('publish_on', $endTs, '>')
      ->sort('publish_on', 'ASC');

    if (!empty($bundles)) {
      $query->condition('type', $bundles, 'IN');
    }

    $nids = $query->execute();
    return !empty($nids) ? $storage->loadMultiple($nids) : [];
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
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 0)
      ->condition('publish_on', 0, '>')
      ->condition('publish_on', $startTs, '<')
      ->sort('publish_on', 'ASC');

    if (!empty($bundles)) {
      $query->condition('type', $bundles, 'IN');
    }

    $nids = $query->execute();
    return !empty($nids) ? $storage->loadMultiple($nids) : [];
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
  protected function formatNodeItem(NodeInterface $node, ?\DateTimeImmutable $scheduledDateTime = NULL): array
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

    $publishOn = $node->hasField('publish_on') ? (int) $node->get('publish_on')->value : 0;

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
      'publish_on' => $publishOn,
      'publish_time' => $scheduledDateTime ? $scheduledDateTime->format('H:i') : ($publishOn > 0 ? $this->dateFormatter->format($publishOn, 'custom', 'H:i') : ''),
      'publish_date_formatted' => $publishOn > 0 ? $this->dateFormatter->format($publishOn, 'short') : '',
      'url' => $url,
      'edit_url' => $editUrl,
    ];
  }

}

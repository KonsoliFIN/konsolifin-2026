<?php

namespace Drupal\Tests\konsolifin_misc\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\konsolifin_misc\Service\EditorialCalendarService;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EditorialCalendarService.
 *
 * @coversDefaultClass \Drupal\konsolifin_misc\Service\EditorialCalendarService
 */
#[AllowMockObjectsWithoutExpectations]
class EditorialCalendarServiceTest extends TestCase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityStorageInterface $nodeStorage;
  protected EntityStorageInterface $nodeTypeStorage;
  protected DateFormatterInterface $dateFormatter;
  protected TimeInterface $time;
  protected ConfigFactoryInterface $configFactory;
  protected ImmutableConfig $dateConfig;

  protected function setUp(): void {
    parent::setUp();

    $string_translation = new class implements \Drupal\Core\StringTranslation\TranslationInterface {
      public function translate($string, array $args = [], array $options = []) {
        return $string;
      }
      public function translateString(\Drupal\Core\StringTranslation\TranslatableMarkup $translated_string) {
        $string = $translated_string->getUntranslatedString();
        $args = $translated_string->getArguments();
        foreach ($args as $key => $val) {
          if ($val instanceof \Drupal\Core\StringTranslation\TranslatableMarkup) {
            $val = (string) $val;
          }
          $args[$key] = $val;
        }
        return strtr($string, $args);
      }
      public function formatPlural($count, $singular, $plural, array $args = [], array $options = []) {
        return $count == 1 ? $singular : $plural;
      }
    };

    if (\Drupal::hasContainer()) {
      $container = \Drupal::getContainer();
    }
    else {
      $container = new \Drupal\Core\DependencyInjection\ContainerBuilder();
      \Drupal::setContainer($container);
    }
    $container->set('string_translation', $string_translation);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->nodeStorage = $this->createMock(EntityStorageInterface::class);
    $this->nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $this->dateFormatter = $this->createMock(DateFormatterInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->dateConfig = $this->createMock(ImmutableConfig::class);

    $this->entityTypeManager->method('getStorage')
      ->willReturnCallback(function ($entity_type) {
        if ($entity_type === 'node') {
          return $this->nodeStorage;
        }
        if ($entity_type === 'node_type') {
          return $this->nodeTypeStorage;
        }
        return NULL;
      });

    $this->configFactory->method('get')
      ->with('system.date')
      ->willReturn($this->dateConfig);

    $this->dateConfig->method('get')
      ->with('timezone.default')
      ->willReturn('Europe/Helsinki');

    $this->dateFormatter->method('format')
      ->willReturnCallback(function ($timestamp, $type = 'medium', $format = '', $timezone = NULL) {
        $tz = new \DateTimeZone($timezone ?? 'Europe/Helsinki');
        $dt = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($tz);
        if ($type === 'custom') {
          return $dt->format($format);
        }
        return $dt->format('d.m.Y H:i');
      });
  }

  /**
   * Tests 2-week calendar date generation and week grouping.
   */
  public function testTwoWeekStructure(): void {
    // Reference date: Thursday, 17 Sep 2026 14:00:00 EEST (UTC+3)
    $tz = new \DateTimeZone('Europe/Helsinki');
    $refDate = new \DateTimeImmutable('2026-09-17 14:00:00', $tz);
    $refTimestamp = $refDate->getTimestamp();

    $this->time->method('getRequestTime')->willReturn($refTimestamp);

    // Mock empty node queries
    $emptyQuery = $this->createMock(QueryInterface::class);
    $emptyQuery->method('accessCheck')->willReturnSelf();
    $emptyQuery->method('condition')->willReturnSelf();
    $emptyQuery->method('sort')->willReturnSelf();
    $emptyQuery->method('orConditionGroup')->willReturn($emptyQuery);
    $emptyQuery->method('notExists')->willReturnSelf();
    $emptyQuery->method('execute')->willReturn([]);

    $this->nodeStorage->method('getQuery')->willReturn($emptyQuery);

    $service = new EditorialCalendarService(
      $this->entityTypeManager,
      $this->dateFormatter,
      $this->time,
      $this->configFactory,
    );

    $data = $service->getCalendarData(NULL, $refTimestamp);

    // Assert weeks structure
    $this->assertArrayHasKey('current', $data['weeks']);
    $this->assertArrayHasKey('next', $data['weeks']);

    $currentWeek = $data['weeks']['current'];
    $nextWeek = $data['weeks']['next'];

    $this->assertEquals(38, $currentWeek['week_number']);
    $this->assertEquals(39, $nextWeek['week_number']);
    $this->assertTrue($currentWeek['is_current_week']);
    $this->assertFalse($nextWeek['is_current_week']);

    // Each week must have 7 days
    $this->assertCount(7, $currentWeek['days']);
    $this->assertCount(7, $nextWeek['days']);

    // Current week starts on Monday 14.9. and ends on Sunday 20.9.
    $this->assertEquals('2026-09-14', $currentWeek['days'][0]['date']);
    $this->assertEquals('Ma', (string) $currentWeek['days'][0]['day_name_short']);
    $this->assertTrue($currentWeek['days'][0]['is_past']);
    $this->assertFalse($currentWeek['days'][0]['is_today']);

    // Today is Thursday 17.9. (index 3)
    $today = $currentWeek['days'][3];
    $this->assertEquals('2026-09-17', $today['date']);
    $this->assertEquals('To', (string) $today['day_name_short']);
    $this->assertTrue($today['is_today']);
    $this->assertFalse($today['is_past']);

    // Saturday 19.9. is weekend
    $this->assertTrue($currentWeek['days'][5]['is_weekend']);
    $this->assertTrue($currentWeek['days'][6]['is_weekend']);

    // Next week starts on Monday 21.9. and ends on Sunday 27.9.
    $this->assertEquals('2026-09-21', $nextWeek['days'][0]['date']);
    $this->assertEquals('2026-09-27', $nextWeek['days'][6]['date']);
    $this->assertFalse($nextWeek['days'][0]['is_past']);
  }

  /**
   * Tests placement of scheduled nodes and listing of unscheduled nodes.
   */
  public function testScheduledAndUnscheduledItems(): void {
    $tz = new \DateTimeZone('Europe/Helsinki');
    $refDate = new \DateTimeImmutable('2026-09-17 12:00:00', $tz);
    $refTimestamp = $refDate->getTimestamp();

    // Node 1: Scheduled for Friday 18.9. at 11:00
    $scheduledTs1 = (new \DateTimeImmutable('2026-09-18 11:00:00', $tz))->getTimestamp();
    $mockNode1 = $this->createMockNode(101, 'Scheduled News', 'uutinen', 'Uutinen', $scheduledTs1, 0);

    // Node 2: Scheduled for next week Tuesday 22.9. at 15:30
    $scheduledTs2 = (new \DateTimeImmutable('2026-09-22 15:30:00', $tz))->getTimestamp();
    $mockNode2 = $this->createMockNode(102, 'Next Week Review', 'peliarvostelu', 'Peliarvostelu', $scheduledTs2, 0);

    // Node 3: Unscheduled draft
    $mockNode3 = $this->createMockNode(103, 'Unscheduled Article Draft', 'article', 'Artikkeli', 0, 0);

    $queryCallCount = 0;
    $this->nodeStorage->method('getQuery')->willReturnCallback(function () use (&$queryCallCount) {
      $queryCallCount++;
      $q = $this->createMock(QueryInterface::class);
      $q->method('accessCheck')->willReturnSelf();
      $q->method('condition')->willReturnSelf();
      $q->method('sort')->willReturnSelf();
      $q->method('orConditionGroup')->willReturn($q);
      $q->method('notExists')->willReturnSelf();

      // Call 1: Scheduled nodes in 2-week window -> [101, 102]
      if ($queryCallCount === 1) {
        $q->method('execute')->willReturn([101, 102]);
      }
      // Call 2: Unscheduled nodes -> [103]
      elseif ($queryCallCount === 2) {
        $q->method('execute')->willReturn([103]);
      }
      // Call 3: Later scheduled nodes -> []
      // Call 4: Overdue scheduled nodes -> []
      else {
        $q->method('execute')->willReturn([]);
      }
      return $q;
    });

    $this->nodeStorage->method('loadMultiple')->willReturnCallback(function ($ids) use ($mockNode1, $mockNode2, $mockNode3) {
      $result = [];
      foreach ($ids as $id) {
        if ($id === 101) {
          $result[101] = $mockNode1;
        }
        if ($id === 102) {
          $result[102] = $mockNode2;
        }
        if ($id === 103) {
          $result[103] = $mockNode3;
        }
      }
      return $result;
    });

    $service = new EditorialCalendarService(
      $this->entityTypeManager,
      $this->dateFormatter,
      $this->time,
      $this->configFactory,
    );

    $data = $service->getCalendarData(NULL, $refTimestamp);

    // Total counts
    $this->assertEquals(2, $data['total_scheduled_count']);
    $this->assertEquals(1, $data['total_unscheduled_count']);

    // Check Node 1 is on Friday 18.9. in current week
    $friday = $data['weeks']['current']['days'][4];
    $this->assertEquals('2026-09-18', $friday['date']);
    $this->assertCount(1, $friday['items']);
    $this->assertEquals('Scheduled News', $friday['items'][0]['title']);
    $this->assertEquals('11:00', $friday['items'][0]['publish_time']);
    $this->assertEquals('Uutinen', $friday['items'][0]['bundle_label']);

    // Check Node 2 is on Tuesday 22.9. in next week
    $nextTuesday = $data['weeks']['next']['days'][1];
    $this->assertEquals('2026-09-22', $nextTuesday['date']);
    $this->assertCount(1, $nextTuesday['items']);
    $this->assertEquals('Next Week Review', $nextTuesday['items'][0]['title']);
    $this->assertEquals('15:30', $nextTuesday['items'][0]['publish_time']);

    // Check unscheduled list contains Node 3
    $this->assertCount(1, $data['unscheduled_content']);
    $this->assertEquals('Unscheduled Article Draft', $data['unscheduled_content'][0]['title']);
  }

  /**
   * Tests later scheduled and overdue scheduled content handling.
   */
  public function testLaterAndOverdueItems(): void {
    $tz = new \DateTimeZone('Europe/Helsinki');
    $refDate = new \DateTimeImmutable('2026-09-17 12:00:00', $tz);
    $refTimestamp = $refDate->getTimestamp();

    // Node later: Scheduled for 3 weeks from now (October 2026)
    $laterTs = (new \DateTimeImmutable('2026-10-05 12:00:00', $tz))->getTimestamp();
    $mockLaterNode = $this->createMockNode(201, 'Far Future Blog', 'blogi', 'Blogi', $laterTs, 0);

    // Node overdue: Scheduled for last week but still unpublished
    $overdueTs = (new \DateTimeImmutable('2026-09-08 09:00:00', $tz))->getTimestamp();
    $mockOverdueNode = $this->createMockNode(202, 'Missed Publication News', 'uutinen', 'Uutinen', $overdueTs, 0);

    $queryCallCount = 0;
    $this->nodeStorage->method('getQuery')->willReturnCallback(function () use (&$queryCallCount) {
      $queryCallCount++;
      $q = $this->createMock(QueryInterface::class);
      $q->method('accessCheck')->willReturnSelf();
      $q->method('condition')->willReturnSelf();
      $q->method('sort')->willReturnSelf();
      $q->method('orConditionGroup')->willReturn($q);
      $q->method('notExists')->willReturnSelf();

      if ($queryCallCount === 1) {
        // Scheduled within window -> none
        $q->method('execute')->willReturn([]);
      }
      elseif ($queryCallCount === 2) {
        // Unscheduled -> none
        $q->method('execute')->willReturn([]);
      }
      elseif ($queryCallCount === 3) {
        // Later scheduled -> [201]
        $q->method('execute')->willReturn([201]);
      }
      elseif ($queryCallCount === 4) {
        // Overdue scheduled -> [202]
        $q->method('execute')->willReturn([202]);
      }
      return $q;
    });

    $this->nodeStorage->method('loadMultiple')->willReturnCallback(function ($ids) use ($mockLaterNode, $mockOverdueNode) {
      $result = [];
      foreach ($ids as $id) {
        if ($id === 201) {
          $result[201] = $mockLaterNode;
        }
        if ($id === 202) {
          $result[202] = $mockOverdueNode;
        }
      }
      return $result;
    });

    $service = new EditorialCalendarService(
      $this->entityTypeManager,
      $this->dateFormatter,
      $this->time,
      $this->configFactory,
    );

    $data = $service->getCalendarData(NULL, $refTimestamp);

    $this->assertCount(1, $data['later_scheduled_content']);
    $this->assertEquals('Far Future Blog', $data['later_scheduled_content'][0]['title']);
    $this->assertEquals('Blogi', $data['later_scheduled_content'][0]['bundle_label']);

    $this->assertCount(1, $data['overdue_scheduled_content']);
    $this->assertEquals('Missed Publication News', $data['overdue_scheduled_content'][0]['title']);
    $this->assertEquals('Uutinen', $data['overdue_scheduled_content'][0]['bundle_label']);
  }

  /**
   * Tests bundle filtering parameter passed to queries.
   */
  public function testBundleFiltering(): void {
    $tz = new \DateTimeZone('Europe/Helsinki');
    $refTimestamp = (new \DateTimeImmutable('2026-09-17 12:00:00', $tz))->getTimestamp();

    $conditionArgs = [];
    $mockQuery = $this->createMock(QueryInterface::class);
    $mockQuery->method('accessCheck')->willReturnSelf();
    $mockQuery->method('condition')->willReturnCallback(function (...$args) use (&$conditionArgs, $mockQuery) {
      $conditionArgs[] = $args;
      return $mockQuery;
    });
    $mockQuery->method('sort')->willReturnSelf();
    $mockQuery->method('orConditionGroup')->willReturn($mockQuery);
    $mockQuery->method('notExists')->willReturnSelf();
    $mockQuery->method('execute')->willReturn([]);

    $this->nodeStorage->method('getQuery')->willReturn($mockQuery);

    $service = new EditorialCalendarService(
      $this->entityTypeManager,
      $this->dateFormatter,
      $this->time,
      $this->configFactory,
    );

    $service->getCalendarData(['peliarvostelu', 'uutinen'], $refTimestamp);

    // Check that type condition with bundle filter was added
    $typeConditions = array_filter($conditionArgs, function ($args) {
      return isset($args[0]) && $args[0] === 'type';
    });
    $this->assertNotEmpty($typeConditions);
    $firstTypeCondition = reset($typeConditions);
    $this->assertEquals(['peliarvostelu', 'uutinen'], $firstTypeCondition[1]);
    $this->assertEquals('IN', $firstTypeCondition[2]);
  }

  /**
   * Helper to create a mock NodeInterface.
   */
  protected function createMockNode(int $id, string $title, string $bundle, string $bundleLabel, int $publishOn, int $status): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn((string) $id);
    $node->method('getTitle')->willReturn($title);
    $node->method('bundle')->willReturn($bundle);
    $node->method('getOwnerId')->willReturn('1');
    $node->method('getCreatedTime')->willReturn(1726500000);
    $node->method('getChangedTime')->willReturn(1726505000);
    $node->method('hasLinkTemplate')->willReturn(FALSE);

    $owner = $this->createMock(AccountInterface::class);
    $owner->method('getDisplayName')->willReturn('Test Writer');
    $node->method('getOwner')->willReturn($owner);

    $node->method('hasField')->willReturnCallback(function ($field) {
      return $field === 'publish_on';
    });

    $fieldItem = new \stdClass();
    $fieldItem->value = $publishOn > 0 ? (string) $publishOn : NULL;
    $fieldItemList = $this->createMock(\Drupal\Core\Field\FieldItemListInterface::class);
    $fieldItemList->method('__get')->with('value')->willReturn($fieldItem->value);

    $node->method('get')->willReturnCallback(function ($field) use ($fieldItemList) {
      if ($field === 'publish_on') {
        return $fieldItemList;
      }
      return NULL;
    });

    $this->nodeTypeStorage->method('load')
      ->willReturnCallback(function ($bundle) {
        $nt = $this->createMock(NodeTypeInterface::class);
        $labels = [
          'uutinen' => 'Uutinen',
          'peliarvostelu' => 'Peliarvostelu',
          'article' => 'Artikkeli',
        ];
        $nt->method('label')->willReturn($labels[$bundle] ?? ucfirst($bundle));
        return $nt;
      });

    return $node;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_workflows\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\konsolifin_workflows\WorkflowPublicationManager;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\workflow\Entity\WorkflowTransitionInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WorkflowPublicationManager.
 *
 * @coversDefaultClass \Drupal\konsolifin_workflows\WorkflowPublicationManager
 */
#[AllowMockObjectsWithoutExpectations]
class WorkflowPublicationManagerTest extends TestCase {

  /**
   * The manager under test.
   */
  protected WorkflowPublicationManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->manager = new WorkflowPublicationManager();
  }

  /**
   * Creates a mock node with optional field presence and value.
   *
   * @param array<string, string|null> $fields
   *   Mapping of field_name => field_value. If value is NULL, the field is treated as empty.
   *
   * @return \Drupal\node\NodeInterface&\PHPUnit\Framework\MockObject\MockObject
   */
  protected function createMockNode(array $fields = []): NodeInterface {
    $node = $this->createMock(NodeInterface::class);

    $node->method('hasField')
      ->willReturnCallback(function (string $fieldName) use ($fields): bool {
        return array_key_exists($fieldName, $fields);
      });

    $node->method('get')
      ->willReturnCallback(function (string $fieldName) use ($fields) {
        if (!array_key_exists($fieldName, $fields)) {
          throw new \InvalidArgumentException("Field $fieldName not mocked");
        }

        $fieldValue = $fields[$fieldName];
        $itemList = $this->createStub(FieldItemListInterface::class);
        $itemList->method('isEmpty')->willReturn($fieldValue === null);
        $itemList->method('__get')->willReturnCallback(function (string $prop) use ($fieldValue) {
          if ($prop === 'value') {
            return $fieldValue;
          }
          return null;
        });

        return $itemList;
      });

    return $node;
  }

  /**
   * Tests hasWorkflowField detection.
   */
  public function testHasWorkflowField(): void {
    $generalNode = $this->createMockNode([WorkflowPublicationManager::FIELD_GENERAL => 'some_state']);
    $this->assertTrue($this->manager->hasWorkflowField($generalNode));

    $newsNode = $this->createMockNode([WorkflowPublicationManager::FIELD_NEWS => 'some_state']);
    $this->assertTrue($this->manager->hasWorkflowField($newsNode));

    $plainNode = $this->createMockNode(['title' => 'Sample']);
    $this->assertFalse($this->manager->hasWorkflowField($plainNode));
  }

  /**
   * Tests evaluation of nodes with field_tyonkulku.
   */
  #[DataProvider('generalWorkflowDataProvider')]
  public function testGeneralWorkflowShouldBePublished(?string $state, bool $expected): void {
    $node = $this->createMockNode([
      WorkflowPublicationManager::FIELD_GENERAL => $state,
    ]);

    $this->assertSame($expected, $this->manager->shouldBePublished($node));
  }

  /**
   * Data provider for general workflow states.
   */
  public static function generalWorkflowDataProvider(): array {
    return [
      'published state' => ['yleinen_julkaisuputki_julkaistu', true],
      'draft state' => ['yleinen_julkaisuputki_tyon_alla', false],
      'review state' => ['yleinen_julkaisuputki_oikoluettavana', false],
      'rejected state' => ['yleinen_julkaisuputki_hylatty', false],
      'unpublish state' => ['yleinen_julkaisuputki_julkaisematta', false],
      'creation pseudo-state' => ['yleinen_julkaisuputki_creation', false],
      'empty field value' => [null, false],
    ];
  }

  /**
   * Tests evaluation of nodes with field_tyonkulku_uutinen.
   */
  #[DataProvider('newsWorkflowDataProvider')]
  public function testNewsWorkflowShouldBePublished(?string $state, bool $expected): void {
    $node = $this->createMockNode([
      WorkflowPublicationManager::FIELD_NEWS => $state,
    ]);

    $this->assertSame($expected, $this->manager->shouldBePublished($node));
  }

  /**
   * Data provider for news workflow states.
   */
  public static function newsWorkflowDataProvider(): array {
    return [
      'published state' => ['uutisputki_julkaistu', true],
      'draft state' => ['uutisputki_tyon_alla', false],
      'rejected state' => ['uutisputki_hylatty', false],
      'creation pseudo-state' => ['uutisputki_creation', false],
      'empty field value' => [null, false],
    ];
  }

  /**
   * Tests nodes with neither workflow field.
   */
  public function testNonWorkflowNode(): void {
    $node = $this->createMockNode(['title' => 'No workflow node'], true);

    $this->assertNull($this->manager->shouldBePublished($node));

    $node->expects($this->never())->method('setPublished');
    $node->expects($this->never())->method('setUnpublished');

    $this->assertFalse($this->manager->syncPublishingStatus($node));
  }

  /**
   * Tests syncPublishingStatus triggers setPublished when state is published.
   */
  public function testSyncPublishingStatusPublishes(): void {
    $node = $this->createMockNode([
      WorkflowPublicationManager::FIELD_GENERAL => WorkflowPublicationManager::STATE_GENERAL_PUBLISHED,
    ], true);

    $node->expects($this->once())->method('setPublished');
    $node->expects($this->never())->method('setUnpublished');

    $result = $this->manager->syncPublishingStatus($node);
    $this->assertTrue($result);
  }

  /**
   * Tests syncPublishingStatus triggers setUnpublished when state is not published.
   */
  public function testSyncPublishingStatusUnpublishes(): void {
    $node = $this->createMockNode([
      WorkflowPublicationManager::FIELD_NEWS => 'uutisputki_tyon_alla',
    ], true);

    $node->expects($this->never())->method('setPublished');
    $node->expects($this->once())->method('setUnpublished');

    $result = $this->manager->syncPublishingStatus($node);
    $this->assertTrue($result);
  }

  /**
   * Tests shouldUpdatePublishingTimestamp across various transitions.
   */
  #[DataProvider('timestampUpdateTransitionsProvider')]
  public function testShouldUpdatePublishingTimestamp(
    string $field,
    ?string $origState,
    ?string $newState,
    bool $expected,
  ): void {
    $original = $this->createMockNode([$field => $origState]);
    $node = $this->createMockNode([$field => $newState]);
    $node->method('getOriginal')->willReturn($original);

    $this->assertSame($expected, $this->manager->shouldUpdatePublishingTimestamp($node));
  }

  /**
   * Data provider for timestamp update transitions.
   */
  public static function timestampUpdateTransitionsProvider(): array {
    return [
      'general julkaisematta to julkaistu (MUST update)' => [
        WorkflowPublicationManager::FIELD_GENERAL,
        WorkflowPublicationManager::STATE_GENERAL_UNPUBLISHED_READY,
        WorkflowPublicationManager::STATE_GENERAL_PUBLISHED,
        true,
      ],
      'news tyon_alla to julkaistu (MUST update)' => [
        WorkflowPublicationManager::FIELD_NEWS,
        WorkflowPublicationManager::STATE_NEWS_WORK_IN_PROGRESS,
        WorkflowPublicationManager::STATE_NEWS_PUBLISHED,
        true,
      ],
      'general julkaistu to julkaistu (saving already published legacy node MUST NOT update)' => [
        WorkflowPublicationManager::FIELD_GENERAL,
        WorkflowPublicationManager::STATE_GENERAL_PUBLISHED,
        WorkflowPublicationManager::STATE_GENERAL_PUBLISHED,
        false,
      ],
      'news julkaistu to julkaistu (saving already published legacy news MUST NOT update)' => [
        WorkflowPublicationManager::FIELD_NEWS,
        WorkflowPublicationManager::STATE_NEWS_PUBLISHED,
        WorkflowPublicationManager::STATE_NEWS_PUBLISHED,
        false,
      ],
      'general tyon_alla to oikoluettavana (MUST NOT update)' => [
        WorkflowPublicationManager::FIELD_GENERAL,
        'yleinen_julkaisuputki_tyon_alla',
        'yleinen_julkaisuputki_oikoluettavana',
        false,
      ],
      'general oikoluettavana to julkaisematta (MUST NOT update)' => [
        WorkflowPublicationManager::FIELD_GENERAL,
        'yleinen_julkaisuputki_oikoluettavana',
        WorkflowPublicationManager::STATE_GENERAL_UNPUBLISHED_READY,
        false,
      ],
      'general tyon_alla to julkaistu directly (MUST NOT update)' => [
        WorkflowPublicationManager::FIELD_GENERAL,
        'yleinen_julkaisuputki_tyon_alla',
        WorkflowPublicationManager::STATE_GENERAL_PUBLISHED,
        false,
      ],
      'general julkaistu to tyon_alla unpublish (MUST NOT update)' => [
        WorkflowPublicationManager::FIELD_GENERAL,
        WorkflowPublicationManager::STATE_GENERAL_PUBLISHED,
        'yleinen_julkaisuputki_tyon_alla',
        false,
      ],
      'news creation to tyon_alla (MUST NOT update)' => [
        WorkflowPublicationManager::FIELD_NEWS,
        'uutisputki_creation',
        'uutisputki_tyon_alla',
        false,
      ],
      'news creation to julkaistu (MUST NOT update)' => [
        WorkflowPublicationManager::FIELD_NEWS,
        'uutisputki_creation',
        WorkflowPublicationManager::STATE_NEWS_PUBLISHED,
        false,
      ],
      'news julkaistu to tyon_alla unpublish (MUST NOT update)' => [
        WorkflowPublicationManager::FIELD_NEWS,
        WorkflowPublicationManager::STATE_NEWS_PUBLISHED,
        'uutisputki_tyon_alla',
        false,
      ],
    ];
  }

  /**
   * Tests that a new node without original entity does not update publishing timestamp.
   */
  public function testNewNodeWithoutOriginalDoesNotUpdateTimestamp(): void {
    $node = $this->createMockNode([
      WorkflowPublicationManager::FIELD_GENERAL => WorkflowPublicationManager::STATE_GENERAL_PUBLISHED,
    ]);
    // No original entity set.
    $this->assertFalse($this->manager->shouldUpdatePublishingTimestamp($node));
    $this->assertFalse($this->manager->syncPublishingTimestamp($node));
  }

  /**
   * Tests syncPublishingTimestamp successfully updates created time when moving julkaisematta -> julkaistu.
   */
  public function testSyncPublishingTimestampGeneralWorkflowSuccess(): void {
    $original = $this->createMockNode([
      WorkflowPublicationManager::FIELD_GENERAL => WorkflowPublicationManager::STATE_GENERAL_UNPUBLISHED_READY,
    ]);
    $node = $this->createMockNode([
      WorkflowPublicationManager::FIELD_GENERAL => WorkflowPublicationManager::STATE_GENERAL_PUBLISHED,
    ], true);
    $node->method('getOriginal')->willReturn($original);

    $expectedTs = 1789899999;
    $node->expects($this->once())
      ->method('setCreatedTime')
      ->with($expectedTs);

    $result = $this->manager->syncPublishingTimestamp($node, $expectedTs);
    $this->assertTrue($result);
  }

  /**
   * Tests syncPublishingTimestamp successfully updates created time when moving tyon_alla -> julkaistu for news.
   */
  public function testSyncPublishingTimestampNewsWorkflowSuccess(): void {
    $original = $this->createMockNode([
      WorkflowPublicationManager::FIELD_NEWS => WorkflowPublicationManager::STATE_NEWS_WORK_IN_PROGRESS,
    ]);
    $node = $this->createMockNode([
      WorkflowPublicationManager::FIELD_NEWS => WorkflowPublicationManager::STATE_NEWS_PUBLISHED,
    ], true);
    $node->method('getOriginal')->willReturn($original);

    $expectedTs = 1789898888;
    $node->expects($this->once())
      ->method('setCreatedTime')
      ->with($expectedTs);

    $result = $this->manager->syncPublishingTimestamp($node, $expectedTs);
    $this->assertTrue($result);
  }

  /**
   * Tests syncPublishingTimestamp resolves timestamp from transition object.
   */
  public function testSyncPublishingTimestampResolvesFromTransition(): void {
    $transitionTs = 1789855555;
    $mockTransition = $this->createStub(WorkflowTransitionInterface::class);
    $mockTransition->method('getTimestamp')->willReturn($transitionTs);

    $original = $this->createMockNode([
      WorkflowPublicationManager::FIELD_GENERAL => WorkflowPublicationManager::STATE_GENERAL_UNPUBLISHED_READY,
    ]);

    $fieldItemList = new class($mockTransition) extends \stdClass {
      public string $value = WorkflowPublicationManager::STATE_GENERAL_PUBLISHED;
      public function __construct(protected $transition) {}
      public function isEmpty(): bool { return false; }
      public function getTransition() { return $this->transition; }
    };

    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->willReturnCallback(fn($f) => $f === WorkflowPublicationManager::FIELD_GENERAL);
    $node->method('get')->willReturnCallback(fn($f) => $fieldItemList);
    $node->method('getOriginal')->willReturn($original);

    $node->expects($this->once())
      ->method('setCreatedTime')
      ->with($transitionTs);

    $result = $this->manager->syncPublishingTimestamp($node);
    $this->assertTrue($result);
  }

  /**
   * Tests syncPublishingTimestamp does nothing on already published node.
   */
  public function testSyncPublishingTimestampDoesNothingOnAlreadyPublishedNode(): void {
    $original = $this->createMockNode([
      WorkflowPublicationManager::FIELD_GENERAL => WorkflowPublicationManager::STATE_GENERAL_PUBLISHED,
    ]);
    $node = $this->createMockNode([
      WorkflowPublicationManager::FIELD_GENERAL => WorkflowPublicationManager::STATE_GENERAL_PUBLISHED,
    ], true);
    $node->method('getOriginal')->willReturn($original);

    $node->expects($this->never())->method('setCreatedTime');

    $result = $this->manager->syncPublishingTimestamp($node);
    $this->assertFalse($result);
  }

}

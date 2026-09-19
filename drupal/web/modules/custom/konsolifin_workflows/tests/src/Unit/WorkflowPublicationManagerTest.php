<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_workflows\Unit;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\konsolifin_workflows\WorkflowPublicationManager;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WorkflowPublicationManager.
 *
 * @coversDefaultClass \Drupal\konsolifin_workflows\WorkflowPublicationManager
 */
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
  protected function createMockNode(array $fields = [], bool $asMock = false): NodeInterface {
    $node = $asMock ? $this->createMock(NodeInterface::class) : $this->createStub(NodeInterface::class);

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

}

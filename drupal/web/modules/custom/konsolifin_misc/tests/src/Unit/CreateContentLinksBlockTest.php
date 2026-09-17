<?php

namespace Drupal\Tests\konsolifin_misc\Unit;

use Drupal\Core\Entity\EntityAccessControlHandlerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\konsolifin_misc\Plugin\Block\CreateContentLinksBlock;
use Drupal\node\NodeTypeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CreateContentLinksBlock.
 *
 * @coversDefaultClass \Drupal\konsolifin_misc\Plugin\Block\CreateContentLinksBlock
 */
#[AllowMockObjectsWithoutExpectations]
class CreateContentLinksBlockTest extends TestCase
{

  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityStorageInterface $nodeTypeStorage;
  protected EntityAccessControlHandlerInterface $accessControlHandler;
  protected AccountProxyInterface $currentUser;

  protected function setUp(): void
  {
    parent::setUp();

    $string_translation = new class implements \Drupal\Core\StringTranslation\TranslationInterface {
      public function translate($string, array $args = [], array $options = [])
      {
        return $string;
      }
      public function translateString(\Drupal\Core\StringTranslation\TranslatableMarkup $translated_string)
      {
        return $translated_string->getUntranslatedString();
      }
      public function formatPlural($count, $singular, $plural, array $args = [], array $options = [])
      {
        return $count == 1 ? $singular : $plural;
      }
    };

    if (\Drupal::hasContainer()) {
      $container = \Drupal::getContainer();
    } else {
      $container = new \Drupal\Core\DependencyInjection\ContainerBuilder();
      \Drupal::setContainer($container);
    }
    $container->set('string_translation', $string_translation);

    $urlGenerator = $this->createMock(\Drupal\Core\Routing\UrlGeneratorInterface::class);
    $urlGenerator->method('generateFromRoute')
      ->willReturnCallback(function ($name, $parameters = [], $options = []) {
        return '/node/add/' . ($parameters['node_type'] ?? '');
      });
    $container->set('url_generator', $urlGenerator);

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->nodeTypeStorage = $this->createMock(EntityStorageInterface::class);
    $this->accessControlHandler = $this->createMock(EntityAccessControlHandlerInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);

    $this->entityTypeManager->method('getStorage')
      ->with('node_type')
      ->willReturn($this->nodeTypeStorage);

    $this->entityTypeManager->method('getAccessControlHandler')
      ->with('node')
      ->willReturn($this->accessControlHandler);
  }

  /**
   * Tests that only allowed content types are included in the block.
   */
  public function testAllowedContentTypes(): void
  {
    $type1 = $this->createMockNodeType('uutinen', 'Uutinen', 'News description');
    $type2 = $this->createMockNodeType('blogi', 'Blogi', 'Blog description');
    $type3 = $this->createMockNodeType('article', 'Artikkeli', 'Article description');

    $this->nodeTypeStorage->method('loadMultiple')
      ->willReturn([
        'uutinen' => $type1,
        'blogi' => $type2,
        'article' => $type3,
      ]);

    // Current user can create uutinen and article, but NOT blogi.
    $this->accessControlHandler->method('createAccess')
      ->willReturnCallback(function ($bundle, $account) {
        return in_array($bundle, ['uutinen', 'article']);
      });

    $block = new CreateContentLinksBlock(
      [],
      'konsolifin_create_content_links',
      ['provider' => 'konsolifin_misc'],
      $this->entityTypeManager,
      $this->currentUser,
    );

    $build = $block->build();

    $this->assertEquals('konsolifin_create_content_links', $build['#theme']);
    $this->assertEquals(['konsolifin_misc/create-content-links'], $build['#attached']['library']);
    $this->assertEquals(['user.permissions'], $build['#cache']['contexts']);
    $this->assertEquals(['config:node_type_list'], $build['#cache']['tags']);

    // Check allowed types (should be 2 items: Artikkeli and Uutinen, sorted alphabetically)
    $this->assertCount(2, $build['#content_types']);
    $this->assertEquals('Artikkeli', $build['#content_types'][0]['label']);
    $this->assertEquals('article', $build['#content_types'][0]['id']);
    $this->assertEquals('/node/add/article', $build['#content_types'][0]['url']);

    $this->assertEquals('Uutinen', $build['#content_types'][1]['label']);
    $this->assertEquals('uutinen', $build['#content_types'][1]['id']);
    $this->assertEquals('/node/add/uutinen', $build['#content_types'][1]['url']);
  }

  /**
   * Tests that when user has no permissions, content_types is empty.
   */
  public function testNoPermissions(): void
  {
    $type1 = $this->createMockNodeType('uutinen', 'Uutinen', 'News');
    $this->nodeTypeStorage->method('loadMultiple')
      ->willReturn(['uutinen' => $type1]);

    $this->accessControlHandler->method('createAccess')
      ->willReturn(FALSE);

    $block = new CreateContentLinksBlock(
      [],
      'konsolifin_create_content_links',
      ['provider' => 'konsolifin_misc'],
      $this->entityTypeManager,
      $this->currentUser,
    );

    $build = $block->build();

    $this->assertEmpty($build['#content_types']);
    $this->assertEquals('konsolifin_create_content_links', $build['#theme']);
  }

  /**
   * Helper to create a mock NodeTypeInterface.
   */
  protected function createMockNodeType(string $id, string $label, string $description): NodeTypeInterface
  {
    $type = $this->createMock(NodeTypeInterface::class);
    $type->method('id')->willReturn($id);
    $type->method('label')->willReturn($label);
    $type->method('getDescription')->willReturn($description);
    return $type;
  }

}

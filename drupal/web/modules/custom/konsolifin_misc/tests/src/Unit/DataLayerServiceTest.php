<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_misc\Unit;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\konsolifin_misc\Service\DataLayerService;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/konsolifin_misc.module';

/**
 * Helper class for mocking iterable Drupal field item lists.
 */
class TestFieldItemList implements \IteratorAggregate {

  public function __construct(private array $items = []) {
  }

  public function isEmpty(): bool {
    return empty($this->items);
  }

  public function getIterator(): \Traversable {
    return new \ArrayIterator($this->items);
  }

}

/**
 * Helper class for mocking single Drupal field item values (e.g. string fields).
 */
class TestFieldItemValue {

  public function __construct(public ?string $value = NULL) {
  }

  public function isEmpty(): bool {
    return $this->value === NULL || $this->value === '';
  }

}

/**
 * Unit tests for DataLayerService and konsolifin_misc_page_attachments().
 *
 * @coversDefaultClass \Drupal\konsolifin_misc\Service\DataLayerService
 */
#[AllowMockObjectsWithoutExpectations]
class DataLayerServiceTest extends TestCase {

  protected DateFormatterInterface $dateFormatter;
  protected DataLayerService $service;

  protected function setUp(): void {
    parent::setUp();

    $this->dateFormatter = $this->createMock(DateFormatterInterface::class);
    $this->dateFormatter->method('format')
      ->willReturnCallback(function (int $timestamp, string $type = 'medium', string $format = '', ?string $timezone = NULL, ?string $langcode = NULL): string {
        if ($format === 'Y-m-d') {
          return gmdate('Y-m-d', $timestamp);
        }
        return (string) $timestamp;
      });

    $this->service = new DataLayerService($this->dateFormatter);
  }

  /**
   * Tests building dataLayer data with all metadata present.
   */
  public function testBuildDataLayerDataWithFullMetadata(): void {
    $author = new class {
      public string $realname = 'Kirjoittajan Nimi';
      public function getDisplayName(): string { return 'Username'; }
      public function label(): string { return 'Username'; }
      public function getCacheTags(): array { return ['user:42']; }
    };

    $term = $this->createMock(TermInterface::class);
    $term->method('label')->willReturn('Pelin Nimi');
    $term->method('getCacheTags')->willReturn(['taxonomy_term:100']);

    $termItem = new \stdClass();
    $termItem->entity = $term;

    $pelitField = new TestFieldItemList([$termItem]);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('uutinen');
    $node->method('id')->willReturn(12345);
    $node->method('getCreatedTime')->willReturn(1790856000); // 2026-10-01
    $node->method('getOwner')->willReturn($author);
    $node->method('getCacheTags')->willReturn(['node:12345']);

    $node->method('hasField')->willReturnCallback(function (string $fieldName): bool {
      return $fieldName === 'field_pelit';
    });
    $node->method('get')->willReturnCallback(function (string $fieldName) use ($pelitField) {
      if ($fieldName === 'field_pelit') {
        return $pelitField;
      }
      return NULL;
    });

    $data = $this->service->buildDataLayerData($node);

    $this->assertSame([
      'platform' => 'drupal',
      'site_section' => 'editorial',
      'content_type' => 'uutinen',
      'content_id' => '12345',
      'author' => 'Kirjoittajan Nimi',
      'game_title' => 'Pelin Nimi',
      'publication_date' => '2026-10-01',
    ], $data);
  }

  /**
   * Tests building JavaScript snippet for dataLayer.
   */
  public function testBuildDataLayerSnippet(): void {
    $author = $this->createMock(UserInterface::class);
    $author->method('getDisplayName')->willReturn('Kirjoittajan Nimi');

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('uutinen');
    $node->method('id')->willReturn(12345);
    $node->method('getCreatedTime')->willReturn(1790856000);
    $node->method('getOwner')->willReturn($author);
    $node->method('hasField')->willReturn(FALSE);

    $snippet = $this->service->buildDataLayerSnippet($node);

    $this->assertStringStartsWith("window.dataLayer = window.dataLayer || [];\nwindow.dataLayer.push({", $snippet);
    $this->assertStringEndsWith('});', $snippet);
    $this->assertStringContainsString('"platform": "drupal"', $snippet);
    $this->assertStringContainsString('"site_section": "editorial"', $snippet);
    $this->assertStringContainsString('"content_type": "uutinen"', $snippet);
    $this->assertStringContainsString('"content_id": "12345"', $snippet);
    $this->assertStringContainsString('"author": "Kirjoittajan Nimi"', $snippet);
    $this->assertStringContainsString('"publication_date": "2026-10-01"', $snippet);
  }

  /**
   * Tests game title with both field_pelit and field_pelin_nimi (e.g. peliarvostelu).
   */
  public function testGameTitleReviewBundleWithSeriesAndPelinNimi(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('label')->willReturn('Final Fantasy');

    $termItem = new \stdClass();
    $termItem->entity = $term;

    $pelitField = new TestFieldItemList([$termItem]);
    $pelinNimiField = new TestFieldItemValue('VII Rebirth');

    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->willReturnCallback(function (string $field): bool {
      return in_array($field, ['field_pelit', 'field_pelin_nimi'], TRUE);
    });
    $node->method('get')->willReturnCallback(function (string $field) use ($pelitField, $pelinNimiField) {
      if ($field === 'field_pelit') {
        return $pelitField;
      }
      if ($field === 'field_pelin_nimi') {
        return $pelinNimiField;
      }
      return NULL;
    });

    $gameTitle = $this->service->getGameTitle($node);
    $this->assertSame('Final Fantasy VII Rebirth', $gameTitle);
  }

  /**
   * Tests game title with multiple pelit terms.
   */
  public function testGameTitleMultiplePelitTerms(): void {
    $term1 = $this->createMock(TermInterface::class);
    $term1->method('label')->willReturn('Mario Kart');

    $term2 = $this->createMock(TermInterface::class);
    $term2->method('label')->willReturn('Super Smash Bros');

    $item1 = new \stdClass();
    $item1->entity = $term1;

    $item2 = new \stdClass();
    $item2->entity = $term2;

    $pelitField = new TestFieldItemList([$item1, $item2]);

    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->willReturnCallback(function (string $field): bool {
      return $field === 'field_pelit';
    });
    $node->method('get')->willReturnCallback(function (string $field) use ($pelitField) {
      return $field === 'field_pelit' ? $pelitField : NULL;
    });

    $gameTitle = $this->service->getGameTitle($node);
    $this->assertSame('Mario Kart, Super Smash Bros', $gameTitle);
  }

  /**
   * Tests game title when only field_pelin_nimi is present.
   */
  public function testGameTitleOnlyPelinNimi(): void {
    $pelinNimiField = new TestFieldItemValue('Astro Bot');

    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->willReturnCallback(function (string $field): bool {
      return $field === 'field_pelin_nimi';
    });
    $node->method('get')->willReturnCallback(function (string $field) use ($pelinNimiField) {
      return $field === 'field_pelin_nimi' ? $pelinNimiField : NULL;
    });

    $gameTitle = $this->service->getGameTitle($node);
    $this->assertSame('Astro Bot', $gameTitle);
  }

  /**
   * Tests node with no game fields.
   */
  public function testNoGameFields(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->willReturn(FALSE);

    $gameTitle = $this->service->getGameTitle($node);
    $this->assertSame('', $gameTitle);
  }

  /**
   * Tests author name fallback to getDisplayName().
   */
  public function testAuthorFallbackToDisplayName(): void {
    $author = $this->createMock(UserInterface::class);
    $author->method('getDisplayName')->willReturn('Toimittaja Tuomas');

    $node = $this->createMock(NodeInterface::class);
    $node->method('getOwner')->willReturn($author);

    $authorName = $this->service->getAuthorName($node);
    $this->assertSame('Toimittaja Tuomas', $authorName);
  }

  /**
   * Tests author name when node owner is null.
   */
  public function testNoAuthor(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('getOwner')->willReturn(NULL);

    $authorName = $this->service->getAuthorName($node);
    $this->assertSame('', $authorName);
  }

  /**
   * Tests publication date when created time is zero.
   */
  public function testPublicationDateEmptyWhenNoCreatedTime(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('getCreatedTime')->willReturn(0);

    $date = $this->service->getPublicationDate($node);
    $this->assertSame('', $date);
  }

  /**
   * Tests attachDataLayer attaches the script and merges cache tags.
   */
  public function testAttachDataLayer(): void {
    $author = $this->createMock(UserInterface::class);
    $author->method('getDisplayName')->willReturn('Kirjoittaja');
    $author->method('getCacheTags')->willReturn(['user:5']);

    $term = $this->createMock(TermInterface::class);
    $term->method('label')->willReturn('Peli');
    $term->method('getCacheTags')->willReturn(['taxonomy_term:10']);

    $termItem = new \stdClass();
    $termItem->entity = $term;

    $pelitField = new TestFieldItemList([$termItem]);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('uutinen');
    $node->method('id')->willReturn(999);
    $node->method('getCreatedTime')->willReturn(1790856000);
    $node->method('getOwner')->willReturn($author);
    $node->method('getCacheTags')->willReturn(['node:999']);
    $node->method('hasField')->willReturnCallback(function (string $field): bool {
      return $field === 'field_pelit';
    });
    $node->method('get')->willReturnCallback(function (string $field) use ($pelitField) {
      return $field === 'field_pelit' ? $pelitField : NULL;
    });

    $attachments = [
      '#cache' => [
        'tags' => ['existing_tag'],
      ],
    ];

    $this->service->attachDataLayer($attachments, $node);

    $this->assertArrayHasKey('#attached', $attachments);
    $this->assertArrayHasKey('html_head', $attachments['#attached']);
    $this->assertCount(1, $attachments['#attached']['html_head']);

    [$tagArray, $tagKey] = $attachments['#attached']['html_head'][0];
    $this->assertSame('konsolifin_misc_datalayer', $tagKey);
    $this->assertSame('script', $tagArray['#tag']);
    $this->assertSame(-100, $tagArray['#weight']);
    $this->assertInstanceOf(MarkupInterface::class, $tagArray['#value']);

    $this->assertContains('node:999', $attachments['#cache']['tags']);
    $this->assertContains('user:5', $attachments['#cache']['tags']);
    $this->assertContains('taxonomy_term:10', $attachments['#cache']['tags']);
    $this->assertContains('existing_tag', $attachments['#cache']['tags']);
  }

  /**
   * Tests escaping of </script> and <!-- inside dataLayer values.
   */
  public function testSpecialCharactersEscaping(): void {
    $author = $this->createMock(UserInterface::class);
    $author->method('getDisplayName')->willReturn("O'Connor </script><script>alert(1)</script>");

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('uutinen');
    $node->method('id')->willReturn(123);
    $node->method('getCreatedTime')->willReturn(1790856000);
    $node->method('getOwner')->willReturn($author);
    $node->method('hasField')->willReturn(FALSE);

    $snippet = $this->service->buildDataLayerSnippet($node);

    $this->assertStringNotContainsString('</script>', $snippet);
    $this->assertStringContainsString('<\/', $snippet);
  }

  /**
   * Tests konsolifin_misc_page_attachments() on canonical node route.
   */
  public function testHookPageAttachmentsCanonicalRoute(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('uutinen');
    $node->method('id')->willReturn(12345);
    $node->method('getCreatedTime')->willReturn(1790856000);
    $node->method('getOwner')->willReturn(NULL);
    $node->method('hasField')->willReturn(FALSE);
    $node->method('getCacheTags')->willReturn(['node:12345']);

    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRouteName')->willReturn('entity.node.canonical');
    $routeMatch->method('getParameter')->with('node')->willReturn($node);

    $container = new ContainerBuilder();
    $container->set('current_route_match', $routeMatch);
    $container->set('konsolifin_misc.datalayer', $this->service);
    \Drupal::setContainer($container);

    $attachments = [];
    konsolifin_misc_page_attachments($attachments);

    $this->assertArrayHasKey('#attached', $attachments);
    $this->assertArrayHasKey('html_head', $attachments['#attached']);
    $this->assertSame('konsolifin_misc_datalayer', $attachments['#attached']['html_head'][0][1]);
  }

  /**
   * Tests konsolifin_misc_page_attachments() on non-canonical route.
   */
  public function testHookPageAttachmentsNonCanonicalRoute(): void {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRouteName')->willReturn('view.frontpage.page_1');

    $container = new ContainerBuilder();
    $container->set('current_route_match', $routeMatch);
    \Drupal::setContainer($container);

    $attachments = [];
    konsolifin_misc_page_attachments($attachments);

    $this->assertEmpty($attachments);
  }

  /**
   * Tests konsolifin_misc_page_attachments() when node is not found.
   */
  public function testHookPageAttachmentsCanonicalRouteWithoutNode(): void {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getRouteName')->willReturn('entity.node.canonical');
    $routeMatch->method('getParameter')->with('node')->willReturn(NULL);

    $container = new ContainerBuilder();
    $container->set('current_route_match', $routeMatch);
    \Drupal::setContainer($container);

    $attachments = [];
    konsolifin_misc_page_attachments($attachments);

    $this->assertEmpty($attachments);
  }

}

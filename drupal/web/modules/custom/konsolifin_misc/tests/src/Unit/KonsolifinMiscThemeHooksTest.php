<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_misc\Unit;

use Drupal\file\FileInterface;
use Drupal\konsolifin_misc\Hook\KonsolifinMiscThemeHooks;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Helper class for mocking Drupal field item lists with property access.
 */
class ThemeTestFieldItemList {

  public function __construct(
    public mixed $entity = NULL,
    protected bool $empty = FALSE,
  ) {}

  public function isEmpty(): bool {
    return $this->empty;
  }

}

/**
 * Mockable user interface with dynamic properties allowed.
 */
abstract class ThemeTestUserEntity implements UserInterface, \IteratorAggregate {

  public mixed $field_kuva = NULL;
  public mixed $field_esittely = NULL;
  public mixed $field_some_linkit = NULL;

  public function getIterator(): \Traversable {
    return new \ArrayIterator([]);
  }

}

/**
 * Unit tests for KonsolifinMiscThemeHooks.
 */
#[AllowMockObjectsWithoutExpectations]
class KonsolifinMiscThemeHooksTest extends TestCase {

  protected KonsolifinMiscThemeHooks $hooks;

  protected function setUp(): void {
    parent::setUp();
    $this->hooks = new KonsolifinMiscThemeHooks();
  }

  /**
   * Tests that theme hook registers expected templates and variables.
   */
  public function testTheme(): void {
    $theme = $this->hooks->theme();

    $this->assertArrayHasKey('konsolifin_two_week_calendar', $theme);
    $this->assertSame('konsolifin-two-week-calendar', $theme['konsolifin_two_week_calendar']['template']);
    $this->assertArrayHasKey('variables', $theme['konsolifin_two_week_calendar']);

    $this->assertArrayHasKey('konsolifin_create_content_links', $theme);
    $this->assertSame('konsolifin-create-content-links', $theme['konsolifin_create_content_links']['template']);
    $this->assertArrayHasKey('content_types', $theme['konsolifin_create_content_links']['variables']);
  }

  /**
   * Tests preprocess_username with realname attribute.
   */
  public function testPreprocessUsernameWithRealname(): void {
    $account = new \stdClass();
    $account->realname = 'Matti Meikäläinen';

    $variables = ['account' => $account];
    $this->hooks->preprocessUsername($variables);

    $this->assertSame('Matti Meikäläinen', $variables['name']);
    $this->assertFalse($variables['truncated']);
  }

  /**
   * Tests preprocess_username fallback to getDisplayName().
   */
  public function testPreprocessUsernameFallbackToDisplayName(): void {
    $account = $this->createMock(UserInterface::class);
    $account->method('getDisplayName')->willReturn('konsolifin_user');

    $variables = ['account' => $account];
    $this->hooks->preprocessUsername($variables);

    $this->assertSame('konsolifin_user', $variables['name']);
    $this->assertFalse($variables['truncated']);
  }

  /**
   * Tests preprocess_node with author image, bio, and social links.
   */
  public function testPreprocessNode(): void {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn('public://authors/author.jpg');

    $mediaImageField = new ThemeTestFieldItemList(entity: $file);

    $media = $this->createMock(MediaInterface::class);
    $media->method('hasField')->with('field_media_image')->willReturn(TRUE);
    $media->method('get')->with('field_media_image')->willReturn($mediaImageField);
    $media->method('getCacheTags')->willReturn(['media:10']);

    $kuvaField = new ThemeTestFieldItemList(entity: $media);

    $bioField = new class {
      public function view(string $display): array {
        return [
          0 => ['#text' => '<p>Ensimmäinen kappale esittelystä.</p><p>Toinen kappale.</p>'],
        ];
      }
    };

    $socialField = new class {
      public function view(string $display): array {
        return ['#markup' => '<div class="social-links">Links</div>'];
      }
    };

    $author = $this->createMock(ThemeTestUserEntity::class);
    $author->method('hasField')->willReturnCallback(function (string $fieldName) {
      return in_array($fieldName, ['field_kuva', 'field_esittely', 'field_some_linkit'], TRUE);
    });
    $author->method('get')->with('field_kuva')->willReturn($kuvaField);
    $author->field_kuva = $kuvaField;
    $author->field_esittely = $bioField;
    $author->field_some_linkit = $socialField;
    $author->method('getDisplayName')->willReturn('Kirjoittaja');
    $author->method('getCacheTags')->willReturn(['user:42']);

    $node = $this->createMock(NodeInterface::class);
    $node->method('getOwner')->willReturn($author);

    $variables = ['node' => $node];
    $this->hooks->preprocessNode($variables);

    $this->assertArrayHasKey('author_headshot', $variables);
    $this->assertSame('image_style', $variables['author_headshot']['#theme']);
    $this->assertSame('teaser_thumbnail', $variables['author_headshot']['#style_name']);
    $this->assertSame('public://authors/author.jpg', $variables['author_headshot']['#uri']);
    $this->assertSame(['user:42', 'media:10'], $variables['author_headshot']['#cache']['tags']);

    $this->assertArrayHasKey('author_bio', $variables);
    $this->assertSame('<p>Ensimmäinen kappale esittelystä.</p>', $variables['author_bio'][0]['#text']);

    $this->assertArrayHasKey('author_social', $variables);
  }

}

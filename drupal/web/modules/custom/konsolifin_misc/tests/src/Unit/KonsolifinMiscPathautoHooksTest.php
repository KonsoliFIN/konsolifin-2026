<?php

declare(strict_types=1);

namespace Drupal\Tests\konsolifin_misc\Unit;

use Drupal\konsolifin_misc\Hook\KonsolifinMiscPathautoHooks;
use Drupal\node\NodeInterface;
use Drupal\pathauto\AliasCleanerInterface;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Helper class for mocking Drupal field item lists with property access.
 */
class PathautoTestFieldItemList implements \Countable {

  public function __construct(
    public mixed $entity = NULL,
    public mixed $value = NULL,
    protected bool $empty = FALSE,
    protected int $count = 1,
  ) {}

  public function isEmpty(): bool {
    return $this->empty;
  }

  public function count(): int {
    return $this->count;
  }

}

/**
 * Unit tests for KonsolifinMiscPathautoHooks.
 */
#[AllowMockObjectsWithoutExpectations]
class KonsolifinMiscPathautoHooksTest extends TestCase {

  protected AliasCleanerInterface $aliasCleaner;
  protected KonsolifinMiscPathautoHooks $hooks;

  protected function setUp(): void {
    parent::setUp();
    $this->aliasCleaner = $this->createMock(AliasCleanerInterface::class);
    $this->aliasCleaner->method('cleanString')->willReturnCallback(function (string $string) {
      return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $string) ?? '');
    });
    $this->hooks = new KonsolifinMiscPathautoHooks($this->aliasCleaner);
  }

  /**
   * Tests that pathauto alias is altered with field_sarja term name.
   */
  public function testAliasAlterWithFieldSarja(): void {
    $term = $this->createMock(TermInterface::class);
    $term->method('getName')->willReturn('Viikon Peli');

    $sarjaField = new PathautoTestFieldItemList(entity: $term);

    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->willReturnCallback(function (string $field) {
      return $field === 'field_sarja';
    });
    $node->method('get')->with('field_sarja')->willReturn($sarjaField);

    $alias = '/artikkelit/super-mario';
    $context = [
      'module' => 'node',
      'data' => ['node' => $node],
    ];

    $this->hooks->pathautoAliasAlter($alias, $context);

    $this->assertSame('/artikkelit/viikon-peli-super-mario', $alias);
  }

  /**
   * Tests that pathauto alias appends pelit and pelin_nimi fields.
   */
  public function testAliasAlterWithPelitAndPelinNimi(): void {
    $gameTerm = $this->createMock(TermInterface::class);
    $gameTerm->method('getName')->willReturn('Halo Infinite');

    $pelitField = new PathautoTestFieldItemList(entity: $gameTerm, count: 1);
    $pelinNimiField = new PathautoTestFieldItemList(value: 'PC Edition');

    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->willReturnCallback(function (string $field) {
      return in_array($field, ['field_pelit', 'field_pelin_nimi'], TRUE);
    });
    $node->method('get')->willReturnCallback(function (string $field) use ($pelitField, $pelinNimiField) {
      return $field === 'field_pelit' ? $pelitField : $pelinNimiField;
    });

    $alias = '/arvostelut/halo-arvostelu';
    $context = [
      'module' => 'node',
      'data' => ['node' => $node],
    ];

    $this->hooks->pathautoAliasAlter($alias, $context);

    $this->assertSame('/arvostelut/halo-arvostelu-halo-infinite-pc-edition', $alias);
  }

  /**
   * Tests that non-node contexts are ignored without modification.
   */
  public function testAliasAlterIgnoredForNonNode(): void {
    $alias = '/kayttajat/matti';
    $context = [
      'module' => 'user',
      'data' => [],
    ];

    $this->hooks->pathautoAliasAlter($alias, $context);

    $this->assertSame('/kayttajat/matti', $alias);
  }

}

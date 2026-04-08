<?php

declare(strict_types=1);

// Feature: games-page, Property 1: Top games list contains only valid published peli terms from config

namespace Drupal\Tests\konsolifin_term_page\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\date_ish\DateIshHelper;
use Drupal\konsolifin_term_page\Controller\GamesPageController;
use Drupal\konsolifin_term_page\MatomoService;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: Top games list contains only valid published peli terms from config.
 *
 * For any configuration of top game term IDs (including null values,
 * non-existent IDs, unpublished terms, and terms from other vocabularies),
 * GamesPageController::buildTopGames() must return an array containing only
 * entries for published peli terms whose IDs appear in the config. The array
 * must never contain more than three entries, and each entry must have a
 * non-empty name and a non-empty url.
 *
 * **Validates: Requirements 2.2, 2.4, 2.5, 3.4**
 *
 * @group konsolifin_term_page
 */
class GamesPageControllerTest extends TestCase {

  /**
   * Possible term "types" for random generation.
   */
  private const TERM_TYPE_NULL = 'null';
  private const TERM_TYPE_NONEXISTENT = 'nonexistent';
  private const TERM_TYPE_UNPUBLISHED_PELI = 'unpublished_peli';
  private const TERM_TYPE_OTHER_VOCAB = 'other_vocab';
  private const TERM_TYPE_VALID_PELI = 'valid_peli';

  /**
   * Data provider generating 100+ random top games configurations.
   *
   * Each dataset contains:
   * - configSlots: array of 3 slot specs, each with a type and optional term data
   * - termRegistry: array of tid => term spec for all terms that "exist"
   */
  public static function randomTopGamesConfigProvider(): \Generator {
    $seed = crc32('top_games_valid_published_peli_property_1_' . date('Y-m-d'));
    mt_srand($seed);

    $slotTypes = [
      self::TERM_TYPE_NULL,
      self::TERM_TYPE_NONEXISTENT,
      self::TERM_TYPE_UNPUBLISHED_PELI,
      self::TERM_TYPE_OTHER_VOCAB,
      self::TERM_TYPE_VALID_PELI,
    ];

    $otherVocabs = ['tags', 'category', 'genre', 'platform', 'publisher'];

    for ($i = 0; $i < 110; $i++) {
      $configSlots = [];
      $termRegistry = [];
      $tidCounter = mt_rand(100, 500);

      for ($slot = 0; $slot < 3; $slot++) {
        $type = $slotTypes[mt_rand(0, count($slotTypes) - 1)];

        switch ($type) {
          case self::TERM_TYPE_NULL:
            $configSlots[] = ['type' => $type, 'tid' => NULL];
            break;

          case self::TERM_TYPE_NONEXISTENT:
            // Use a tid that won't be in the registry.
            $fakeTid = mt_rand(90000, 99999);
            $configSlots[] = ['type' => $type, 'tid' => $fakeTid];
            break;

          case self::TERM_TYPE_UNPUBLISHED_PELI:
            $tidCounter++;
            $name = 'Unpublished Game ' . mt_rand(1000, 9999);
            $url = '/taxonomy/term/' . $tidCounter;
            $termRegistry[$tidCounter] = [
              'tid' => $tidCounter,
              'name' => $name,
              'url' => $url,
              'bundle' => 'peli',
              'published' => FALSE,
              'has_hero_image' => (bool) mt_rand(0, 1),
            ];
            $configSlots[] = ['type' => $type, 'tid' => $tidCounter];
            break;

          case self::TERM_TYPE_OTHER_VOCAB:
            $tidCounter++;
            $vocab = $otherVocabs[mt_rand(0, count($otherVocabs) - 1)];
            $name = 'Other Vocab Term ' . mt_rand(1000, 9999);
            $url = '/taxonomy/term/' . $tidCounter;
            $termRegistry[$tidCounter] = [
              'tid' => $tidCounter,
              'name' => $name,
              'url' => $url,
              'bundle' => $vocab,
              'published' => TRUE,
              'has_hero_image' => (bool) mt_rand(0, 1),
            ];
            $configSlots[] = ['type' => $type, 'tid' => $tidCounter];
            break;

          case self::TERM_TYPE_VALID_PELI:
            $tidCounter++;
            $name = 'Valid Game ' . mt_rand(1000, 9999) . ' ' . chr(mt_rand(65, 90));
            $url = '/taxonomy/term/' . $tidCounter;
            $termRegistry[$tidCounter] = [
              'tid' => $tidCounter,
              'name' => $name,
              'url' => $url,
              'bundle' => 'peli',
              'published' => TRUE,
              'has_hero_image' => (bool) mt_rand(0, 1),
            ];
            $configSlots[] = ['type' => $type, 'tid' => $tidCounter];
            break;
        }
      }

      $typesSummary = implode(',', array_column($configSlots, 'type'));
      yield "dataset #{$i} ({$typesSummary})" => [$configSlots, $termRegistry];
    }
  }


  /**
   * Tests that buildTopGames() returns only valid published peli terms from config.
   *
   * **Validates: Requirements 2.2, 2.4, 2.5, 3.4**
   */
  #[DataProvider('randomTopGamesConfigProvider')]
  public function testTopGamesOnlyValidPublishedPeli(array $configSlots, array $termRegistry): void {
    // Build config mock returning the three top_game values.
    $configValues = [
      'top_game_1' => $configSlots[0]['tid'],
      'top_game_2' => $configSlots[1]['tid'],
      'top_game_3' => $configSlots[2]['tid'],
    ];

    $configMock = $this->createStub(ImmutableConfig::class);
    $configMock->method('get')->willReturnCallback(
      fn(string $key) => $configValues[$key] ?? NULL,
    );

    $configFactoryMock = $this->createStub(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->willReturn($configMock);

    // Build term storage mock that returns term mocks from the registry.
    $termStorageMock = $this->createStub(EntityStorageInterface::class);
    $termStorageMock->method('load')->willReturnCallback(
      function (int|string $tid) use ($termRegistry) {
        $tid = (int) $tid;
        if (!isset($termRegistry[$tid])) {
          return NULL;
        }
        return $this->buildPeliTermMock($termRegistry[$tid]);
      },
    );

    // Build entity type manager mock.
    $entityTypeManagerMock = $this->createStub(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getStorage')->willReturn($termStorageMock);

    // Build a minimal MatomoService stub.
    $loggerMock = $this->createStub(LoggerChannelInterface::class);
    $loggerFactoryMock = $this->createStub(LoggerChannelFactoryInterface::class);
    $loggerFactoryMock->method('get')->willReturn($loggerMock);
    $httpClientMock = $this->createStub(ClientInterface::class);
    $matomoConfigMock = $this->createStub(ImmutableConfig::class);
    $matomoConfigFactoryMock = $this->createStub(ConfigFactoryInterface::class);
    $matomoConfigFactoryMock->method('get')->willReturn($matomoConfigMock);

    $matomoService = new MatomoService(
      $httpClientMock,
      $entityTypeManagerMock,
      $matomoConfigFactoryMock,
      $loggerFactoryMock,
    );

    // Instantiate the controller.
    $controller = new GamesPageController($matomoService);

    // Inject the configFactory via reflection (ControllerBase uses $this->configFactory).
    $reflection = new \ReflectionProperty($controller, 'configFactory');
    $reflection->setAccessible(TRUE);
    $reflection->setValue($controller, $configFactoryMock);

    // Inject the entityTypeManager via reflection.
    $etmReflection = new \ReflectionProperty($controller, 'entityTypeManager');
    $etmReflection->setAccessible(TRUE);
    $etmReflection->setValue($controller, $entityTypeManagerMock);

    // Call buildTopGames().
    $result = $controller->buildTopGames();

    // Determine expected valid entries: published peli terms from config.
    $expectedValidTids = [];
    foreach ($configSlots as $slot) {
      if ($slot['tid'] === NULL) {
        continue;
      }
      if (!isset($termRegistry[$slot['tid']])) {
        continue;
      }
      $spec = $termRegistry[$slot['tid']];
      if ($spec['bundle'] === 'peli' && $spec['published'] === TRUE) {
        $expectedValidTids[] = $slot['tid'];
      }
    }

    // (c) Max 3 entries.
    $this->assertLessThanOrEqual(
      3,
      count($result),
      'buildTopGames() must return at most 3 entries.',
    );

    // Result count must match expected valid entries.
    $this->assertCount(
      count($expectedValidTids),
      $result,
      sprintf(
        'Expected %d valid entries but got %d. Config slots: %s',
        count($expectedValidTids),
        count($result),
        implode(', ', array_map(fn($s) => $s['type'] . '(' . ($s['tid'] ?? 'null') . ')', $configSlots)),
      ),
    );

    // (a) Every entry corresponds to a published peli term from config.
    foreach ($result as $idx => $entry) {
      // (d) Each entry has non-empty name and url.
      $this->assertArrayHasKey('name', $entry, "Entry {$idx} must have a 'name' key.");
      $this->assertNotEmpty($entry['name'], "Entry {$idx} must have a non-empty name.");
      $this->assertArrayHasKey('url', $entry, "Entry {$idx} must have a 'url' key.");
      $this->assertNotEmpty($entry['url'], "Entry {$idx} must have a non-empty url.");

      // Verify the entry matches a valid published peli term from config.
      $matchedTid = $expectedValidTids[$idx] ?? NULL;
      $this->assertNotNull($matchedTid, "Entry {$idx} must correspond to a valid config slot.");

      $expectedSpec = $termRegistry[$matchedTid];
      $this->assertSame(
        $expectedSpec['name'],
        $entry['name'],
        "Entry {$idx} name must match the expected term name.",
      );
      $this->assertSame(
        $expectedSpec['url'],
        $entry['url'],
        "Entry {$idx} url must match the expected term url.",
      );
    }

    // (b) No entry for unpublished, non-existent, or non-peli terms.
    $resultNames = array_column($result, 'name');
    foreach ($termRegistry as $tid => $spec) {
      if ($spec['bundle'] !== 'peli' || $spec['published'] !== TRUE) {
        $this->assertNotContains(
          $spec['name'],
          $resultNames,
          "Term '{$spec['name']}' (bundle={$spec['bundle']}, published=" . ($spec['published'] ? 'true' : 'false') . ") must not appear in results.",
        );
      }
    }
  }

  /**
   * Builds a mock taxonomy term for testing buildTopGames().
   *
   * @param array $spec
   *   Term spec with keys: tid, name, url, bundle, published, has_hero_image.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   A mock TermInterface.
   */
  private function buildPeliTermMock(array $spec): \Drupal\taxonomy\TermInterface {
    $urlString = $spec['url'];
    $urlStub = new class($urlString) {
      public function __construct(private string $urlString) {}
      public function toString(): string { return $this->urlString; }
    };

    // Stub the hero image field.
    $heroFieldStub = $this->createStub(\Drupal\Core\Field\FieldItemListInterface::class);
    $heroFieldStub->method('isEmpty')->willReturn(!$spec['has_hero_image']);

    $termMock = $this->createStub(\Drupal\taxonomy\TermInterface::class);
    $termMock->method('getName')->willReturn($spec['name']);
    $termMock->method('toUrl')->willReturn($urlStub);
    $termMock->method('bundle')->willReturn($spec['bundle']);
    $termMock->method('isPublished')->willReturn($spec['published']);
    $termMock->method('hasField')->willReturnCallback(
      fn(string $fieldName) => $fieldName === 'field_hero_kuva',
    );
    $termMock->method('get')->willReturnCallback(
      function (string $fieldName) use ($heroFieldStub) {
        if ($fieldName === 'field_hero_kuva') {
          return $heroFieldStub;
        }
        return $this->createStub(\Drupal\Core\Field\FieldItemListInterface::class);
      },
    );

    return $termMock;
  }

  // Feature: games-page, Property 2: Upcoming releases are correctly filtered and ordered

  /**
   * Accuracy levels for random date generation.
   */
  private const ACCURACY_LEVELS = ['exact', 'month', 'quarter', 'year_half', 'year'];

  /**
   * Data provider generating 110 random upcoming releases scenarios.
   *
   * Each dataset contains:
   * - nodeSpecs: array of julkaisu node specs with nid, title, url,
   *   stored_date, accuracy_level, published
   *
   * **Validates: Requirements 5.2, 5.3, 5.6, 5.7**
   */
  public static function randomUpcomingReleasesProvider(): \Generator {
    $seed = crc32('upcoming_releases_filter_order_property_2_' . date('Y-m-d'));
    mt_srand($seed);

    $today = date('Y-m-d');

    for ($i = 0; $i < 110; $i++) {
      $nodeCount = mt_rand(1, 15);
      $nodeSpecs = [];
      $nidCounter = mt_rand(1000, 5000);

      for ($n = 0; $n < $nodeCount; $n++) {
        $nidCounter++;
        $title = 'Release ' . mt_rand(10000, 99999) . ' ' . chr(mt_rand(65, 90));
        $url = '/node/' . $nidCounter;
        $published = (bool) mt_rand(0, 1);
        $accuracyLevel = self::ACCURACY_LEVELS[mt_rand(0, count(self::ACCURACY_LEVELS) - 1)];

        // Generate a random date: past, today, future, or null.
        $dateType = mt_rand(0, 3);
        $storedDate = NULL;
        switch ($dateType) {
          case 0:
            // Past date: 1-365 days ago.
            $daysAgo = mt_rand(1, 365);
            $storedDate = date('Y-m-d', strtotime("-{$daysAgo} days"));
            break;

          case 1:
            // Today.
            $storedDate = $today;
            break;

          case 2:
            // Future date: 1-365 days from now.
            $daysAhead = mt_rand(1, 365);
            $storedDate = date('Y-m-d', strtotime("+{$daysAhead} days"));
            break;

          case 3:
            // Null date.
            $storedDate = NULL;
            break;
        }

        $nodeSpecs[] = [
          'nid' => $nidCounter,
          'title' => $title,
          'url' => $url,
          'stored_date' => $storedDate,
          'accuracy_level' => $accuracyLevel,
          'published' => $published,
        ];
      }

      yield "dataset #{$i} ({$nodeCount} nodes)" => [$nodeSpecs];
    }
  }

  /**
   * Tests that buildUpcomingReleases() correctly filters and orders results.
   *
   * Property 2: For any set of julkaisu nodes with varying stored dates and
   * statuses, buildUpcomingReleases() must return an array where:
   * (a) every entry has a stored date >= today,
   * (b) entries are ordered by stored date ascending,
   * (c) only published nodes are included, and
   * (d) the array contains at most 10 entries.
   *
   * **Validates: Requirements 5.2, 5.3, 5.6, 5.7**
   */
  #[DataProvider('randomUpcomingReleasesProvider')]
  public function testUpcomingReleasesFilterAndOrder(array $nodeSpecs): void {
    $today = date('Y-m-d');

    // Compute expected results: published, stored_date >= today, sorted ASC, max 10.
    $expected = array_filter($nodeSpecs, function (array $spec) use ($today) {
      return $spec['published']
        && $spec['stored_date'] !== NULL
        && $spec['stored_date'] >= $today;
    });

    // Sort by stored_date ascending.
    usort($expected, fn(array $a, array $b) => strcmp($a['stored_date'], $b['stored_date']));

    // Limit to 10.
    $expected = array_slice($expected, 0, 10);

    // Extract expected nids (these are what the mocked query will return).
    $expectedNids = array_column($expected, 'nid');

    // Build node mocks for the expected nodes.
    $nodeMocks = [];
    foreach ($expected as $spec) {
      $nodeMocks[$spec['nid']] = $this->buildJulkaisuNodeMock($spec);
    }

    // Build the entity query mock chain.
    $queryMock = $this->createStub(QueryInterface::class);
    $queryMock->method('condition')->willReturnSelf();
    $queryMock->method('sort')->willReturnSelf();
    $queryMock->method('range')->willReturnSelf();
    $queryMock->method('accessCheck')->willReturnSelf();
    $queryMock->method('execute')->willReturn(
      empty($expectedNids) ? [] : array_combine($expectedNids, $expectedNids),
    );

    // Build node storage mock.
    $nodeStorageMock = $this->createStub(EntityStorageInterface::class);
    $nodeStorageMock->method('getQuery')->willReturn($queryMock);
    $nodeStorageMock->method('loadMultiple')->willReturn($nodeMocks);

    // Build entity type manager mock that returns node storage.
    $entityTypeManagerMock = $this->createStub(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getStorage')->willReturnCallback(
      function (string $entityType) use ($nodeStorageMock) {
        if ($entityType === 'node') {
          return $nodeStorageMock;
        }
        return $this->createStub(EntityStorageInterface::class);
      },
    );

    // Build minimal MatomoService stub.
    $loggerMock = $this->createStub(LoggerChannelInterface::class);
    $loggerFactoryMock = $this->createStub(LoggerChannelFactoryInterface::class);
    $loggerFactoryMock->method('get')->willReturn($loggerMock);
    $httpClientMock = $this->createStub(ClientInterface::class);
    $matomoConfigMock = $this->createStub(ImmutableConfig::class);
    $matomoConfigFactoryMock = $this->createStub(ConfigFactoryInterface::class);
    $matomoConfigFactoryMock->method('get')->willReturn($matomoConfigMock);

    $matomoService = new MatomoService(
      $httpClientMock,
      $entityTypeManagerMock,
      $matomoConfigFactoryMock,
      $loggerFactoryMock,
    );

    // Instantiate the controller.
    $controller = new GamesPageController($matomoService);

    // Inject the entityTypeManager via reflection.
    $etmReflection = new \ReflectionProperty($controller, 'entityTypeManager');
    $etmReflection->setAccessible(TRUE);
    $etmReflection->setValue($controller, $entityTypeManagerMock);

    // Call buildUpcomingReleases().
    $result = $controller->buildUpcomingReleases();

    // (d) At most 10 entries.
    $this->assertLessThanOrEqual(
      10,
      count($result),
      'buildUpcomingReleases() must return at most 10 entries.',
    );

    // Result count must match expected.
    $this->assertCount(
      count($expected),
      $result,
      sprintf(
        'Expected %d upcoming releases but got %d.',
        count($expected),
        count($result),
      ),
    );

    // Verify each entry.
    foreach ($result as $idx => $entry) {
      $expectedSpec = $expected[$idx];

      // Each entry must have title, url, date_display.
      $this->assertArrayHasKey('title', $entry, "Entry {$idx} must have a 'title' key.");
      $this->assertArrayHasKey('url', $entry, "Entry {$idx} must have a 'url' key.");
      $this->assertArrayHasKey('date_display', $entry, "Entry {$idx} must have a 'date_display' key.");

      // (a) Every entry has stored_date >= today.
      $this->assertGreaterThanOrEqual(
        $today,
        $expectedSpec['stored_date'],
        "Entry {$idx} stored_date '{$expectedSpec['stored_date']}' must be >= today '{$today}'.",
      );

      // (c) Only published nodes.
      $this->assertTrue(
        $expectedSpec['published'],
        "Entry {$idx} must correspond to a published node.",
      );

      // Verify title matches.
      $this->assertSame(
        $expectedSpec['title'],
        $entry['title'],
        "Entry {$idx} title must match expected.",
      );

      // Verify URL matches.
      $this->assertSame(
        $expectedSpec['url'],
        $entry['url'],
        "Entry {$idx} url must match expected.",
      );

      // Verify date_display matches DateIshHelper output.
      $expectedDateDisplay = DateIshHelper::formatForDisplay(
        $expectedSpec['accuracy_level'],
        $expectedSpec['stored_date'],
      );
      $this->assertSame(
        $expectedDateDisplay,
        $entry['date_display'],
        "Entry {$idx} date_display must match DateIshHelper::formatForDisplay() output.",
      );
    }

    // (b) Entries are ordered by stored_date ascending.
    for ($j = 1; $j < count($result); $j++) {
      $prevDate = $expected[$j - 1]['stored_date'];
      $currDate = $expected[$j]['stored_date'];
      $this->assertLessThanOrEqual(
        0,
        strcmp($prevDate, $currDate),
        "Entry {$j} stored_date '{$currDate}' must be >= entry " . ($j - 1) . " stored_date '{$prevDate}' (ascending order).",
      );
    }
  }

  /**
   * Builds a mock julkaisu node for testing buildUpcomingReleases().
   *
   * @param array $spec
   *   Node spec with keys: nid, title, url, stored_date, accuracy_level,
   *   published.
   *
   * @return \Drupal\node\NodeInterface
   *   A mock NodeInterface.
   */
  private function buildJulkaisuNodeMock(array $spec): NodeInterface {
    $urlString = $spec['url'];
    $urlStub = new class($urlString) {
      public function __construct(private string $urlString) {}
      public function toString(): string { return $this->urlString; }
    };

    // Build the date field stub.
    $dateFieldStub = $this->createStub(\Drupal\Core\Field\FieldItemListInterface::class);
    $dateFieldStub->method('getValue')->willReturn([
      [
        'accuracy_level' => $spec['accuracy_level'],
        'stored_date' => $spec['stored_date'],
      ],
    ]);

    $nodeMock = $this->createStub(NodeInterface::class);
    $nodeMock->method('getTitle')->willReturn($spec['title']);
    $nodeMock->method('toUrl')->willReturn($urlStub);
    $nodeMock->method('isPublished')->willReturn($spec['published']);
    $nodeMock->method('get')->willReturnCallback(
      function (string $fieldName) use ($dateFieldStub) {
        if ($fieldName === 'field_julkaisuajankohta') {
          return $dateFieldStub;
        }
        return $this->createStub(\Drupal\Core\Field\FieldItemListInterface::class);
      },
    );

    return $nodeMock;
  }

  // =========================================================================
  // Unit tests for GamesPageController (Task 6.4)
  // =========================================================================

  /**
   * Creates a GamesPageController with mocked dependencies for unit tests.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $configMock
   *   The config mock for games_page_settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManagerMock
   *   The entity type manager mock.
   *
   * @return \Drupal\konsolifin_term_page\Controller\GamesPageController
   *   The controller instance.
   */
  private function createControllerForUnitTest(
    ImmutableConfig $configMock,
    EntityTypeManagerInterface $entityTypeManagerMock,
  ): GamesPageController {
    $configFactoryMock = $this->createStub(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->willReturn($configMock);

    $loggerMock = $this->createStub(LoggerChannelInterface::class);
    $loggerFactoryMock = $this->createStub(LoggerChannelFactoryInterface::class);
    $loggerFactoryMock->method('get')->willReturn($loggerMock);
    $httpClientMock = $this->createStub(ClientInterface::class);
    $matomoConfigMock = $this->createStub(ImmutableConfig::class);
    $matomoConfigFactoryMock = $this->createStub(ConfigFactoryInterface::class);
    $matomoConfigFactoryMock->method('get')->willReturn($matomoConfigMock);

    $matomoService = new MatomoService(
      $httpClientMock,
      $entityTypeManagerMock,
      $matomoConfigFactoryMock,
      $loggerFactoryMock,
    );

    $controller = new GamesPageController($matomoService);

    $reflection = new \ReflectionProperty($controller, 'configFactory');
    $reflection->setAccessible(TRUE);
    $reflection->setValue($controller, $configFactoryMock);

    // Inject the entityTypeManager via reflection.
    $etmReflection = new \ReflectionProperty($controller, 'entityTypeManager');
    $etmReflection->setAccessible(TRUE);
    $etmReflection->setValue($controller, $entityTypeManagerMock);

    return $controller;
  }

  /**
   * Tests buildTopGames() with all three valid published peli term IDs.
   *
   * **Validates: Requirements 2.2, 2.5**
   */
  public function testBuildTopGamesAllThreeValid(): void {
    $termSpecs = [
      10 => ['tid' => 10, 'name' => 'Zelda', 'url' => '/pelit/zelda', 'bundle' => 'peli', 'published' => TRUE, 'has_hero_image' => FALSE],
      20 => ['tid' => 20, 'name' => 'Mario', 'url' => '/pelit/mario', 'bundle' => 'peli', 'published' => TRUE, 'has_hero_image' => FALSE],
      30 => ['tid' => 30, 'name' => 'Metroid', 'url' => '/pelit/metroid', 'bundle' => 'peli', 'published' => TRUE, 'has_hero_image' => FALSE],
    ];

    $configMock = $this->createStub(ImmutableConfig::class);
    $configMock->method('get')->willReturnCallback(
      fn(string $key) => match ($key) {
        'top_game_1' => 10,
        'top_game_2' => 20,
        'top_game_3' => 30,
        default => NULL,
      },
    );

    $termStorageMock = $this->createStub(EntityStorageInterface::class);
    $termStorageMock->method('load')->willReturnCallback(
      fn(int|string $tid) => isset($termSpecs[(int) $tid])
        ? $this->buildPeliTermMock($termSpecs[(int) $tid])
        : NULL,
    );

    $entityTypeManagerMock = $this->createStub(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getStorage')->willReturn($termStorageMock);

    $controller = $this->createControllerForUnitTest($configMock, $entityTypeManagerMock);
    $result = $controller->buildTopGames();

    $this->assertCount(3, $result);
    $this->assertSame('Zelda', $result[0]['name']);
    $this->assertSame('/pelit/zelda', $result[0]['url']);
    $this->assertSame('Mario', $result[1]['name']);
    $this->assertSame('/pelit/mario', $result[1]['url']);
    $this->assertSame('Metroid', $result[2]['name']);
    $this->assertSame('/pelit/metroid', $result[2]['url']);
  }

  /**
   * Tests buildTopGames() when one config slot is null.
   *
   * Config: top_game_1=valid, top_game_2=null, top_game_3=valid.
   * Expects 2 entries returned (null slot skipped).
   *
   * **Validates: Requirements 2.2, 2.5**
   */
  public function testBuildTopGamesOneNull(): void {
    $termSpecs = [
      10 => ['tid' => 10, 'name' => 'Zelda', 'url' => '/pelit/zelda', 'bundle' => 'peli', 'published' => TRUE, 'has_hero_image' => FALSE],
      30 => ['tid' => 30, 'name' => 'Metroid', 'url' => '/pelit/metroid', 'bundle' => 'peli', 'published' => TRUE, 'has_hero_image' => FALSE],
    ];

    $configMock = $this->createStub(ImmutableConfig::class);
    $configMock->method('get')->willReturnCallback(
      fn(string $key) => match ($key) {
        'top_game_1' => 10,
        'top_game_2' => NULL,
        'top_game_3' => 30,
        default => NULL,
      },
    );

    $termStorageMock = $this->createStub(EntityStorageInterface::class);
    $termStorageMock->method('load')->willReturnCallback(
      fn(int|string $tid) => isset($termSpecs[(int) $tid])
        ? $this->buildPeliTermMock($termSpecs[(int) $tid])
        : NULL,
    );

    $entityTypeManagerMock = $this->createStub(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getStorage')->willReturn($termStorageMock);

    $controller = $this->createControllerForUnitTest($configMock, $entityTypeManagerMock);
    $result = $controller->buildTopGames();

    $this->assertCount(2, $result);
    $this->assertSame('Zelda', $result[0]['name']);
    $this->assertSame('Metroid', $result[1]['name']);
  }

  /**
   * Tests buildTopGames() when one config slot has a non-existent term ID.
   *
   * Config: top_game_1=valid, top_game_2=99999 (non-existent), top_game_3=valid.
   * Expects 2 entries returned (non-existent ID skipped).
   *
   * **Validates: Requirements 2.2, 2.5**
   */
  public function testBuildTopGamesNonExistentId(): void {
    $termSpecs = [
      10 => ['tid' => 10, 'name' => 'Zelda', 'url' => '/pelit/zelda', 'bundle' => 'peli', 'published' => TRUE, 'has_hero_image' => FALSE],
      30 => ['tid' => 30, 'name' => 'Metroid', 'url' => '/pelit/metroid', 'bundle' => 'peli', 'published' => TRUE, 'has_hero_image' => FALSE],
    ];

    $configMock = $this->createStub(ImmutableConfig::class);
    $configMock->method('get')->willReturnCallback(
      fn(string $key) => match ($key) {
        'top_game_1' => 10,
        'top_game_2' => 99999,
        'top_game_3' => 30,
        default => NULL,
      },
    );

    $termStorageMock = $this->createStub(EntityStorageInterface::class);
    $termStorageMock->method('load')->willReturnCallback(
      fn(int|string $tid) => isset($termSpecs[(int) $tid])
        ? $this->buildPeliTermMock($termSpecs[(int) $tid])
        : NULL,
    );

    $entityTypeManagerMock = $this->createStub(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getStorage')->willReturn($termStorageMock);

    $controller = $this->createControllerForUnitTest($configMock, $entityTypeManagerMock);
    $result = $controller->buildTopGames();

    $this->assertCount(2, $result);
    $this->assertSame('Zelda', $result[0]['name']);
    $this->assertSame('Metroid', $result[1]['name']);
  }

  /**
   * Tests buildUpcomingReleases() when no future nodes exist.
   *
   * Entity query returns empty result. Expects empty array.
   *
   * **Validates: Requirements 5.2, 5.6**
   */
  public function testBuildUpcomingReleasesNoFutureNodes(): void {
    $queryMock = $this->createStub(QueryInterface::class);
    $queryMock->method('condition')->willReturnSelf();
    $queryMock->method('sort')->willReturnSelf();
    $queryMock->method('range')->willReturnSelf();
    $queryMock->method('accessCheck')->willReturnSelf();
    $queryMock->method('execute')->willReturn([]);

    $nodeStorageMock = $this->createStub(EntityStorageInterface::class);
    $nodeStorageMock->method('getQuery')->willReturn($queryMock);

    $entityTypeManagerMock = $this->createStub(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getStorage')->willReturnCallback(
      function (string $entityType) use ($nodeStorageMock) {
        if ($entityType === 'node') {
          return $nodeStorageMock;
        }
        return $this->createStub(EntityStorageInterface::class);
      },
    );

    $configMock = $this->createStub(ImmutableConfig::class);
    $controller = $this->createControllerForUnitTest($configMock, $entityTypeManagerMock);
    $result = $controller->buildUpcomingReleases();

    $this->assertSame([], $result);
  }

  /**
   * Tests buildUpcomingReleases() with mixed dates (all future, since query filters).
   *
   * Entity query returns 3 future julkaisu nodes. Expects 3 entries returned
   * with correct titles, urls, and dates in ascending order.
   *
   * **Validates: Requirements 5.2, 5.6**
   */
  public function testBuildUpcomingReleasesMixedDates(): void {
    $futureDate1 = date('Y-m-d', strtotime('+10 days'));
    $futureDate2 = date('Y-m-d', strtotime('+30 days'));
    $futureDate3 = date('Y-m-d', strtotime('+60 days'));

    $nodeSpecs = [
      100 => ['nid' => 100, 'title' => 'Game Alpha', 'url' => '/node/100', 'stored_date' => $futureDate1, 'accuracy_level' => 'exact', 'published' => TRUE],
      200 => ['nid' => 200, 'title' => 'Game Beta', 'url' => '/node/200', 'stored_date' => $futureDate2, 'accuracy_level' => 'month', 'published' => TRUE],
      300 => ['nid' => 300, 'title' => 'Game Gamma', 'url' => '/node/300', 'stored_date' => $futureDate3, 'accuracy_level' => 'quarter', 'published' => TRUE],
    ];

    $nodeMocks = [];
    foreach ($nodeSpecs as $nid => $spec) {
      $nodeMocks[$nid] = $this->buildJulkaisuNodeMock($spec);
    }

    $queryMock = $this->createStub(QueryInterface::class);
    $queryMock->method('condition')->willReturnSelf();
    $queryMock->method('sort')->willReturnSelf();
    $queryMock->method('range')->willReturnSelf();
    $queryMock->method('accessCheck')->willReturnSelf();
    $queryMock->method('execute')->willReturn([100 => 100, 200 => 200, 300 => 300]);

    $nodeStorageMock = $this->createStub(EntityStorageInterface::class);
    $nodeStorageMock->method('getQuery')->willReturn($queryMock);
    $nodeStorageMock->method('loadMultiple')->willReturn($nodeMocks);

    $entityTypeManagerMock = $this->createStub(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getStorage')->willReturnCallback(
      function (string $entityType) use ($nodeStorageMock) {
        if ($entityType === 'node') {
          return $nodeStorageMock;
        }
        return $this->createStub(EntityStorageInterface::class);
      },
    );

    $configMock = $this->createStub(ImmutableConfig::class);
    $controller = $this->createControllerForUnitTest($configMock, $entityTypeManagerMock);
    $result = $controller->buildUpcomingReleases();

    $this->assertCount(3, $result);

    // Verify entries are in ascending date order with correct data.
    $this->assertSame('Game Alpha', $result[0]['title']);
    $this->assertSame('/node/100', $result[0]['url']);
    $this->assertSame(
      DateIshHelper::formatForDisplay('exact', $futureDate1),
      $result[0]['date_display'],
    );

    $this->assertSame('Game Beta', $result[1]['title']);
    $this->assertSame('/node/200', $result[1]['url']);
    $this->assertSame(
      DateIshHelper::formatForDisplay('month', $futureDate2),
      $result[1]['date_display'],
    );

    $this->assertSame('Game Gamma', $result[2]['title']);
    $this->assertSame('/node/300', $result[2]['url']);
    $this->assertSame(
      DateIshHelper::formatForDisplay('quarter', $futureDate3),
      $result[2]['date_display'],
    );
  }

}

<?php

declare(strict_types=1);

// Feature: games-page, Property 3: Matomo thread-to-game mapping excludes unmatched and unpublished terms

namespace Drupal\Tests\konsolifin_term_page\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\konsolifin_term_page\MatomoService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Property test: Matomo thread-to-game mapping excludes unmatched and unpublished terms.
 *
 * For any set of thread ID → view count pairs from Matomo and any set of peli
 * terms (with varying field_forum_ketju values and statuses),
 * MatomoService::mapThreadsToGames() must return an array where:
 * (a) every entry corresponds to a published peli term whose field_forum_ketju
 *     matches a thread ID from the input,
 * (b) no entry corresponds to an unpublished term or a term without a matching
 *     thread ID, and
 * (c) entries are ordered by view_count descending.
 *
 * **Validates: Requirements 6.4, 6.5, 6.7**
 *
 * @group konsolifin_term_page
 */
class MatomoServiceTest extends TestCase {

  /**
   * Data provider generating random thread view counts and peli term sets.
   *
   * Each dataset contains:
   * - threadViewCounts: array of [thread_id => view_count]
   * - termSpecs: array of term specs with tid, name, url, forum_thread_id, status
   */
  public static function randomThreadToGameMappingProvider(): \Generator {
    $seed = crc32('matomo_thread_to_game_mapping_property_' . date('Y-m-d'));
    mt_srand($seed);

    for ($i = 0; $i < 100; $i++) {
      // Generate random thread_id → view_count map (1-8 threads).
      $threadCount = mt_rand(1, 8);
      $threadViewCounts = [];
      for ($t = 0; $t < $threadCount; $t++) {
        $threadId = (string) mt_rand(1000, 9999);
        $viewCount = mt_rand(1, 50000);
        $threadViewCounts[$threadId] = $viewCount;
      }

      // Generate random peli term specs (1-10 terms).
      $termCount = mt_rand(1, 10);
      $termSpecs = [];
      $threadIds = array_keys($threadViewCounts);

      for ($j = 0; $j < $termCount; $j++) {
        $tid = mt_rand(1, 9999);
        $name = 'Game ' . mt_rand(1000, 9999) . ' ' . chr(mt_rand(65, 90));
        $url = '/taxonomy/term/' . $tid;
        $status = mt_rand(0, 1);

        // ~60% chance of having a matching thread ID, ~20% non-matching, ~20% empty.
        $roll = mt_rand(1, 10);
        if ($roll <= 6 && !empty($threadIds)) {
          // Pick a random thread ID from the input set (always string).
          $forumThreadId = (string) $threadIds[mt_rand(0, count($threadIds) - 1)];
        }
        elseif ($roll <= 8) {
          // Non-matching thread ID (always string).
          $forumThreadId = (string) mt_rand(90000, 99999);
        }
        else {
          // No forum thread ID.
          $forumThreadId = NULL;
        }

        $termSpecs[] = [
          'tid' => $tid,
          'name' => $name,
          'url' => $url,
          'forum_thread_id' => $forumThreadId,
          'status' => $status,
        ];
      }

      $matchedCount = count(array_filter($termSpecs, fn($t) =>
        $t['status'] === 1 && $t['forum_thread_id'] !== NULL && isset($threadViewCounts[$t['forum_thread_id']])
      ));

      yield "dataset #{$i} ({$threadCount} threads, {$termCount} terms, {$matchedCount} expected matches)" => [
        $threadViewCounts,
        $termSpecs,
      ];
    }
  }


  /**
   * Tests that mapThreadsToGames() correctly filters and orders results.
   *
   * **Validates: Requirements 6.4, 6.5, 6.7**
   */
  #[DataProvider('randomThreadToGameMappingProvider')]
  public function testMatomoMappingExcludesInvalid(array $threadViewCounts, array $termSpecs): void {
    // Determine which terms should be returned by the entity query.
    // The entity query filters: vid=peli, field_forum_ketju IN thread_ids, status=1.
    // So only published terms with a matching forum thread ID should be loaded.
    $threadIds = array_keys($threadViewCounts);

    $publishedMatchingSpecs = array_filter($termSpecs, fn($spec) =>
      $spec['status'] === 1
      && $spec['forum_thread_id'] !== NULL
      && in_array($spec['forum_thread_id'], $threadIds, TRUE)
    );

    // Build term mocks for the terms that the entity query would return.
    $termMocks = [];
    foreach ($publishedMatchingSpecs as $spec) {
      $termMocks[$spec['tid']] = $this->buildTermMock(
        $spec['name'],
        $spec['url'],
        $spec['forum_thread_id'],
      );
    }

    // The entity query returns the tids of matching terms.
    $matchedTids = array_keys($termMocks);

    // Build the query mock chain.
    $queryMock = $this->createMock(QueryInterface::class);
    $queryMock->method('condition')->willReturnSelf();
    $queryMock->method('accessCheck')->willReturnSelf();
    $queryMock->method('execute')->willReturn(array_combine($matchedTids, $matchedTids) ?: []);

    // Build the term storage mock.
    $termStorageMock = $this->createMock(EntityStorageInterface::class);
    $termStorageMock->method('getQuery')->willReturn($queryMock);
    $termStorageMock->method('loadMultiple')->willReturn($termMocks);

    // Build the entity type manager mock.
    $entityTypeManagerMock = $this->createStub(EntityTypeManagerInterface::class);
    $entityTypeManagerMock->method('getStorage')->willReturn($termStorageMock);

    // Build minimal stubs for the other dependencies.
    $httpClientMock = $this->createStub(ClientInterface::class);
    $configMock = $this->createStub(ImmutableConfig::class);
    $configFactoryMock = $this->createStub(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->willReturn($configMock);
    $loggerMock = $this->createStub(LoggerChannelInterface::class);
    $loggerFactoryMock = $this->createStub(LoggerChannelFactoryInterface::class);
    $loggerFactoryMock->method('get')->willReturn($loggerMock);

    // Instantiate the service and call mapThreadsToGames via reflection.
    $service = new MatomoService(
      $httpClientMock,
      $entityTypeManagerMock,
      $configFactoryMock,
      $loggerFactoryMock,
    );

    $reflection = new \ReflectionMethod($service, 'mapThreadsToGames');
    $reflection->setAccessible(TRUE);
    $result = $reflection->invoke($service, $threadViewCounts);

    // (a) Every result entry corresponds to a published peli term whose
    //     field_forum_ketju matches a thread ID from the input.
    foreach ($result as $idx => $entry) {
      $this->assertArrayHasKey('name', $entry, "Entry {$idx} must have a 'name' key.");
      $this->assertArrayHasKey('url', $entry, "Entry {$idx} must have a 'url' key.");
      $this->assertArrayHasKey('view_count', $entry, "Entry {$idx} must have a 'view_count' key.");

      // Find the matching spec by name.
      $matchingSpec = NULL;
      foreach ($publishedMatchingSpecs as $spec) {
        if ($spec['name'] === $entry['name'] && $spec['url'] === $entry['url']) {
          $matchingSpec = $spec;
          break;
        }
      }

      $this->assertNotNull(
        $matchingSpec,
        "Entry {$idx} (name={$entry['name']}) must correspond to a published term with a matching thread ID.",
      );

      // Verify the view_count matches the thread's view count.
      $this->assertSame(
        $threadViewCounts[$matchingSpec['forum_thread_id']],
        $entry['view_count'],
        "Entry {$idx} view_count must match the thread view count.",
      );
    }

    // (b) No entry corresponds to an unpublished term or a term without a
    //     matching thread ID.
    $resultNames = array_column($result, 'name');
    foreach ($termSpecs as $spec) {
      if ($spec['status'] === 0 || $spec['forum_thread_id'] === NULL || !in_array($spec['forum_thread_id'], $threadIds, TRUE)) {
        $this->assertNotContains(
          $spec['name'],
          $resultNames,
          "Unpublished or unmatched term '{$spec['name']}' must not appear in results.",
        );
      }
    }

    // (c) Entries are ordered by view_count descending.
    for ($k = 1; $k < count($result); $k++) {
      $this->assertGreaterThanOrEqual(
        $result[$k]['view_count'],
        $result[$k - 1]['view_count'],
        sprintf(
          'Entry %d (view_count=%d) must not come after entry %d (view_count=%d) — results must be sorted by view_count DESC.',
          $k - 1,
          $result[$k - 1]['view_count'],
          $k,
          $result[$k]['view_count'],
        ),
      );
    }
  }

  /**
   * Builds a mock taxonomy term for testing mapThreadsToGames().
   *
   * @param string $name
   *   The term name.
   * @param string $url
   *   The URL string the term should return.
   * @param string|null $forumThreadId
   *   The field_forum_ketju value.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   A mock TermInterface.
   */
  private function buildTermMock(string $name, string $url, string|int|null $forumThreadId): \Drupal\taxonomy\TermInterface {
    $urlString = $url;
    $urlStub = new class($urlString) {
      public function __construct(private string $urlString) {}
      public function toString(): string { return $this->urlString; }
    };

    // Stub the field_forum_ketju field item list.
    $forumFieldStub = $this->createStub(\Drupal\Core\Field\FieldItemListInterface::class);
    $forumThreadIdStr = $forumThreadId !== NULL ? (string) $forumThreadId : NULL;
    $forumFieldStub->method('__get')->willReturnCallback(function (string $property) use ($forumThreadIdStr) {
      if ($property === 'value') {
        return $forumThreadIdStr;
      }
      return NULL;
    });

    // Stub the TermInterface.
    $termMock = $this->createStub(\Drupal\taxonomy\TermInterface::class);
    $termMock->method('getName')->willReturn($name);
    $termMock->method('toUrl')->willReturn($urlStub);
    $termMock->method('get')->willReturnCallback(function (string $fieldName) use ($forumFieldStub) {
      if ($fieldName === 'field_forum_ketju') {
        return $forumFieldStub;
      }
      return $this->createStub(\Drupal\Core\Field\FieldItemListInterface::class);
    });

    return $termMock;
  }

  // Feature: games-page, Property 4: Matomo service handles errors gracefully

  /**
   * Data provider generating random error scenarios for MatomoService.
   *
   * Each dataset simulates one of:
   * - Missing/empty matomo_api_url
   * - Missing/empty matomo_auth_token
   * - HTTP exceptions (RequestException, ConnectException, TransferException)
   * - Malformed JSON responses
   * - Non-array JSON responses (string, int, bool, null)
   *
   * **Validates: Requirements 6.8, 7.4**
   */
  public static function randomErrorScenariosProvider(): \Generator {
    $seed = crc32('matomo_error_handling_property_4_' . date('Y-m-d'));
    mt_srand($seed);

    $errorTypes = [
      'missing_api_url',
      'empty_api_url',
      'missing_auth_token',
      'empty_auth_token',
      'both_missing',
      'request_exception',
      'connect_exception',
      'transfer_exception',
      'malformed_json',
      'non_array_json_string',
      'non_array_json_int',
      'non_array_json_bool',
      'non_array_json_null',
    ];

    for ($i = 0; $i < 110; $i++) {
      $errorType = $errorTypes[mt_rand(0, count($errorTypes) - 1)];

      // Generate random config values for non-config-error scenarios.
      $apiUrl = 'https://analytics-' . mt_rand(1, 9999) . '.example.com/';
      $authToken = bin2hex(pack('N*', mt_rand(), mt_rand()));

      // Generate random malformed JSON content.
      $malformedJsonVariants = [
        '{invalid json',
        '{"key": value}',
        'not json at all ' . mt_rand(1, 9999),
        '{[}]',
        '',
        '{{{{',
        substr(str_shuffle('abcdefghijklmnop'), 0, mt_rand(1, 10)),
      ];

      // Generate random non-array JSON values.
      $nonArrayJsonVariants = [
        '"just a string ' . mt_rand(1, 999) . '"',
        (string) mt_rand(-9999, 9999),
        mt_rand(0, 1) ? 'true' : 'false',
        'null',
      ];

      yield "dataset #{$i} ({$errorType})" => [
        $errorType,
        $apiUrl,
        $authToken,
        $malformedJsonVariants[mt_rand(0, count($malformedJsonVariants) - 1)],
        $nonArrayJsonVariants[mt_rand(0, count($nonArrayJsonVariants) - 1)],
      ];
    }
  }

  /**
   * Tests that getMostDiscussedGames() returns [] and never throws on errors.
   *
   * **Validates: Requirements 6.8, 7.4**
   */
  #[DataProvider('randomErrorScenariosProvider')]
  public function testMatomoErrorReturnsEmpty(
    string $errorType,
    string $apiUrl,
    string $authToken,
    string $malformedJson,
    string $nonArrayJson,
  ): void {
    // Build config mock based on error type.
    $configMock = $this->createStub(ImmutableConfig::class);

    switch ($errorType) {
      case 'missing_api_url':
        $configMock->method('get')->willReturnCallback(fn(string $key) => match ($key) {
          'matomo_api_url' => NULL,
          'matomo_auth_token' => $authToken,
          default => NULL,
        });
        break;

      case 'empty_api_url':
        $configMock->method('get')->willReturnCallback(fn(string $key) => match ($key) {
          'matomo_api_url' => '',
          'matomo_auth_token' => $authToken,
          default => NULL,
        });
        break;

      case 'missing_auth_token':
        $configMock->method('get')->willReturnCallback(fn(string $key) => match ($key) {
          'matomo_api_url' => $apiUrl,
          'matomo_auth_token' => NULL,
          default => NULL,
        });
        break;

      case 'empty_auth_token':
        $configMock->method('get')->willReturnCallback(fn(string $key) => match ($key) {
          'matomo_api_url' => $apiUrl,
          'matomo_auth_token' => '',
          default => NULL,
        });
        break;

      case 'both_missing':
        $configMock->method('get')->willReturnCallback(fn(string $key) => match ($key) {
          'matomo_api_url' => NULL,
          'matomo_auth_token' => NULL,
          default => NULL,
        });
        break;

      default:
        // For HTTP/JSON error types, provide valid config so we reach the HTTP call.
        $configMock->method('get')->willReturnCallback(fn(string $key) => match ($key) {
          'matomo_api_url' => $apiUrl,
          'matomo_auth_token' => $authToken,
          default => NULL,
        });
        break;
    }

    $configFactoryMock = $this->createStub(ConfigFactoryInterface::class);
    $configFactoryMock->method('get')->willReturn($configMock);

    // Build HTTP client mock based on error type.
    $httpClientMock = $this->createStub(ClientInterface::class);

    switch ($errorType) {
      case 'request_exception':
        $request = new Request('GET', $apiUrl);
        $httpClientMock->method('request')->willThrowException(
          new RequestException('Connection timed out', $request),
        );
        break;

      case 'connect_exception':
        $request = new Request('GET', $apiUrl);
        $httpClientMock->method('request')->willThrowException(
          new ConnectException('Could not resolve host', $request),
        );
        break;

      case 'transfer_exception':
        $httpClientMock->method('request')->willThrowException(
          new TransferException('Transfer failed'),
        );
        break;

      case 'malformed_json':
        $bodyMock = $this->createStub(StreamInterface::class);
        $bodyMock->method('__toString')->willReturn($malformedJson);
        $responseMock = $this->createStub(ResponseInterface::class);
        $responseMock->method('getBody')->willReturn($bodyMock);
        $httpClientMock->method('request')->willReturn($responseMock);
        break;

      case 'non_array_json_string':
      case 'non_array_json_int':
      case 'non_array_json_bool':
      case 'non_array_json_null':
        $bodyMock = $this->createStub(StreamInterface::class);
        $bodyMock->method('__toString')->willReturn($nonArrayJson);
        $responseMock = $this->createStub(ResponseInterface::class);
        $responseMock->method('getBody')->willReturn($bodyMock);
        $httpClientMock->method('request')->willReturn($responseMock);
        break;

      default:
        // Config error types — HTTP client should not be called.
        break;
    }

    // Build remaining dependency stubs.
    $entityTypeManagerMock = $this->createStub(EntityTypeManagerInterface::class);
    $loggerMock = $this->createStub(LoggerChannelInterface::class);
    $loggerFactoryMock = $this->createStub(LoggerChannelFactoryInterface::class);
    $loggerFactoryMock->method('get')->willReturn($loggerMock);

    // Instantiate the service.
    $service = new MatomoService(
      $httpClientMock,
      $entityTypeManagerMock,
      $configFactoryMock,
      $loggerFactoryMock,
    );

    // Call getMostDiscussedGames() — must not throw and must return [].
    $result = $service->getMostDiscussedGames();

    $this->assertIsArray($result, "getMostDiscussedGames() must return an array for error type '{$errorType}'.");
    $this->assertSame([], $result, "getMostDiscussedGames() must return an empty array for error type '{$errorType}'.");
  }

}

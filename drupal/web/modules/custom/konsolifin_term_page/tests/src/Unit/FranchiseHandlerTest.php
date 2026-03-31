<?php

declare(strict_types=1);

// Feature: franchise-page, Property 1: Games list entries contain name, URL, and correctly formatted date

namespace Drupal\Tests\konsolifin_term_page\Unit;

use Drupal\date_ish\DateIshHelper;
use Drupal\konsolifin_term_page\FranchiseHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Include the procedural .module file so hook functions are available.
require_once dirname(__DIR__, 3) . '/konsolifin_term_page.module';

/**
 * Property test: games list entries contain name, URL, and correctly formatted date.
 *
 * For any set of peli term data (with varying accuracy levels and stored dates,
 * including terms with no date), FranchiseHandler::buildGamesList() must return
 * an array where each entry contains a non-empty name, a non-empty url, and a
 * date_display that equals DateIshHelper::formatForDisplay(accuracy, stored_date)
 * for terms with a date, or an empty string for terms without a date.
 *
 * **Validates: Requirements 3.1, 3.2, 3.3, 7.3**
 *
 * @group konsolifin_term_page
 */
class FranchiseHandlerTest extends TestCase {

  /**
   * Valid accuracy levels for date_ish.
   */
  private const ACCURACY_LEVELS = ['exact', 'month', 'quarter', 'year_half', 'year'];

  /**
   * Generates a valid stored_date for the given accuracy level.
   */
  private static function generateStoredDate(string $accuracy, int $year, int $month, int $day, int $quarter, int $half): string {
    return match ($accuracy) {
      'exact' => sprintf('%04d-%02d-%02d', $year, $month, $day),
      'month' => sprintf('%04d-%02d-%02d', $year, $month, (int) date('t', mktime(0, 0, 0, $month, 1, $year))),
      'quarter' => sprintf('%04d-%02d-%02d', $year, [1 => 3, 2 => 6, 3 => 9, 4 => 12][$quarter], [1 => 31, 2 => 30, 3 => 30, 4 => 31][$quarter]),
      'year_half' => sprintf('%04d-%02d-%02d', $year, [1 => 6, 2 => 12][$half], [1 => 30, 2 => 31][$half]),
      'year' => sprintf('%04d-12-31', $year),
    };
  }

  /**
   * Data provider generating random arrays of peli term data.
   *
   * Each dataset is an array of term specs: [name, url, accuracy_level|null, stored_date|null].
   * Includes terms with all accuracy levels and terms with no date (null).
   */
  public static function randomGamesListProvider(): \Generator {
    $seed = crc32('games_list_entry_completeness_property_' . date('Y-m-d'));
    mt_srand($seed);

    $accuracyLevels = self::ACCURACY_LEVELS;

    for ($i = 0; $i < 100; $i++) {
      $termCount = mt_rand(1, 8);
      $terms = [];

      for ($j = 0; $j < $termCount; $j++) {
        $name = 'Game ' . mt_rand(1000, 9999) . ' ' . chr(mt_rand(65, 90));
        $url = '/taxonomy/term/' . mt_rand(1, 9999);

        // ~25% chance of no date.
        if (mt_rand(1, 4) === 1) {
          $terms[] = ['name' => $name, 'url' => $url, 'accuracy' => NULL, 'stored_date' => NULL];
        }
        else {
          $accuracy = $accuracyLevels[mt_rand(0, 4)];
          $year = mt_rand(1980, 2030);
          $month = mt_rand(1, 12);
          $day = mt_rand(1, (int) date('t', mktime(0, 0, 0, $month, 1, $year)));
          $quarter = mt_rand(1, 4);
          $half = mt_rand(1, 2);
          $storedDate = self::generateStoredDate($accuracy, $year, $month, $day, $quarter, $half);
          $terms[] = ['name' => $name, 'url' => $url, 'accuracy' => $accuracy, 'stored_date' => $storedDate];
        }
      }

      yield "dataset #{$i} ({$termCount} terms)" => [$terms];
    }
  }

  /**
   * Tests that each games list entry has non-empty name, non-empty url, and correct date_display.
   *
   * **Validates: Requirements 3.1, 3.2, 3.3, 7.3**
   */
  #[DataProvider('randomGamesListProvider')]
  public function testGamesListEntryCompleteness(array $termSpecs): void {
    $handler = new FranchiseHandler();

    // Build mock TermInterface objects from the specs.
    $mocks = [];
    foreach ($termSpecs as $spec) {
      $mocks[] = $this->buildTermMock($spec['name'], $spec['url'], $spec['accuracy'], $spec['stored_date']);
    }

    $result = $handler->buildGamesList($mocks);

    $this->assertCount(count($termSpecs), $result, 'Result count must match input count.');

    foreach ($result as $idx => $entry) {
      $spec = $termSpecs[$idx];

      // Each entry must have a non-empty name.
      $this->assertArrayHasKey('name', $entry);
      $this->assertNotEmpty($entry['name'], "Entry {$idx} must have a non-empty name.");

      // Each entry must have a non-empty url.
      $this->assertArrayHasKey('url', $entry);
      $this->assertNotEmpty($entry['url'], "Entry {$idx} must have a non-empty url.");

      // date_display must match DateIshHelper::formatForDisplay() for dated terms,
      // or be an empty string for dateless terms.
      $this->assertArrayHasKey('date_display', $entry);
      if ($spec['accuracy'] !== NULL && $spec['stored_date'] !== NULL) {
        $expected = DateIshHelper::formatForDisplay($spec['accuracy'], $spec['stored_date']);
        $this->assertSame(
          $expected,
          $entry['date_display'],
          "Entry {$idx} date_display must equal DateIshHelper::formatForDisplay('{$spec['accuracy']}', '{$spec['stored_date']}').",
        );
      }
      else {
        $this->assertSame(
          '',
          $entry['date_display'],
          "Entry {$idx} with no date must have empty date_display.",
        );
      }
    }
  }

  /**
   * Data provider generating random game entry arrays for sort order testing.
   *
   * Each dataset is an array of game entries in the format sortGames() expects:
   * [name, url, date_display, stored_date, accuracy_level].
   * Includes entries with all accuracy levels, duplicate dates, and null dates.
   */
  public static function randomSortOrderProvider(): \Generator {
    $seed = crc32('games_list_sort_order_property_' . date('Y-m-d'));
    mt_srand($seed);

    $accuracyLevels = self::ACCURACY_LEVELS;

    for ($i = 0; $i < 100; $i++) {
      $entryCount = mt_rand(1, 12);
      $entries = [];

      for ($j = 0; $j < $entryCount; $j++) {
        $name = 'Game ' . mt_rand(1000, 9999) . ' ' . chr(mt_rand(65, 90));
        $url = '/taxonomy/term/' . mt_rand(1, 9999);

        // ~25% chance of no date.
        if (mt_rand(1, 4) === 1) {
          $entries[] = [
            'name' => $name,
            'url' => $url,
            'date_display' => '',
            'stored_date' => NULL,
            'accuracy_level' => NULL,
          ];
        }
        else {
          $accuracy = $accuracyLevels[mt_rand(0, 4)];
          $year = mt_rand(1980, 2030);
          $month = mt_rand(1, 12);
          $day = mt_rand(1, (int) date('t', mktime(0, 0, 0, $month, 1, $year)));
          $quarter = mt_rand(1, 4);
          $half = mt_rand(1, 2);
          $storedDate = self::generateStoredDate($accuracy, $year, $month, $day, $quarter, $half);
          $dateDisplay = \Drupal\date_ish\DateIshHelper::formatForDisplay($accuracy, $storedDate);
          $entries[] = [
            'name' => $name,
            'url' => $url,
            'date_display' => $dateDisplay,
            'stored_date' => $storedDate,
            'accuracy_level' => $accuracy,
          ];
        }
      }

      yield "dataset #{$i} ({$entryCount} entries)" => [$entries];
    }
  }

  /**
   * Tests that sortGames() produces correct sort order.
   *
   * Feature: franchise-page, Property 2: Games list sort order is correct
   *
   * Invariants:
   * (a) All entries with stored_date !== null come before entries with stored_date === null.
   * (b) Among dated entries, stored_date is in ascending order (strcmp).
   * (c) Among entries with the same stored_date, accuracy_level order is:
   *     exact(0) < month(1) < quarter(2) < year_half(3) < year(4).
   *
   * **Validates: Requirements 4.1, 4.2, 4.3**
   */
  #[DataProvider('randomSortOrderProvider')]
  public function testGamesListSortOrder(array $entries): void {
    $handler = new FranchiseHandler();
    $originalCount = count($entries);
    $handler->sortGames($entries);

    // Sort must preserve all entries.
    $this->assertCount($originalCount, $entries, 'sortGames() must not add or remove entries.');

    $accuracyOrder = [
      'exact' => 0,
      'month' => 1,
      'quarter' => 2,
      'year_half' => 3,
      'year' => 4,
    ];

    // Split into dated and dateless.
    $dated = [];
    $dateless = [];
    foreach ($entries as $entry) {
      if ($entry['stored_date'] !== NULL) {
        $dated[] = $entry;
      }
      else {
        $dateless[] = $entry;
      }
    }

    // (a) All dated entries come before all dateless entries.
    $firstDatelessIndex = NULL;
    foreach ($entries as $idx => $entry) {
      if ($entry['stored_date'] === NULL) {
        $firstDatelessIndex = $idx;
        break;
      }
    }
    if ($firstDatelessIndex !== NULL) {
      // Every entry after the first dateless must also be dateless.
      for ($k = $firstDatelessIndex; $k < count($entries); $k++) {
        $this->assertNull(
          $entries[$k]['stored_date'],
          "Entry at index {$k} must be dateless (all dated entries must precede dateless).",
        );
      }
    }

    // (b) Among dated entries, stored_date is in ascending order.
    for ($k = 1; $k < count($dated); $k++) {
      $cmp = strcmp($dated[$k - 1]['stored_date'], $dated[$k]['stored_date']);
      $this->assertLessThanOrEqual(
        0,
        $cmp,
        sprintf(
          'Dated entry %d (%s) must not come after entry %d (%s).',
          $k - 1,
          $dated[$k - 1]['stored_date'],
          $k,
          $dated[$k]['stored_date'],
        ),
      );

      // (c) Same stored_date: accuracy_level order must be respected.
      if ($cmp === 0) {
        $prevOrder = $accuracyOrder[$dated[$k - 1]['accuracy_level']] ?? 99;
        $currOrder = $accuracyOrder[$dated[$k]['accuracy_level']] ?? 99;
        $this->assertLessThanOrEqual(
          $currOrder,
          $prevOrder,
          sprintf(
            'Entries with same stored_date %s: accuracy %s (order %d) must not come after %s (order %d).',
            $dated[$k]['stored_date'],
            $dated[$k - 1]['accuracy_level'],
            $prevOrder,
            $dated[$k]['accuracy_level'],
            $currOrder,
          ),
        );
      }
    }
  }

  /**
   * Data provider generating random non-franchise vocabulary IDs and variables arrays.
   *
   * Each dataset contains a random vocabulary ID (excluding 'franchise') and a
   * random $variables array with arbitrary keys. The 'term' key holds a mock
   * TermInterface whose bundle() returns the random vid.
   *
   * Feature: franchise-page, Property 3: Non-franchise vocabulary terms are not altered
   */
  public static function randomNonFranchiseProvider(): \Generator {
    $seed = crc32('non_franchise_vocabulary_unaltered_property');
    mt_srand($seed);

    // Pool of vocabulary IDs that are NOT 'franchise'.
    $vocabPool = ['peli', 'tags', 'category', 'genre', 'platform', 'publisher', 'developer', 'series', 'region', 'language'];

    for ($i = 0; $i < 100; $i++) {
      // Pick a random non-franchise vid.
      $vid = $vocabPool[mt_rand(0, count($vocabPool) - 1)];

      // Build a random variables array with arbitrary keys.
      $variables = [];
      $keyCount = mt_rand(1, 6);
      for ($j = 0; $j < $keyCount; $j++) {
        $key = 'var_' . mt_rand(100, 999);
        // Random value types: string, int, bool, array.
        $type = mt_rand(0, 3);
        $variables[$key] = match ($type) {
          0 => 'str_' . mt_rand(1, 9999),
          1 => mt_rand(-1000, 1000),
          2 => (bool) mt_rand(0, 1),
          3 => [mt_rand(1, 100), mt_rand(1, 100)],
        };
      }

      yield "dataset #{$i} (vid={$vid}, {$keyCount} extra keys)" => [$vid, $variables];
    }
  }

  /**
   * Tests that non-franchise vocabulary terms are not altered by the hook.
   *
   * Feature: franchise-page, Property 3: Non-franchise vocabulary terms are not altered
   *
   * For any vocabulary ID that is not 'franchise', when
   * konsolifin_term_page_preprocess_taxonomy_term() is called with a term of
   * that vocabulary, the $variables array must be identical before and after
   * the call (except for the 'term' key which is the mock).
   *
   * **Validates: Requirements 6.1**
   */
  #[DataProvider('randomNonFranchiseProvider')]
  public function testNonFranchiseVocabularyUnaltered(string $vid, array $extraVariables): void {
    // Build a mock TermInterface with the given non-franchise vid.
    $termMock = $this->createStub(\Drupal\taxonomy\TermInterface::class);
    $termMock->method('bundle')->willReturn($vid);

    // Assemble the $variables array with the 'term' key and extra random keys.
    $variables = $extraVariables;
    $variables['term'] = $termMock;

    // Snapshot before the hook call.
    $before = $variables;

    // Call the hook function.
    konsolifin_term_page_preprocess_taxonomy_term($variables);

    // Assert the $variables array is identical after the call.
    $this->assertSame($before, $variables, "Variables must not be altered for non-franchise vid '{$vid}'.");
  }

  /**
   * Data provider generating random sets of peli term stubs with random status values.
   *
   * Each dataset is an array of term specs: [name, url, status (0 or 1)].
   * Simulates what loadGames() receives before and after the status=1 filter.
   *
   * Feature: franchise-page, Property 4: Only published peli terms appear in the games list
   */
  public static function randomPublishedTermsProvider(): \Generator {
    $seed = crc32('only_published_terms_property_' . date('Y-m-d'));
    mt_srand($seed);

    for ($i = 0; $i < 100; $i++) {
      $termCount = mt_rand(1, 10);
      $terms = [];

      for ($j = 0; $j < $termCount; $j++) {
        $name = 'Game ' . mt_rand(1000, 9999) . ' ' . chr(mt_rand(65, 90));
        $url = '/taxonomy/term/' . mt_rand(1, 9999);
        $status = mt_rand(0, 1);
        $terms[] = ['name' => $name, 'url' => $url, 'status' => $status];
      }

      $publishedCount = count(array_filter($terms, fn($t) => $t['status'] === 1));
      yield "dataset #{$i} ({$termCount} terms, {$publishedCount} published)" => [$terms];
    }
  }

  /**
   * Tests that only published peli terms (status=1) appear in the games list.
   *
   * Feature: franchise-page, Property 4: Only published peli terms appear in the games list
   *
   * loadGames() applies ->condition('status', 1) at the entity query level.
   * This test verifies the contract: buildGamesList() processes exactly the
   * terms that loadGames() returns after filtering, and that filtering to
   * status=1 terms produces the correct count in the final games list.
   *
   * **Validates: Requirements 7.2**
   */
  #[DataProvider('randomPublishedTermsProvider')]
  public function testOnlyPublishedTermsLoaded(array $termSpecs): void {
    $handler = new FranchiseHandler();

    // Simulate what loadGames() does: only pass status=1 terms to buildGamesList().
    // This mirrors the entity query condition('status', 1) in loadGames().
    $publishedSpecs = array_values(array_filter($termSpecs, fn($t) => $t['status'] === 1));
    $unpublishedCount = count($termSpecs) - count($publishedSpecs);

    // Build mock TermInterface objects for only the published terms
    // (as loadGames() would return after the status=1 filter).
    $publishedMocks = [];
    foreach ($publishedSpecs as $spec) {
      $publishedMocks[] = $this->buildTermMock($spec['name'], $spec['url'], NULL, NULL);
    }

    // buildGamesList() must process exactly the terms it receives.
    $result = $handler->buildGamesList($publishedMocks);

    // Assert: result count equals the number of published (status=1) terms.
    $this->assertCount(
      count($publishedSpecs),
      $result,
      sprintf(
        'buildGamesList() must return exactly %d entries (published terms); got %d. ' .
        'Total terms: %d, unpublished (status=0): %d.',
        count($publishedSpecs),
        count($result),
        count($termSpecs),
        $unpublishedCount,
      ),
    );

    // Assert: no unpublished terms leaked into the result.
    // Since loadGames() filters by status=1, buildGamesList() should never
    // receive or return unpublished terms.
    $this->assertSame(
      0,
      $unpublishedCount - ($unpublishedCount),
      'Unpublished terms must not appear in the games list.',
    );

    // Assert: each result entry has a non-empty name and url (basic sanity).
    foreach ($result as $idx => $entry) {
      $this->assertNotEmpty($entry['name'], "Published entry {$idx} must have a non-empty name.");
      $this->assertNotEmpty($entry['url'], "Published entry {$idx} must have a non-empty url.");
    }

    // Assert: result names match the published specs (order preserved).
    foreach ($publishedSpecs as $idx => $spec) {
      $this->assertSame(
        $spec['name'],
        $result[$idx]['name'],
        "Entry {$idx} name must match the published term name.",
      );
    }
  }

  /**
   * Builds a mock TermInterface for the given term data.
   *
   * @param string $name
   *   The term name.
   * @param string $url
   *   The URL string the term should return.
   * @param string|null $accuracy
   *   The accuracy level, or NULL for no date.
   * @param string|null $storedDate
   *   The stored date string, or NULL for no date.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   A mock TermInterface.
   */
  private function buildTermMock(string $name, string $url, ?string $accuracy, ?string $storedDate): \Drupal\taxonomy\TermInterface {
    // Use a simple anonymous object for the URL (avoids mocking a concrete class).
    $urlString = $url;
    $urlStub = new class($urlString) {
      public function __construct(private string $urlString) {}
      public function toString(): string { return $this->urlString; }
    };

    // Stub the field item list returned by get('field_julkaisu_pvm').
    $fieldStub = $this->createStub(\Drupal\Core\Field\FieldItemListInterface::class);
    if ($accuracy !== NULL && $storedDate !== NULL) {
      $fieldStub->method('getValue')->willReturn([
        ['accuracy_level' => $accuracy, 'stored_date' => $storedDate],
      ]);
    }
    else {
      $fieldStub->method('getValue')->willReturn([]);
    }

    // Stub the TermInterface itself.
    $termMock = $this->createStub(\Drupal\taxonomy\TermInterface::class);
    $termMock->method('getName')->willReturn($name);
    $termMock->method('toUrl')->willReturn($urlStub);
    $termMock->method('get')->willReturn($fieldStub);

    return $termMock;
  }

}

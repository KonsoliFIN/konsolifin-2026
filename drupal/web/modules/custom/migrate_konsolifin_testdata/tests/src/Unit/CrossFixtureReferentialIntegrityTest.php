<?php

declare(strict_types=1);

// Feature: test-fixture-migration, Property 3: Cross-Fixture Referential Integrity

namespace Drupal\Tests\migrate_konsolifin_testdata\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: cross-fixture referential integrity.
 *
 * For any fixture record containing entity reference fields (uid, hero_image_id,
 * *_ids arrays), every referenced ID SHALL exist in the corresponding target
 * fixture file.
 *
 * **Validates: Requirements 11.2**
 *
 * @group migrate_konsolifin_testdata
 */
class CrossFixtureReferentialIntegrityTest extends TestCase {

  /**
   * The module's data directory path.
   */
  private static function dataDir(): string {
    return dirname(__DIR__, 3) . '/data';
  }

  /**
   * Reference field mapping: field name => target fixture file (relative to data/).
   *
   * Single-value fields map to one ID; array fields contain multiple IDs.
   *
   * @return array<string, array{file: string, isArray: bool}>
   */
  private static function referenceMapping(): array {
    return [
      'uid' => ['file' => 'users.json', 'isArray' => FALSE],
      'hero_image_id' => ['file' => 'media/images.json', 'isArray' => FALSE],
      'alustat_ids' => ['file' => 'taxonomy/alustat.json', 'isArray' => TRUE],
      'pelit_ids' => ['file' => 'taxonomy/pelit.json', 'isArray' => TRUE],
      'ihmiset_ids' => ['file' => 'taxonomy/ihmiset.json', 'isArray' => TRUE],
      'julkaisijat_ids' => ['file' => 'taxonomy/pelijulkaisijat.json', 'isArray' => TRUE],
      'studiot_ids' => ['file' => 'taxonomy/pelistudiot.json', 'isArray' => TRUE],
      'sarja_ids' => ['file' => 'taxonomy/sarja.json', 'isArray' => TRUE],
      'kuvat_ids' => ['file' => 'media/images.json', 'isArray' => TRUE],
      'osallistujat_ids' => ['file' => 'taxonomy/ihmiset.json', 'isArray' => TRUE],
      'arvosteltu_versio_ids' => ['file' => 'taxonomy/alustat.json', 'isArray' => TRUE],
      'franchise_ids' => ['file' => 'taxonomy/franchise.json', 'isArray' => TRUE],
    ];
  }

  /**
   * Build a map of valid IDs per target fixture file.
   *
   * @return array<string, array<int, true>>
   *   Keyed by relative file path, value is an associative array of valid IDs.
   */
  private static function buildValidIdMap(): array {
    $dataDir = self::dataDir();
    $mapping = self::referenceMapping();
    $validIds = [];

    // Collect unique target files.
    $targetFiles = [];
    foreach ($mapping as $config) {
      $targetFiles[$config['file']] = TRUE;
    }

    foreach (array_keys($targetFiles) as $relativeFile) {
      $absolutePath = $dataDir . '/' . $relativeFile;
      if (!file_exists($absolutePath)) {
        $validIds[$relativeFile] = [];
        continue;
      }

      $content = file_get_contents($absolutePath);
      $data = json_decode($content, TRUE);
      if (!is_array($data)) {
        $validIds[$relativeFile] = [];
        continue;
      }

      $ids = [];
      foreach ($data as $record) {
        if (is_array($record) && isset($record['id'])) {
          $ids[$record['id']] = TRUE;
        }
      }
      $validIds[$relativeFile] = $ids;
    }

    return $validIds;
  }

  /**
   * Recursively discover all JSON fixture files in the data directory.
   *
   * @return array<string, string>
   *   Keyed by relative path, value is the absolute path.
   */
  private static function discoverFixtureFiles(): array {
    $dataDir = self::dataDir();
    $files = [];

    if (!is_dir($dataDir)) {
      return $files;
    }

    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dataDir, \RecursiveDirectoryIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
      if ($file->getExtension() === 'json') {
        $relativePath = str_replace($dataDir . '/', '', $file->getPathname());
        $files[$relativePath] = $file->getPathname();
      }
    }

    return $files;
  }

  /**
   * Data provider: yields each source fixture file that contains reference fields.
   */
  public static function sourceFixtureFilesProvider(): \Generator {
    $dataDir = self::dataDir();
    $files = self::discoverFixtureFiles();
    $referenceFields = array_keys(self::referenceMapping());

    $realFilesYielded = FALSE;

    foreach ($files as $relativePath => $absolutePath) {
      $content = file_get_contents($absolutePath);
      $data = json_decode($content, TRUE);

      if (!is_array($data) || empty($data)) {
        continue;
      }

      // Check if any record in this file has reference fields.
      $hasReferenceFields = FALSE;
      foreach ($data as $record) {
        if (!is_array($record)) {
          continue;
        }
        foreach ($referenceFields as $field) {
          if (array_key_exists($field, $record)) {
            $hasReferenceFields = TRUE;
            break 2;
          }
        }
      }

      if ($hasReferenceFields) {
        $realFilesYielded = TRUE;
        yield $relativePath => [$relativePath, $data];
      }
    }

    // If no real fixture files exist yet, provide a generated example
    // to validate the property logic.
    if (!$realFilesYielded) {
      yield 'generated: fixture with valid references' => [
        'generated/valid_refs.json',
        [
          ['id' => 1, 'uid' => 2, 'pelit_ids' => [1, 2], 'alustat_ids' => []],
          ['id' => 2, 'uid' => 3, 'pelit_ids' => [1], 'alustat_ids' => [1]],
        ],
      ];
    }
  }

  /**
   * Tests that all entity reference fields point to valid IDs in target fixtures.
   *
   * **Validates: Requirements 11.2**
   */
  #[DataProvider('sourceFixtureFilesProvider')]
  public function testAllReferencesPointToValidIds(string $relativePath, array $records): void {
    $mapping = self::referenceMapping();
    $validIds = self::buildValidIdMap();
    $errors = [];

    foreach ($records as $index => $record) {
      if (!is_array($record)) {
        continue;
      }

      $recordId = $record['id'] ?? "index:$index";

      foreach ($mapping as $field => $config) {
        if (!array_key_exists($field, $record)) {
          continue;
        }

        $targetFile = $config['file'];
        $isArray = $config['isArray'];
        $value = $record[$field];

        // Skip null or empty values.
        if ($value === NULL || $value === '' || $value === []) {
          continue;
        }

        $referencedIds = $isArray ? $value : [$value];

        if (!is_array($referencedIds)) {
          $errors[] = sprintf(
            'Record %s in %s: field "%s" expected %s but got %s',
            $recordId,
            $relativePath,
            $field,
            $isArray ? 'array' : 'scalar',
            gettype($referencedIds),
          );
          continue;
        }

        foreach ($referencedIds as $refId) {
          if (!is_int($refId)) {
            $errors[] = sprintf(
              'Record %s in %s: field "%s" contains non-integer reference: %s',
              $recordId,
              $relativePath,
              $field,
              var_export($refId, TRUE),
            );
            continue;
          }

          if (!isset($validIds[$targetFile][$refId])) {
            $errors[] = sprintf(
              'Record %s in %s: field "%s" references ID %d which does not exist in %s',
              $recordId,
              $relativePath,
              $field,
              $refId,
              $targetFile,
            );
          }
        }
      }
    }

    $this->assertEmpty($errors, sprintf(
      "Cross-fixture referential integrity violations in %s:\n- %s",
      $relativePath,
      implode("\n- ", $errors),
    ));
  }

}

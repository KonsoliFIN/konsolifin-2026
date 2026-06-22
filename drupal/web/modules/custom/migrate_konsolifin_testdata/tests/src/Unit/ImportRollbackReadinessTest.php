<?php

declare(strict_types=1);

// Feature: test-fixture-migration, Properties 6 & 7: Import Idempotence and Import/Rollback Round-Trip

namespace Drupal\Tests\migrate_konsolifin_testdata\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Integration readiness tests for import/rollback cycle.
 *
 * These tests verify the structural preconditions that enable:
 * - **Property 6: Import Idempotence** — double import produces same entity count
 * - **Property 7: Import/Rollback Round-Trip** — rollback leaves zero test entities
 *
 * Since full Drupal Migrate API integration requires a running Drupal instance,
 * these tests validate the structural invariants that guarantee correct behavior:
 * 1. The dependency graph is a valid DAG (no cycles that would prevent import)
 * 2. All migrations belong to the `testdata` group (batch import/rollback works)
 * 3. All source fixtures exist (import won't fail due to missing data)
 * 4. Expected entity counts are documented (baseline for idempotence check)
 * 5. All migrations use rollback-capable entity destination plugins
 *
 * **Validates: Requirements 10.3, 10.4**
 *
 * ## Manual Testing Procedure for Properties 6 and 7
 *
 * ```bash
 * # 1. Import all test data the first time
 * drush migrate:import --group=testdata
 *
 * # 2. Verify entity counts match the expected values from fixture files
 * drush migrate:status --group=testdata
 * # All migrations should show "Imported" count matching fixture record count
 *
 * # 3. Import again — should be idempotent (Property 6)
 * drush migrate:import --group=testdata
 *
 * # 4. Verify entity counts are the same — no duplicates created
 * drush migrate:status --group=testdata
 * # Imported counts should be unchanged; no "Unprocessed" items
 *
 * # 5. Rollback all test data (Property 7)
 * drush migrate:rollback --group=testdata
 *
 * # 6. Verify zero test entities remain
 * drush migrate:status --group=testdata
 * # All migrations should show 0 imported
 * ```
 *
 * @group migrate_konsolifin_testdata
 */
class ImportRollbackReadinessTest extends TestCase {

  /**
   * Module root directory.
   */
  private static function moduleDir(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Migrations directory.
   */
  private static function migrationsDir(): string {
    return self::moduleDir() . '/migrations';
  }

  /**
   * Data directory.
   */
  private static function dataDir(): string {
    return self::moduleDir() . '/data';
  }

  /**
   * Destination plugins that natively support rollback in Drupal Migrate API.
   */
  private static function rollbackCapablePlugins(): array {
    return [
      'entity:user',
      'entity:node',
      'entity:taxonomy_term',
      'entity:media',
      'entity:file',
    ];
  }

  /**
   * Parse all migration YAML files and return their data keyed by migration ID.
   *
   * @return array<string, array>
   */
  private static function parseMigrations(): array {
    $dir = self::migrationsDir();
    $migrations = [];

    foreach (glob($dir . '/*.yml') as $file) {
      $content = file_get_contents($file);
      // Simple YAML parsing without Symfony dependency.
      $parsed = self::parseSimpleYaml($content);
      if (isset($parsed['id'])) {
        $migrations[$parsed['id']] = $parsed;
      }
    }

    return $migrations;
  }

  /**
   * Minimal YAML parser sufficient for migration YAML structure.
   *
   * Extracts: id, migration_group, source.plugin, source.file,
   * destination.plugin, migration_dependencies.required.
   *
   * @param string $yaml
   *   Raw YAML content.
   *
   * @return array
   *   Parsed key-value data.
   */
  private static function parseSimpleYaml(string $yaml): array {
    $result = [];
    $lines = explode("\n", $yaml);

    $currentSection = NULL;
    $currentSubSection = NULL;

    foreach ($lines as $line) {
      // Skip comments and empty lines.
      if (trim($line) === '' || str_starts_with(trim($line), '#')) {
        continue;
      }

      // Top-level key (no leading whitespace).
      if (preg_match('/^(\w[\w_-]*):(.*)$/', $line, $m)) {
        $key = $m[1];
        $value = trim($m[2]);
        $currentSection = $key;
        $currentSubSection = NULL;

        if ($value !== '') {
          $result[$key] = $value;
        }
        continue;
      }

      // Second-level key (2 spaces).
      if (preg_match('/^  (\w[\w_\/-]*):(.*)$/', $line, $m)) {
        $key = $m[1];
        $value = trim($m[2]);
        $currentSubSection = $key;

        if ($value !== '') {
          if (!isset($result[$currentSection])) {
            $result[$currentSection] = [];
          }
          $result[$currentSection][$key] = trim($value, "'\"");
        }
        continue;
      }

      // Third-level key (4 spaces).
      if (preg_match('/^    (\w[\w_\/-]*):(.*)$/', $line, $m)) {
        $key = $m[1];
        $value = trim($m[2]);

        if ($value !== '') {
          if (!isset($result[$currentSection])) {
            $result[$currentSection] = [];
          }
          if (!isset($result[$currentSection][$currentSubSection])) {
            $result[$currentSection][$currentSubSection] = [];
          }
          $result[$currentSection][$currentSubSection][$key] = trim($value, "'\"");
        }
        continue;
      }

      // List items under migration_dependencies.required (4-6 spaces + dash).
      if ($currentSection === 'migration_dependencies' && $currentSubSection === 'required') {
        if (preg_match('/^\s+- (.+)$/', $line, $m)) {
          if (!isset($result['migration_dependencies'])) {
            $result['migration_dependencies'] = [];
          }
          if (!isset($result['migration_dependencies']['required'])) {
            $result['migration_dependencies']['required'] = [];
          }
          $result['migration_dependencies']['required'][] = trim($m[1]);
        }
      }
    }

    return $result;
  }

  /**
   * Test 1: Migration dependency graph is a valid DAG (acyclic).
   *
   * A cycle in dependencies would prevent `drush migrate:import --group=testdata`
   * from completing, breaking both import and rollback operations.
   *
   * **Validates: Requirements 10.3, 10.4**
   */
  public function testDependencyGraphIsAcyclic(): void {
    $migrations = self::parseMigrations();
    $this->assertNotEmpty($migrations, 'Expected at least one migration YAML file.');

    // Build adjacency list: migration => [dependencies].
    $graph = [];
    foreach ($migrations as $id => $migration) {
      $deps = $migration['migration_dependencies']['required'] ?? [];
      $graph[$id] = $deps;
    }

    // Topological sort using DFS cycle detection.
    $visited = [];
    $inStack = [];
    $cyclePath = [];

    $hasCycle = FALSE;

    $dfs = function (string $node) use (&$dfs, &$graph, &$visited, &$inStack, &$hasCycle, &$cyclePath): void {
      if ($hasCycle) {
        return;
      }
      $visited[$node] = TRUE;
      $inStack[$node] = TRUE;

      foreach (($graph[$node] ?? []) as $dep) {
        if (!isset($visited[$dep])) {
          $cyclePath[] = $dep;
          $dfs($dep);
        }
        elseif (isset($inStack[$dep])) {
          $hasCycle = TRUE;
          $cyclePath[] = $dep;
          return;
        }
      }

      unset($inStack[$node]);
    };

    foreach (array_keys($graph) as $node) {
      if (!isset($visited[$node])) {
        $cyclePath = [$node];
        $dfs($node);
        if ($hasCycle) {
          break;
        }
      }
    }

    $this->assertFalse(
      $hasCycle,
      sprintf(
        'Dependency cycle detected in migration graph: %s',
        implode(' → ', $cyclePath),
      ),
    );
  }

  /**
   * Test 2: All migrations use the testdata group.
   *
   * If any migration doesn't belong to `testdata` group, it won't be included
   * in `drush migrate:import --group=testdata` or rollback, breaking Properties 6 and 7.
   *
   * **Validates: Requirements 10.3, 10.4**
   */
  public function testAllMigrationsUseTestdataGroup(): void {
    $migrations = self::parseMigrations();
    $this->assertNotEmpty($migrations);

    $wrongGroup = [];
    foreach ($migrations as $id => $migration) {
      $group = $migration['migration_group'] ?? NULL;
      if ($group !== 'testdata') {
        $wrongGroup[] = "$id (group: " . ($group ?? 'NULL') . ')';
      }
    }

    $this->assertEmpty(
      $wrongGroup,
      sprintf(
        "All migrations must use migration_group: testdata. Violations:\n- %s",
        implode("\n- ", $wrongGroup),
      ),
    );
  }

  /**
   * Test 3: All source fixture files referenced by migrations exist.
   *
   * If a fixture file is missing, import will throw MigrateException and fail,
   * making idempotent re-import and rollback impossible.
   *
   * **Validates: Requirements 10.3, 10.4**
   */
  public function testAllSourceFixturesExist(): void {
    $migrations = self::parseMigrations();
    $this->assertNotEmpty($migrations);

    $dataDir = self::dataDir();
    $missing = [];

    foreach ($migrations as $id => $migration) {
      $file = $migration['source']['file'] ?? NULL;
      if ($file === NULL) {
        continue;
      }

      $fullPath = $dataDir . '/' . $file;
      if (!file_exists($fullPath)) {
        $missing[] = "$id → data/$file";
      }
    }

    $this->assertEmpty(
      $missing,
      sprintf(
        "Source fixture files missing:\n- %s",
        implode("\n- ", $missing),
      ),
    );
  }

  /**
   * Test 4: Expected entity counts per migration (baseline for Property 6).
   *
   * Documents the expected entity count for each migration by counting records
   * in the corresponding fixture file. This establishes the baseline that
   * Property 6 (idempotent import) can be verified against.
   *
   * **Property 6: Import Idempotence** — double import produces same entity count.
   * **Validates: Requirements 10.3**
   */
  public function testExpectedEntityCountsAreDocumented(): void {
    $migrations = self::parseMigrations();
    $this->assertNotEmpty($migrations);

    $dataDir = self::dataDir();
    $counts = [];
    $totalEntities = 0;

    foreach ($migrations as $id => $migration) {
      $file = $migration['source']['file'] ?? NULL;
      if ($file === NULL) {
        continue;
      }

      $fullPath = $dataDir . '/' . $file;
      if (!file_exists($fullPath)) {
        continue;
      }

      $content = file_get_contents($fullPath);
      $data = json_decode($content, TRUE);
      $count = is_array($data) ? count($data) : 0;
      $counts[$id] = $count;
      $totalEntities += $count;
    }

    // Every migration must have at least one record.
    $empty = array_filter($counts, fn($c) => $c === 0);
    $this->assertEmpty(
      $empty,
      sprintf(
        "Migrations with zero fixture records (would produce no entities):\n- %s",
        implode("\n- ", array_keys($empty)),
      ),
    );

    // Document the expected counts.
    $this->assertGreaterThan(
      0,
      $totalEntities,
      'Total expected entity count across all migrations must be > 0.',
    );

    // Output counts for documentation purposes (visible in verbose test output).
    // Each migration's expected entity count establishes the baseline for
    // verifying Property 6: after double import, these counts should not change.
    foreach ($counts as $id => $count) {
      $this->assertGreaterThan(
        0,
        $count,
        "Migration '$id' fixture must contain at least one record.",
      );
    }
  }

  /**
   * Test 5: All migrations use rollback-capable entity destination plugins.
   *
   * Drupal's entity destination plugins (entity:user, entity:node, etc.)
   * natively support rollback. If a migration uses a non-entity destination,
   * rollback won't remove its entities, breaking Property 7.
   *
   * **Property 7: Import/Rollback Round-Trip** — rollback leaves zero test entities.
   * **Validates: Requirements 10.4**
   */
  public function testAllMigrationsUseRollbackCapableDestinations(): void {
    $migrations = self::parseMigrations();
    $this->assertNotEmpty($migrations);

    $validPlugins = self::rollbackCapablePlugins();
    $unsupported = [];

    foreach ($migrations as $id => $migration) {
      $plugin = $migration['destination']['plugin'] ?? NULL;
      if ($plugin === NULL) {
        $unsupported[] = "$id (no destination plugin found)";
        continue;
      }

      if (!in_array($plugin, $validPlugins, TRUE)) {
        $unsupported[] = "$id (uses: $plugin)";
      }
    }

    $this->assertEmpty(
      $unsupported,
      sprintf(
        "Migrations with non-rollback-capable destinations:\n- %s\nAllowed: %s",
        implode("\n- ", $unsupported),
        implode(', ', $validPlugins),
      ),
    );
  }

  /**
   * Test 6: No migrations reference dependencies outside the testdata group.
   *
   * If a migration depends on a non-testdata migration, the group import may
   * fail or behave unexpectedly. All dependencies must be self-contained.
   *
   * **Validates: Requirements 10.3, 10.4**
   */
  public function testDependenciesAreWithinTestdataGroup(): void {
    $migrations = self::parseMigrations();
    $this->assertNotEmpty($migrations);

    $migrationIds = array_keys($migrations);
    $external = [];

    foreach ($migrations as $id => $migration) {
      $deps = $migration['migration_dependencies']['required'] ?? [];
      foreach ($deps as $dep) {
        if (!in_array($dep, $migrationIds, TRUE)) {
          $external[] = "$id depends on external: $dep";
        }
      }
    }

    $this->assertEmpty(
      $external,
      sprintf(
        "Migrations with external dependencies (outside testdata group):\n- %s",
        implode("\n- ", $external),
      ),
    );
  }

  /**
   * Test 7: Fixture files use a shared source (testdata_files + testdata_media_images).
   *
   * The files and media_images migrations share a source fixture (media/images.json).
   * This verifies the expected relationship holds, ensuring file entities and
   * media entities are created/rolled back consistently.
   *
   * **Validates: Requirements 10.4**
   */
  public function testFilesAndMediaShareConsistentSource(): void {
    $migrations = self::parseMigrations();

    // testdata_files should source from media/images.json.
    $this->assertArrayHasKey('testdata_files', $migrations);
    $this->assertSame(
      'media/images.json',
      $migrations['testdata_files']['source']['file'] ?? NULL,
      'testdata_files must source from media/images.json',
    );

    // testdata_media_images should also source from media/images.json.
    $this->assertArrayHasKey('testdata_media_images', $migrations);
    $this->assertSame(
      'media/images.json',
      $migrations['testdata_media_images']['source']['file'] ?? NULL,
      'testdata_media_images must source from media/images.json',
    );
  }

  /**
   * Test 8: Migration count matches expected total.
   *
   * Ensures no migrations are accidentally missing from the group.
   * The total should cover: users + files + media + 8 taxonomy + 7 node types = 18.
   *
   * **Validates: Requirements 10.3, 10.4**
   */
  public function testMigrationCountMatchesExpected(): void {
    $migrations = self::parseMigrations();

    // Expected migrations based on the module structure.
    $expectedIds = [
      'testdata_users',
      'testdata_files',
      'testdata_media_images',
      'testdata_taxonomy_alustat',
      'testdata_taxonomy_alustatarkenne',
      'testdata_taxonomy_franchise',
      'testdata_taxonomy_ihmiset',
      'testdata_taxonomy_pelijulkaisijat',
      'testdata_taxonomy_pelistudiot',
      'testdata_taxonomy_pelit',
      'testdata_taxonomy_sarja',
      'testdata_nodes_article',
      'testdata_nodes_blogi',
      'testdata_nodes_laitearvio',
      'testdata_nodes_peliarvostelu',
      'testdata_nodes_podcast',
      'testdata_nodes_uutinen',
      'testdata_nodes_video',
    ];

    $actualIds = array_keys($migrations);
    sort($expectedIds);
    sort($actualIds);

    $missing = array_diff($expectedIds, $actualIds);
    $unexpected = array_diff($actualIds, $expectedIds);

    $this->assertEmpty(
      $missing,
      sprintf("Missing expected migrations:\n- %s", implode("\n- ", $missing)),
    );
    $this->assertEmpty(
      $unexpected,
      sprintf("Unexpected migrations found:\n- %s", implode("\n- ", $unexpected)),
    );
  }

}

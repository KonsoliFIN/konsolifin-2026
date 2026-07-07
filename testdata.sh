#!/usr/bin/env bash
set -euo pipefail

BATCH_SIZE=100
PAUSE=3

# Runs a migration in batches until all items are imported.
# Uses drush migrate:status to check remaining items.
run_migration() {
  local migration="$1"
  echo ""
  echo "=== Starting: $migration ==="

  while true; do
    # Get the count of unprocessed items.
    local status_output
    status_output=$(docker exec konsolifin_web ./vendor/bin/drush migrate:status "$migration" --format=json 2>/dev/null || echo "[]")
    local unprocessed
    unprocessed=$(echo "$status_output" | php -r '
      $data = json_decode(file_get_contents("php://stdin"), true);
      if (is_array($data) && count($data) > 0) {
        echo (int)($data[0]["unprocessed"] ?? 0);
      } else {
        echo "0";
      }
    ')

    if [ "$unprocessed" -le 0 ] 2>/dev/null; then
      echo "  ✓ $migration complete (no remaining items)"
      break
    fi

    echo "  → $unprocessed remaining, importing batch of $BATCH_SIZE..."
    docker exec konsolifin_web ./vendor/bin/drush migrate:import --limit="$BATCH_SIZE" "$migration" || true
    sleep "$PAUSE"
  done
}

echo "Loading test data into system..."
echo "Batch size: $BATCH_SIZE | Pause between batches: ${PAUSE}s"
echo ""

# Users
run_migration testdata_users

# Files and media
run_migration testdata_files
run_migration testdata_media_images
run_migration testdata_media_audio

# Taxonomies
run_migration testdata_taxonomy_alustat
run_migration testdata_taxonomy_alustatarkenne
run_migration testdata_taxonomy_ihmiset
run_migration testdata_taxonomy_pelijulkaisijat
run_migration testdata_taxonomy_pelistudiot
run_migration testdata_taxonomy_sarja
run_migration testdata_taxonomy_franchise
run_migration testdata_taxonomy_pelit

# Nodes
run_migration testdata_nodes_uutinen
run_migration testdata_nodes_peliarvostelu
run_migration testdata_nodes_blogi
run_migration testdata_nodes_article
run_migration testdata_nodes_laitearvio
run_migration testdata_nodes_julkaisu
run_migration testdata_nodes_podcast

echo ""
echo "=== Import complete ==="
docker exec konsolifin_web ./vendor/bin/drush migrate:status --group=testdata

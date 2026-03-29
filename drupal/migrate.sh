#!/usr/bin/env bash
set -euo pipefail

BATCH_SIZE=500
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
    status_output=$(drush migrate:status "$migration" --format=json 2>/dev/null || echo "[]")
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
    drush migrate:import --limit="$BATCH_SIZE" "$migration" || true
    sleep "$PAUSE"
  done
}

echo "Starting full KonsoliFIN migration..."
echo "Batch size: $BATCH_SIZE | Pause between batches: ${PAUSE}s"
echo ""

# Users
run_migration konsolifin_user_roles
run_migration konsolifin_users

# Files and media
run_migration konsolifin_files
run_migration konsolifin_media_images
run_migration konsolifin_media_audio
run_migration konsolifin_media_video

# Taxonomies
run_migration konsolifin_taxonomy_alustat
run_migration konsolifin_taxonomy_alustatarkenne
run_migration konsolifin_taxonomy_ihmiset
run_migration konsolifin_taxonomy_pelijulkaisijat
run_migration konsolifin_taxonomy_pelistudiot
run_migration konsolifin_taxonomy_sarja
run_migration konsolifin_taxonomy_pelit

# Nodes
run_migration konsolifin_nodes_uutinen
run_migration konsolifin_nodes_peliarvostelu
run_migration konsolifin_nodes_blog
run_migration konsolifin_nodes_media_arvostelu
run_migration konsolifin_nodes_article
run_migration konsolifin_nodes_laitearvio
run_migration konsolifin_nodes_julkaisu
run_migration konsolifin_nodes_video
run_migration konsolifin_nodes_vierailija_arvostelu
run_migration konsolifin_nodes_page

# Comments
run_migration konsolifin_comments

# URL aliases
run_migration konsolifin_url_alias

echo ""
echo "=== Migration complete ==="
drush migrate:status --group=migrate_konsolifin

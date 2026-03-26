# Migrating users
read -p "Press Enter to migrate user roles and users" </dev/tty
drush migrate:import konsolifin_user_roles
drush migrate:import konsolifin_users
drush migrate:status --group=migrate_konsolifin  

# Migrating files and media entities
read -p "Press Enter to migrate files" </dev/tty
drush migrate:import konsolifin_files
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate image media" </dev/tty
drush migrate:import konsolifin_media_images
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate audio media" </dev/tty
drush migrate:import konsolifin_media_audio
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate video media" </dev/tty
drush migrate:import konsolifin_media_video
drush migrate:status --group=migrate_konsolifin  


# Migrating vocabularies
read -p "Press Enter to migrate platform terms" </dev/tty
drush migrate:import konsolifin_taxonomy_alustat
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate platform specifier terms" </dev/tty
drush migrate:import konsolifin_taxonomy_alustatarkenne
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate people terms" </dev/tty
drush migrate:import konsolifin_taxonomy_ihmiset
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate publisher terms" </dev/tty
drush migrate:import konsolifin_taxonomy_pelijulkaisijat
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate studio terms" </dev/tty
drush migrate:import konsolifin_taxonomy_pelistudiot
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate series terms" </dev/tty
drush migrate:import konsolifin_taxonomy_sarja
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate games terms" </dev/tty
drush migrate:import konsolifin_taxonomy_pelit
drush migrate:status --group=migrate_konsolifin  

# Migrating nodes
echo "Migrate news nodes, how many rounds? (1000 nodes each)"
read rounds
for (( i=1; i<=rounds; i++ ))
do
    drush migrate:import --limit=1000 konsolifin_nodes_uutinen
    sleep 3
done
drush migrate:status --group=migrate_konsolifin  

echo "Migrate games review nodes, how many rounds? (1000 nodes each)"
read rounds
for (( i=1; i<=rounds; i++ ))
do
    drush migrate:import --limit=1000 konsolifin_nodes_peliarvostelu
    sleep 3
done
drush migrate:status --group=migrate_konsolifin  

echo "Migrate blog nodes, how many rounds? (1000 nodes each)"
read rounds
for (( i=1; i<=rounds; i++ ))
do
    drush migrate:import --limit=1000 konsolifin_nodes_blog
    sleep 3
done
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate media review nodes" </dev/tty
drush migrate:import konsolifin_nodes_media_arvostelu
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate article nodes" </dev/tty
drush migrate:import konsolifin_nodes_artikkeli
drush migrate:status --group=migrate_konsolifin  

read -p "Press Enter to migrate hardware review nodes" </dev/tty
drush migrate:import konsolifin_nodes_laitearvio
drush migrate:status --group=migrate_konsolifin  

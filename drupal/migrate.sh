# Migrating users
drush migrate:import konsolifin_user_roles
read -p "Press Enter to continue" </dev/tty

drush migrate:import konsolifin_users
read -p "Press Enter to continue" </dev/tty

# Migrating files and media entities
drush migrate:import konsolifin_files
read -p "Press Enter to continue" </dev/tty

drush migrate:import konsolifin_media_images
read -p "Press Enter to continue" </dev/tty

drush migrate:import konsolifin_media_audio
read -p "Press Enter to continue" </dev/tty

drush migrate:import konsolifin_media_video
read -p "Press Enter to continue" </dev/tty

# Migrating vocabularies
drush migrate:import konsolifin_taxonomy_alustat
read -p "Press Enter to continue" </dev/tty

drush migrate:import konsolifin_taxonomy_alustatarkenne
read -p "Press Enter to continue" </dev/tty

drush migrate:import konsolifin_taxonomy_ihmiset
read -p "Press Enter to continue" </dev/tty

drush migrate:import konsolifin_taxonomy_pelijulkaisijat
read -p "Press Enter to continue" </dev/tty

drush migrate:import konsolifin_taxonomy_pelistudiot
read -p "Press Enter to continue" </dev/tty

drush migrate:import konsolifin_taxonomy_sarja
read -p "Press Enter to continue" </dev/tty

drush migrate:import konsolifin_taxonomy_pelit
read -p "Press Enter to continue" </dev/tty

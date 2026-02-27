cd drupal
drush state:set system.maintenance_mode 1 --input-format=integer
drush cache:rebuild
composer install --no-dev
drush cset -y system.site uuid "55068876-fcca-43bb-b0ea-0b5929f25973"
drush config:import -y --source="../config/sync"
drush updatedb
drush state:set system.maintenance_mode 0 --input-format=integer
drush cache:rebuild

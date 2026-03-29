setup: start build install
# After installing, there are some annoying shortcuts that would
# block importing configuration. The shortcuts must die before doing
# anything else.
	docker exec konsolifin_web ./vendor/bin/drush php:eval '\Drupal::entityTypeManager()->getStorage("shortcut_set")->load("default")->delete();'

# Since this is probably the first time importing the config,
# some imports fail, so the importing is performed twice. After
# the second round everything should be completely fine.
	docker exec konsolifin_web ./vendor/bin/drush config:set -y system.site uuid "55068876-fcca-43bb-b0ea-0b5929f25973"
	-docker exec konsolifin_web ./vendor/bin/drush config:import -y --source="../config/sync"
	docker exec konsolifin_web ./vendor/bin/drush config:import -y --source="../config/sync"

build:
	docker exec konsolifin_web composer install

install:
	docker exec konsolifin_web ./vendor/bin/drush site:install \
		--db-url=mysql://user:password@db:3306/drupal \
		--account-name=admin --account-pass=admin --account-mail=toimitus@konsolifin.net \
		--site-name="Kehitysympäristö" --config-dir=../config/sync --existing-config

clean:
	docker exec konsolifin_web ./vendor/bin/drush cache:rebuild

pack:
	docker exec konsolifin_web ./vendor/bin/drush config:export --destination="../config/sync"

unpack:
	docker exec konsolifin_web ./vendor/bin/drush config:set -y system.site uuid "55068876-fcca-43bb-b0ea-0b5929f25973"
	docker exec konsolifin_web ./vendor/bin/drush config:import -y --source="../config/sync"

start:
	docker-compose up -d

stop:
	docker-compose stop

update:
	docker exec konsolifin_web ./vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer
	docker exec konsolifin_web ./vendor/bin/drush cache:rebuild
	git pull
	docker exec konsolifin_web composer update
	docker exec konsolifin_web ./vendor/bin/drush updatedb
	docker exec konsolifin_web ./vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer
	docker exec konsolifin_web ./vendor/bin/drush cache:rebuild

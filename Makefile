SHELL := /bin/bash

TEST_PLUGIN_NAME ?= trackmage-woo-shipment-tracking
TRAVIS_WP_FOLDER ?= wordpress
BUILD_FOLDER ?= build
BUILD_PLUGIN_FOLDER ?= ${BUILD_FOLDER}/${TEST_PLUGIN_NAME}
TRAVIS_WP_URL ?= http://wp.test
TRAVIS_WP_DOMAIN ?= wp.test
TRAVIS_DB_NAME ?= wp_site
TRAVIS_TEST_DB_NAME ?= test
TRAVIS_WP_TABLE_PREFIX ?= wp_
TRAVIS_WP_ADMIN_USERNAME ?= admin
TRAVIS_WP_ADMIN_PASSWORD ?= password
COMPOSE_FILE ?= docker-compose.yml
WORDPRESS_VERSION ?= 5.5.3
WOOCOMMERCE_VERSION ?= 4.3.0
AIRPLANE_MODE_VERSION ?= 0.2.4
PHP_VERSION ?= 7.2
PROJECT := $(shell basename ${CURDIR})

ifneq (${TRAVIS_PULL_REQUEST_BRANCH},)
  BRANCH := ${TRAVIS_PULL_REQUEST_BRANCH}
else ifneq ($(TRAVIS_BRANCH),)
  BRANCH := ${TRAVIS_BRANCH}
else
  BRANCH := $(shell git rev-parse --abbrev-ref HEAD)
endif
BRANCH_SLUG = $(shell echo $(BRANCH) | sed -e s@/@-@g )
PLUGIN_NAME_WITH_BRANCH := ${TEST_PLUGIN_NAME}-${BRANCH_SLUG}


.PHONY: wp_dump \
	build  \
	comment  \
	ci_before_install  \
	ci_before_script \
	ci_docker_restart \
	ci_install  \
	ci_local_prepare \
	ci_run  \
	ci_script

# PUll all the Docker images this repository will use in building images or running processes.
docker_pull:
	images=( \
		'wordpress:cli-php${PHP_VERSION}' \
		'martin/wait' \
		'selenium/standalone-chrome' \
		'mariadb:latest' \
		'wordpress:php${PHP_VERSION}-apache' \
	); \
	for image in "$${images[@]}"; do \
		docker pull "$$image"; \
	done;

# Runs phpstan on the source files.
phpstan: src
	docker run --rm -v ${CURDIR}:/app phpstan/phpstan analyse -l 5 /app/src


ci_setup_db:
	# Start just the database container and wait until its initialized.
	docker-compose -f docker/${COMPOSE_FILE} up -d db
	docker-compose -f docker/${COMPOSE_FILE} run --rm waiter
	# Create the databases that will be used in the tests.
	docker-compose -f docker/${COMPOSE_FILE} exec db bash -c 'mysql -u root -e "create database if not exists wp_test"'

ci_setup_wp:
	# Download wordpress
	if [ ! -d ${TRAVIS_WP_FOLDER} ]; then \
		wget -q -O wp.zip https://wordpress.org/wordpress-${WORDPRESS_VERSION}.zip && \
		unzip -qq wp.zip && rm wp.zip; \
	fi
	cp -a docker/wordpress/. wordpress/
#	Make sure the WordPress folder is write-able.
	sudo chmod -R 0777 wordpress

ci_before_install: ci_setup_db ci_setup_wp
	# Start the WordPress container.
	docker-compose -f docker/${COMPOSE_FILE} up -d wp
	# Fetch the IP address of the WordPress container in the containers network.
	# Start the Chromedriver container using that information to have the *.wp.test domain bound to the WP container.
	WP_CONTAINER_IP=`docker inspect -f '{{ .NetworkSettings.Networks.docker_default.IPAddress }}' wpbrowser_wp` \
	docker-compose -f docker/${COMPOSE_FILE} up -d chromedriver

build:
	rm -rf ${BUILD_FOLDER} || true
	mkdir -p ${BUILD_PLUGIN_FOLDER}
	rsync -a --include-from=.rsync --exclude="*" . ${BUILD_PLUGIN_FOLDER}
	(cd ${BUILD_PLUGIN_FOLDER} && COMPOSER_MEMORY_LIMIT=-1 composer update --no-dev --prefer-dist \
		&& npm ci && npm run build && rm -rf node_modules/)
	if [ "${ZIP_BUILD}" = true ]; then \
		(cd ${BUILD_FOLDER} && rm -rf ${TEST_PLUGIN_NAME}.zip && zip -qq -r ${TEST_PLUGIN_NAME}.zip ${TEST_PLUGIN_NAME}); \
		(cd ${BUILD_FOLDER}; mv ${TEST_PLUGIN_NAME} ${PLUGIN_NAME_WITH_BRANCH}; \
			rm -rf ${PLUGIN_NAME_WITH_BRANCH}.zip && zip -qq -r ${PLUGIN_NAME_WITH_BRANCH}.zip ${PLUGIN_NAME_WITH_BRANCH}; \
			mv ${PLUGIN_NAME_WITH_BRANCH} ${TEST_PLUGIN_NAME}); \
	fi

ci_install: build
ci_install:
	# install dev deps
	COMPOSER_MEMORY_LIMIT=-1 composer update

	# Initialize WordPress DB
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli-php${PHP_VERSION} wp core install \
		--url=${TRAVIS_WP_URL} \
		--title=Test \
		--admin_user=${TRAVIS_WP_ADMIN_USERNAME} \
		--admin_password=${TRAVIS_WP_ADMIN_PASSWORD} \
		--admin_email=admin@${TRAVIS_WP_DOMAIN} \
		--skip-email
	# Empty the main site of all content.
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli-php${PHP_VERSION} wp site empty --yes

	# Install the Airplane Mode plugin to speed up the Driver tests.
	if [ ! -d ${TRAVIS_WP_FOLDER}/wp-content/plugins/airplane-mode ]; then \
		mkdir -p ${TRAVIS_WP_FOLDER}/wp-content/plugins/airplane-mode && \
		(cd ${TRAVIS_WP_FOLDER}/wp-content/plugins/airplane-mode && \
			wget -q -O - https://github.com/norcross/airplane-mode/archive/${AIRPLANE_MODE_VERSION}.tar.gz \
			| tar xz --strip-components=1); \
	fi
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli-php${PHP_VERSION} wp plugin activate airplane-mode

	# Install Woocommerce plugin
	if [ ! -d ${TRAVIS_WP_FOLDER}/wp-content/plugins/woocommerce ]; then \
		wget -q -O wc.zip https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip && \
		unzip -qq wc.zip -d ${TRAVIS_WP_FOLDER}/wp-content/plugins && rm wc.zip; \
	fi
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli-php${PHP_VERSION} wp plugin activate woocommerce

	# setup the plugin
	rm -rf ${TRAVIS_WP_FOLDER}/wp-content/plugins/${TEST_PLUGIN_NAME} || true
	cp -r ${BUILD_PLUGIN_FOLDER} ${TRAVIS_WP_FOLDER}/wp-content/plugins/${TEST_PLUGIN_NAME}/
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli-php${PHP_VERSION} wp plugin activate ${TEST_PLUGIN_NAME}

	# Make sure everyone can write to the tests/_data folder.
	sudo chmod -R 777 tests/_data
	# Export a dump of the just installed database to the _data folder of the project.
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli-php${PHP_VERSION} wp db export \
		/project/tests/_data/dump.sql

ci_before_script:
	# Sync the plugin source code
	rsync -a --include-from=.rsync --exclude="*" . ${TRAVIS_WP_FOLDER}/wp-content/plugins/${TEST_PLUGIN_NAME}
	# Build Codeception modules.
	vendor/bin/codecept build

ci_script: export BUILD_FLAVOR = PHP${PHP_VERSION}WP${WORDPRESS_VERSION}WC${WOOCOMMERCE_VERSION}
ci_script:
	vendor/bin/codecept run unit
	vendor/bin/codecept run wpunit
#	vendor/bin/codecept run functional
	if [ "${ZIP_BUILD}" = true ]; then \
		make comment; \
	fi

# Restarts the project containers.
ci_docker_restart:
	docker-compose -f docker/${COMPOSE_FILE} restart

# Make sure the host machine can ping the WordPress container
ensure_pingable_hosts:
	set -o allexport &&  source .env.testing &&  set +o allexport && \
	echo $${TEST_HOSTS} | \
	sed -e $$'s/ /\\\n/g' | while read host; do echo "\nPinging $${host}" && ping -c 1 "$${host}"; done

ci_prepare: ci_before_install ensure_pingable_hosts ci_install ci_before_script

ci_local_prepare: ci_before_install ensure_pingable_hosts ci_install ci_before_script

ci_run: ci_prepare ci_script

# Gracefully stop the Docker containers used in the tests.
docker_down:
	# Shutdown all working containers
	docker-compose -f docker/docker-compose.yml down

down: docker_down
down:
	# Remove wordpress installation
	rm -rf wordpress/
	# Cleanup codeception
	vendor/bin/codecept clean

remove_hosts_entries:
	# backup hosts first
	sudo cp /etc/hosts /etc/hosts.$(date +%F_%R)
	echo "Removing project ${PROJECT} hosts entries..."
	sudo sed -i '/^## ${PROJECT} project - start ##/,/## ${PROJECT} project - end ##$$/d' /etc/hosts

sync_hosts_entries: remove_hosts_entries
	echo "Adding project ${project} hosts entries..."
	set -o allexport &&  source .env.testing &&  set +o allexport && \
	sudo -- sh -c "echo '' >> /etc/hosts" && \
	sudo -- sh -c "echo '## ${PROJECT} project - start ##' >> /etc/hosts" && \
	sudo -- sh -c "echo '127.0.0.1 $${TEST_HOSTS}' >> /etc/hosts" && \
	sudo -- sh -c "echo '## ${PROJECT} project - end ##' >> /etc/hosts"

# Export a dump of WordPress database to the _data folder of the project.
wp_dump:
	docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli-php${PHP_VERSION} wp db export \
		/project/tests/_data/dump.sql

comment: export BUILD_URL = https://travis-uploaded-artifacts.s3-us-west-2.amazonaws.com/${TRAVIS_REPO_SLUG}/${TRAVIS_PULL_REQUEST_BRANCH}/build/${PLUGIN_NAME_WITH_BRANCH}.zip
comment: export COMMENT = Download build ${BUILD_URL}
comment:
	echo "COMMENT: ${COMMENT} TRAVIS_PULL_REQUEST: ${TRAVIS_PULL_REQUEST}"
	if [ "${TRAVIS_PULL_REQUEST}" != false ] ; then \
		curl -H "Authorization: token ${GITHUB_TOKEN}" -X POST -d "{\"body\": \"${COMMENT}\"}" "https://api.github.com/repos/${TRAVIS_REPO_SLUG}/issues/${TRAVIS_PULL_REQUEST}/comments"; \
	fi

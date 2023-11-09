SHELL := /bin/bash

PHP_VERSION ?= 7.4
WORDPRESS_VERSION ?= 5.3.0
WOOCOMMERCE_VERSION ?= 4.5.0
ZIP_BUILD ?= false
GIT_BRANCH ?= master
TEST_PLUGIN_NAME := trackmage-woo-shipment-tracking
BUILD_FOLDER := build
BUILD_PLUGIN_FOLDER := ${BUILD_FOLDER}/${TEST_PLUGIN_NAME}
BRANCH_SLUG = $(shell echo $(GIT_BRANCH) | sed -e s@/@-@g )
PLUGIN_NAME_WITH_BRANCH := ${TEST_PLUGIN_NAME}-${BRANCH_SLUG}
WP_FOLDER := /var/www/html
REPO_SLUG := trackmage/trackmage-woo-shipment-tracking

.PHONY: \
	build \
	init \
	info \
	test \
	comment

info:
	echo ZIP_BUILD ${ZIP_BUILD}
	echo WOOCOMMERCE_VERSION ${WOOCOMMERCE_VERSION}
	echo WORDPRESS_VERSION ${WORDPRESS_VERSION}
	echo PHP_VERSION ${PHP_VERSION}
	echo GIT_BRANCH ${GIT_BRANCH}

build:
	rm -rf ${BUILD_FOLDER} || true
	mkdir -p ${BUILD_PLUGIN_FOLDER}
	rsync -a --include-from=.rsync --exclude="*" . ${BUILD_PLUGIN_FOLDER}
	(cd ${BUILD_PLUGIN_FOLDER} && COMPOSER_MEMORY_LIMIT=-1 composer update --no-dev --prefer-dist)
	if [ "${ZIP_BUILD}" = true ]; then \
		(cd ${BUILD_FOLDER} && rm -rf ${TEST_PLUGIN_NAME}.zip && zip -qq -r ${TEST_PLUGIN_NAME}.zip ${TEST_PLUGIN_NAME}); \
		(cd ${BUILD_FOLDER}; mv ${TEST_PLUGIN_NAME} ${PLUGIN_NAME_WITH_BRANCH}; \
			rm -rf ${PLUGIN_NAME_WITH_BRANCH}.zip && zip -qq -r ${PLUGIN_NAME_WITH_BRANCH}.zip ${PLUGIN_NAME_WITH_BRANCH}; \
			mv ${PLUGIN_NAME_WITH_BRANCH} ${TEST_PLUGIN_NAME}); \
	fi

init: build
init: export AIRPLANE_MODE_VERSION := 0.2.5
init: export WP_ADMIN_USERNAME ?= admin
init: export WP_ADMIN_PASSWORD ?= password
init: export WP_DOMAIN ?= wp.test
init: export WP_URL ?= http://wp.test
init:
	# install dev deps
	COMPOSER_MEMORY_LIMIT=-1 composer update
	cp -a docker/wordpress/. ${WP_FOLDER}/
	wp core install --allow-root --path=/var/www/html \
		--url=${WP_URL} \
		--title=Test \
		--admin_user=${WP_ADMIN_USERNAME} \
		--admin_password=${WP_ADMIN_PASSWORD} \
		--admin_email=admin@${WP_DOMAIN} \
		--skip-email --allow-root
	# Empty the main site of all content.
	wp site empty --yes --allow-root --path=/var/www/html
	wp core update --allow-root --path=${WP_FOLDER} \
	    --version=${WORDPRESS_VERSION} \
	    --force

	# Install the Airplane Mode plugin to speed up the Driver tests.
	if [ ! -d ${WP_FOLDER}/wp-content/plugins/airplane-mode ]; then \
		mkdir -p ${WP_FOLDER}/wp-content/plugins/airplane-mode && \
		(cd ${WP_FOLDER}/wp-content/plugins/airplane-mode && \
			wget -q -O - https://github.com/norcross/airplane-mode/archive/${AIRPLANE_MODE_VERSION}.tar.gz \
			| tar xz --strip-components=1); \
	fi
	wp plugin activate airplane-mode --allow-root --path=/var/www/html

	# Install Woocommerce plugin
	if [ ! -d ${WP_FOLDER}/wp-content/plugins/woocommerce ]; then \
		wget -q -O wc.zip https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip && \
		unzip -qq wc.zip -d ${WP_FOLDER}/wp-content/plugins && rm wc.zip; \
	fi
	wp plugin activate woocommerce --allow-root --path=/var/www/html

	# setup the plugin
	rm -rf ${WP_FOLDER}/wp-content/plugins/${TEST_PLUGIN_NAME} || true
	cp -r ${BUILD_PLUGIN_FOLDER} ${WP_FOLDER}/wp-content/plugins/${TEST_PLUGIN_NAME}/
	wp plugin activate ${TEST_PLUGIN_NAME} --allow-root --path=/var/www/html

	# Make sure everyone can write to the tests/_data folder.
	# sudo chmod -R 777 tests/_data
	# Export a dump of the just installed database to the _data folder of the project.
	wp db export /project/tests/_data/dump.sql --allow-root --path=/var/www/html

test: export BUILD_FLAVOR := PHP${PHP_VERSION}WP${WORDPRESS_VERSION}WC${WOOCOMMERCE_VERSION}
test:
	# Sync the plugin source code
	rsync -a --include-from=.rsync --exclude="*" . ${WP_FOLDER}/wp-content/plugins/${TEST_PLUGIN_NAME}
	# Build Codeception modules.
	vendor/bin/codecept build
	vendor/bin/codecept run unit
	vendor/bin/codecept run wpunit
#	vendor/bin/codecept run functional
	if [ "${ZIP_BUILD}" = true ]; then \
		make comment; \
	fi

comment: export COMMENT := Download build ${CI_JOB_URL}/artifacts/browse
comment:
	curl -sS -H "Authorization: token ${GITHUB_TOKEN}" "https://api.github.com/repos/${REPO_SLUG}/pulls?head=trackmage:${GIT_BRANCH}&per_page=1"
	set -e ; \
	PR_URL=$$(curl -sS -H "Authorization: token ${GITHUB_TOKEN}" "https://api.github.com/repos/${REPO_SLUG}/pulls?head=trackmage:${GIT_BRANCH}&per_page=1"|jq -r '.[].comments_url'); \
	echo "COMMENT: ${COMMENT} PR_URL: $$PR_URL"; \
	if [ $$PR_URL != "" ] ; then \
        curl -sS -H "Authorization: token ${GITHUB_TOKEN}" -X POST -d '{"body": "${COMMENT}"}' $$PR_URL; \
	fi

#!/usr/bin/env bash
set -euxo pipefail

echo 'APT::Acquire::Retries "3";' > /etc/apt/apt.conf.d/80-retries
export DEBIAN_FRONTEND=noninteractive
apt-get -y update
apt-get install -y jq libicu-dev mariadb-client rsync zip unzip wget

docker-php-ext-configure intl && docker-php-ext-install intl

# Ensure MySQL PDO and mysqli drivers are available for Codeception and
# WordPress. The upstream wordpress:php*-fpm images install mysqli but not
# pdo_mysql, which Codeception's Db module requires. Guard with a check so
# a re-run (or an image that already has the extension) does not error.
for ext in pdo_mysql mysqli; do
    if ! php -m | grep -qi "^${ext}$"; then
        docker-php-ext-install "$ext"
    fi
done

php -m

curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
composer --version

curl -L -o wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x ./wp && mv ./wp /usr/local/bin/
wp --info

# Skip theme loading in every subsequent wp-cli invocation. The docker
# image ships themes from its WP baseline (e.g. wordpress:php8.2-fpm
# carries twentytwentyfive from WP 6.9, which calls
# register_block_bindings_source - a WP 6.5+ API). After "wp core update
# --version=X" downgrades core to an older WORDPRESS_VERSION the active
# theme'\''s functions.php explodes on every wp init hook. Tests do not
# use the theme, so skip-themes is the cleanest fix for all legacy jobs.
mkdir -p /root/.wp-cli
printf 'skip-themes: true\n' > /root/.wp-cli/config.yml

chown -R www-data:www-data /var/www

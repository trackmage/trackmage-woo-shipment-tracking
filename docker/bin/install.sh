#!/usr/bin/env bash

echo 'APT::Acquire::Retries "3";' > /etc/apt/apt.conf.d/80-retries
apt-get -y update && apt-get install -y jq libicu-dev mariadb-client rsync zip unzip wget

docker-php-ext-configure intl && docker-php-ext-install intl
php -m

curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
composer --version

curl -L -o wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x ./wp && mv ./wp /usr/local/bin/
wp --info

chown -R www-data:www-data /var/www

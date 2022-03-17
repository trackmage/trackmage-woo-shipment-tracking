#!/usr/bin/env bash

echo 'APT::Acquire::Retries "3";' > /etc/apt/apt.conf.d/80-retries
apt-get -y update && apt-get install -y jq libicu-dev mariadb-client rsync zip unzip wget

docker-php-ext-configure intl && docker-php-ext-install intl
php -m

curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --version=1.10.24
composer --version
composer global require hirak/prestissimo

if [[ $PHP_VERSION = "5.6" ]]; then
    WPCLI_INSTALL_URL=https://github.com/wp-cli/wp-cli/releases/download/v2.5.0/wp-cli-2.5.0.phar
else
    WPCLI_INSTALL_URL=https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi
curl -L -o wp ${WPCLI_INSTALL_URL} && chmod +x ./wp && mv ./wp /usr/local/bin/
wp --info

chown -R www-data:www-data /var/www

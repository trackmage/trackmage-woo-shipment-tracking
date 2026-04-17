#!/usr/bin/env bash

echo 'APT::Acquire::Retries "3";' > /etc/apt/apt.conf.d/80-retries
apt-get -y update && apt-get install -y jq libicu-dev mariadb-client rsync zip unzip wget

# Newer mariadb-client packages default to requiring TLS to the server, but
# the pinned mariadb:10.3.6 service does not advertise SSL. Disable SSL
# globally for every client invocation (wp-cli, mysql, mysqldump) so the
# test bootstrap can talk to the DB.
mkdir -p /etc/mysql/conf.d
printf '[client]\nssl=0\n\n[mysql]\nssl=0\n\n[mysqldump]\nssl=0\n' > /etc/mysql/conf.d/disable-ssl.cnf

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

chown -R www-data:www-data /var/www

[![Build Status](https://travis-ci.org/trackmage/trackmage-woo-shipment-tracking.svg?branch=master)](https://travis-ci.org/trackmage/trackmage-woo-shipment-tracking)

# trackmage-woo-shipment-tracking
TrackMage integrates shipments tracking into your WooCommerce store.

[Download](https://travis-uploaded-artifacts.s3-us-west-2.amazonaws.com/trackmage/trackmage-woo-shipment-tracking/master/build/trackmage-woo-shipment-tracking.zip)

## Local development
```
composer install
npm install
npm run build
```
In case of `npm run build` errors run
```
npm install -g gulp-cli
npm rebuild node-sass
```

Add this to wp-config.php to change the api domain:
```
define('TRACKMAGE_API_DOMAIN', 'https://api.test.trackmage.com');
define('TRACKMAGE_APP_DOMAIN', 'https://app.test.trackmage.com');
```

## Testing:

Local commands:
```
export PHP_VERSION=7.2
make sync_hosts_entries
make ci_local_prepare
make ci_before_script
make ci_script
vendor/bin/codecept run wpunit,unit,functional,acceptance
XDEBUG_CONFIG="idekey=PhpStorm1" vendor/bin/codecept run wpunit tests/wpunit/Synchronization/OrderSyncTest.php 
make docker_down
make down
```

Deployment on stage server
```
make build
export STAGE_SSH_PASS=<password>
make deploy
```

Docs:
```
https://codeception.com/for/wordpress
https://wpbrowser.wptestkit.dev/summary/levels-of-testing
```

<a href="https://s.trackmage.com/ef2424">
  <div align="center">
    <img src="https://user-images.githubusercontent.com/1675033/84406764-a7091300-ac12-11ea-8250-774a8f0697fe.jpg" width='128'/>
  </div>
</a>

<h1 align="center">Trackmage - Get Branded Tracking Page for your ecommerce store. WooCommerce shipment tracking plugin for WordPress</h1>

[![Build Status](https://gitlab.dev.trackmage.com/karser/trackmage-woo-shipment-tracking/badges/master/pipeline.svg)](https://gitlab.dev.trackmage.com/karser/trackmage-woo-shipment-tracking/pipelines)

[Download plugin from WordPress.org](https://wordpress.org/plugins/trackmage-woo-shipment-tracking/)<br>
<hr>
<p align="center">
  Your Beautiful, Branded, Highly Customizable TrackMage <b>Tracking Page</b>.
  <br><br>
  <a href='#'>
    <img src = "https://user-images.githubusercontent.com/1675033/84408463-d3be2a00-ac14-11ea-8ab1-df0302e00a32.png" width="700" alt="tracking page"/>
  </a>
</p>
<hr>

<p align="center">
  Provide your customers with <b>proactive</b> email updates.
  TrackMage has pre-configured <b>email notifications</b> for all of the typical shipment statuses and related events.
  <br><br>
  <a href='#'>
    <img src = "https://user-images.githubusercontent.com/1675033/84408630-0b2cd680-ac15-11ea-8e7c-091d7cf7858c.png" width="700" alt="Available for pickup email"/>
  </a>
</p>
<hr>

<p align="center">
  When your customer receives their package, the system will automatically ask them to <b>leave a review</b>.
  <br><br>
  <a href='#'>
    <img src = "https://user-images.githubusercontent.com/1675033/84408771-39121b00-ac15-11ea-8a32-f80a3dbc2405.png" width="700" alt="Leave a review email"/>
  </a>
</p>
<hr>

<p align="center">
  Depending on how high their <b>review score</b> was, your customer support team will be notified and you will be able to <b>react accordingly</b>.
  <br><br>
  <a href='#'>
    <img src = "https://user-images.githubusercontent.com/1675033/84408942-7676a880-ac15-11ea-8374-511ca13dbc51.png" width="700" alt="Leave a review on tracking page"/>
  </a>
</p>
<hr>

<p align="center">
 No Strings Attached, No Credit Card Required - <a href="https://s.trackmage.com/ef2424">Try TrackMage now</a>.
 <br>
 <br>
 And btw, if you are just starting out, there is <b>Forever Free plan with 100 Parcels Per Month</b>.
</p>
<hr>



## Local development
```
composer install
npm install
npm run build
```

Add this to wp-config.php to change the api domain:
```
define('TRACKMAGE_API_DOMAIN', 'https://api.test.trackmage.com');
define('TRACKMAGE_APP_DOMAIN', 'https://app.test.trackmage.com');
```


## Generate some orders:

```
git clone https://github.com/woocommerce/wc-smooth-generator.git
cd wc-smooth-generator/
composer install

docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli-php7.2 \
 wp plugin activate wc-smooth-generator

docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli-php7.2 \
 wp wc generate products 2

docker run -it --rm --volumes-from wpbrowser_wp --network container:wpbrowser_wp wordpress:cli-php7.2 \
 wp wc generate orders 100 --date-start=2020-04-01 --date-end=2020-11-15
```

## Testing:

Local commands:
```
export PHP_VERSION=7.4
make init
make test
vendor/bin/codecept run wpunit,unit,functional,acceptance
XDEBUG_CONFIG="idekey=PhpStorm1" vendor/bin/codecept run wpunit tests/wpunit/Synchronization/OrderSyncTest.php 
vendor/bin/codecept clean
docker-compose -f docker/docker-compose.yml down
```

Manual deployment to wordpress.org
```
make build
CI_SERVER=yes WP_ORG_USERNAME=trackmage WP_ORG_PASSWORD= GIT_TAG=v1.0.0 bin/deploy.sh
```

Docs:
```
https://codeception.com/for/wordpress
https://wpbrowser.wptestkit.dev/summary/levels-of-testing
```

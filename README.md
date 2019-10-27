[![Build Status](https://travis-ci.org/trackmage/trackmage-wordpress-plugin.svg?branch=master)](https://travis-ci.org/trackmage/trackmage-wordpress-plugin)

# trackmage-wordpress-plugin-test
TrackMage Wordpress Plugin


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

## Testing:

Local commands:
```
export PHP_VERSION=7.2
make sync_hosts_entries
make ci_local_prepare
make ci_before_script
make ci_script
vendor/bin/codecept run wpunit,unit,functional,acceptance
XDEBUG_CONFIG="idekey=PhpStorm1" vendor/bin/codecept run wpunit tests/wpunit/Syncrhonization/OrderSyncTest.php 
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

How to download the build:

1. Go to [Travis](https://travis-ci.org/trackmage/trackmage-wordpress-plugin) and find the build you need.
2. Find the job where BUILD_ZIP=true and open it
3. In the log find "Uploading Artifacts" section and find the url containing "wordpress-plugin.zip".
4. The url is a bit incorrect. Replace `https://s3.amazonaws.com/travis-uploaded-artifacts` with `https://travis-uploaded-artifacts.s3-us-west-2.amazonaws.com`, so it'll look like `https://travis-uploaded-artifacts.s3-us-west-2.amazonaws.com/trackmage/trackmage-wordpress-plugin/220/220.1/build/trackmage-wordpress-plugin.zip`
5. Download the plugin zip and manualy install it on stage.

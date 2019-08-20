[![Build Status](https://travis-ci.org/trackmage/trackmage-wordpress-plugin.svg?branch=master)](https://travis-ci.org/trackmage/trackmage-wordpress-plugin)

# trackmage-wordpress-plugin
TrackMage Wordpress Plugin


## Testing:

Local commands:
```
export PHP_VERSION=7.2
make ci_local_prepare
make ci_before_script
make ci_script
make down
```

Docs:
```
https://codeception.com/for/wordpress
https://wpbrowser.wptestkit.dev/summary/levels-of-testing
```

Travis artifacts bug in Download URL:
Incorrect url:
https://s3.amazonaws.com/travis-uploaded-artifacts/trackmage/trackmage-wordpress-plugin/56/56.1/CriticalPathCest.test.fail.html
Correct url:
https://travis-uploaded-artifacts.s3-us-west-2.amazonaws.com/trackmage/trackmage-wordpress-plugin/56/56.1/tests/_output/CriticalPathCest.test.fail.html

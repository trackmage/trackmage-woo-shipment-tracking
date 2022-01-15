<?php
/*
 * If in the contxext of a wp-cli request then use the localhost-accessible database host.
 */
define('DB_HOST', 'db');
define('DB_NAME', 'wp_site');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
define('TRACKMAGE_API_DOMAIN', 'https://api.test.trackmage.com');
define('TRACKMAGE_APP_DOMAIN', 'https://app.test.trackmage.com');

/*
 * Salts.
 */
define('AUTH_KEY', 'p}anr &nMFOk]h*x^w6|ZsCd U82PnN[i6LjbWx:2zW$sb8Fw*g)pj4<!dys$2e1');
define('SECURE_AUTH_KEY', '5:<K*z(X{b_BhRe5NTk^($?nP`0ly)<6cik6t!$E_JvC%t>GjE;%gx>*~oz47N0a');
define('LOGGED_IN_KEY', 'A4a$>Z}t~Gx-ucU)QY,ho*Z1uvUCYBgWs{GDhb4*%TXCR[=Ffq7BblN>~&|I.ZLY');
define('NONCE_KEY', 'D=sTX|z@AU$kKE*s~G$Se`z/ES=R^iuxq]cf`,=$2u+U8<w)AZ.{(J3yN5X?]t2t');
define('AUTH_SALT', '+JJDa|{{0DW<&}.tt/g=H(yJCQ(D[*S.<iS91!~ell1OWPzLOV ,EZGi{Bq0#Gsh');
define('SECURE_AUTH_SALT', 'pqq4_DwXhKaCIh;8p%zh2h%eiYnRU;Nvu+J2]KBXesFGCU ~Y@T|,6f98VGqX93$');
define('LOGGED_IN_SALT', '0Y,r*&66ssBpiSK,e}g|ljL[6pZg)R?l$~gB<YM_|;.z5VH]25uysy~)qn`<{gP,');
define('NONCE_SALT', '4M@IIkGjeeLL&>Bh;-Do*$?z0jIi|#n:S/*+pQPqiLw-_)pu~>Hzdn5BefnoGcZb');
define('WP_CACHE_KEY_SALT', '7V%9NEn<=oe6@c7+oFc8XxQ5J2@tFwk.g98p]p4ys:BGOaqsKpvipqu>H%#WH!I!');
$table_prefix = 'wp_';
/*
 * Override the site URL and home with the one that's being requested.
 */
if (filter_has_var(INPUT_SERVER, 'HTTP_HOST')) {
    if (! defined('WP_HOME')) {
        define('WP_HOME', 'http://' . $_SERVER['HTTP_HOST']);
    }
    if (! defined('WP_SITEURL')) {
        define('WP_SITEURL', 'http://' . $_SERVER['HTTP_HOST']);
    }
}


/** Absolute path to the WordPress directory. */
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}
/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

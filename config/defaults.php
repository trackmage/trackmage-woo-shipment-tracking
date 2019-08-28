<?php
/**
 * Plugin configuration file
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

$trackmage_plugin = [
	'textdomain'    => 'trackmage',
	'languages_dir' => 'languages',
];

$trackmage_settings = [
	'submenu_pages' => [],
	'settings'      => [],
];

return [
	'TrackMage' => [
		'WordPress' => [
		    'dropOnDeactivate' => true,
            'logLevel' => 'info',
			'Plugin'   => $trackmage_plugin,
			'Settings' => $trackmage_settings,
		],
	],
];

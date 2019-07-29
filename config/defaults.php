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
			'Plugin'   => $trackmage_plugin,
			'Settings' => $trackmage_settings,
		],
	],
];

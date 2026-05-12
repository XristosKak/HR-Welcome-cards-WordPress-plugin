<?php
/**
 * Plugin Name: Inventio Wellcome Card
 * Description: Συνθέτει την κάρτα καλωσορίσματος πάνω στο καμβά σας (φωτογραφία + κείμενο) και εξάγει JPG.
 * Version: 1.3.9
 * Author: Inventio
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: inventio-wellcome-card
 *
 * @package Inventio_Wellcome_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INVENTIO_WELCOME_VERSION', '1.3.9' );
define( 'INVENTIO_WELCOME_PLUGIN_FILE', __FILE__ );
define( 'INVENTIO_WELCOME_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INVENTIO_WELCOME_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once INVENTIO_WELCOME_PLUGIN_DIR . 'includes/class-inventio-wellcome-renderer.php';
require_once INVENTIO_WELCOME_PLUGIN_DIR . 'includes/class-inventio-wellcome-presets.php';
require_once INVENTIO_WELCOME_PLUGIN_DIR . 'includes/class-inventio-wellcome-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		Inventio_Wellcome_Presets::maybe_migrate_legacy();
		Inventio_Wellcome_Admin::instance();
	}
);

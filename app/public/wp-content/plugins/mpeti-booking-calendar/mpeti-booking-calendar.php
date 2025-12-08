<?php
/**
 * Plugin Name: mpeti Booking Calendar
 * Description: Full appointment booking system with calendar, staff, services, REST, and React admin UI.
 * Version: 1.0.0
 * Author: Peter Matyas
 * Text Domain: mpeti-booking-calendar
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MBC_PLUGIN_FILE', __FILE__ );
define( 'MBC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MBC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MBC_PLUGIN_VERSION', '1.0.0' );

/**
 * Simple PSR-4 style autoloader for MBC namespace.
 */
spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'MBC\\' ) ) {
			return;
		}
		// Convert namespace to dashed filename, e.g. MBC\Loader => class-mbc-loader.php.
		$relative = strtolower( str_replace( array( '\\', '_' ), array( '-', '-' ), $class ) );
		$file     = MBC_PLUGIN_DIR . 'includes/class-' . $relative . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Bootstrap plugin.
 */
function mbc_bootstrap() {
	$loader = new MBC\Loader();
	$loader->init();
}
add_action( 'plugins_loaded', 'mbc_bootstrap' );

/**
 * Activation/Deactivation hooks.
 */
function mbc_activate() {
	$loader = new MBC\Loader();
	$loader->activate();
	flush_rewrite_rules();
}
function mbc_deactivate() {
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'mbc_activate' );
register_deactivation_hook( __FILE__, 'mbc_deactivate' );


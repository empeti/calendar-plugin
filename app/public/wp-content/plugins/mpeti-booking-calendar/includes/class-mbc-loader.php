<?php
namespace MBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main loader to wire components.
 */
class Loader {

	/**
	 * Start plugin.
	 */
	public function init(): void {
		$this->load_textdomain();
		$this->register_hooks();
	}

	/**
	 * Activation tasks.
	 */
	public function activate(): void {
		$cpts = array(
			new CPT_Appointment(),
			new CPT_Staff(),
			new CPT_Service(),
		);
		foreach ( $cpts as $cpt ) {
			$cpt->register();
		}

		$timeslots = new Timeslots();
		$timeslots->maybe_create_table();
	}

	/**
	 * Textdomain load.
	 */
	protected function load_textdomain(): void {
		\load_plugin_textdomain( 'mpeti-booking-calendar', false, \basename( MBC_PLUGIN_DIR ) . '/languages' );
	}

	/**
	 * Hook wiring.
	 */
	protected function register_hooks(): void {
		$appointment = new CPT_Appointment();
		$staff       = new CPT_Staff();
		$service     = new CPT_Service();
		$settings    = new Settings();
		$timeslots   = new Timeslots();
		$email       = new Email();
		$rest        = new Rest( $timeslots, $email );
		$frontend    = new Frontend( $timeslots );
		$admin_page  = new Admin_Page();

		\add_action( 'init', array( $appointment, 'register' ) );
		\add_action( 'init', array( $staff, 'register' ) );
		\add_action( 'init', array( $service, 'register' ) );

		\add_filter( 'manage_mbc_appointment_posts_columns', array( $appointment, 'add_columns' ) );
		\add_action( 'manage_mbc_appointment_posts_custom_column', array( $appointment, 'render_columns' ), 10, 2 );
		\add_filter( 'post_row_actions', array( $appointment, 'row_actions' ), 10, 2 );
		\add_action( 'admin_init', array( $appointment, 'handle_row_actions' ) );

		\add_action( 'admin_menu', array( $admin_page, 'register_menus' ) );
		\add_action( 'admin_init', array( $settings, 'register_settings' ) );
		\add_action( 'admin_enqueue_scripts', array( $admin_page, 'enqueue_assets' ) );

		\add_action( 'add_meta_boxes_mbc_appointment', array( $appointment, 'register_meta_boxes' ) );
		\add_action( 'save_post_mbc_appointment', array( $appointment, 'save_meta_box' ), 10, 2 );

		\add_action( 'rest_api_init', array( $rest, 'register_routes' ) );

		\add_action( 'wp_enqueue_scripts', array( $frontend, 'enqueue_frontend' ) );
		\add_action( 'init', array( $frontend, 'register_shortcode' ) );
		\add_action( 'init', array( $frontend, 'register_block' ) );
	}
}


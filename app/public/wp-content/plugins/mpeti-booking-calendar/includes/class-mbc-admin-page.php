<?php
namespace MBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Page {

	public function register_menus(): void {
		$capability = 'manage_options';

		\add_menu_page(
			\__( 'Booking Calendar (mpeti)', 'mpeti-booking-calendar' ),
			\__( 'Booking Calendar (mpeti)', 'mpeti-booking-calendar' ),
			$capability,
			'mbc_admin_calendar',
			array( $this, 'render_calendar' ),
			'dashicons-calendar-alt',
			26
		);

		\add_submenu_page(
			'mbc_admin_calendar',
			\__( 'Calendar', 'mpeti-booking-calendar' ),
			\__( 'Calendar', 'mpeti-booking-calendar' ),
			$capability,
			'mbc_admin_calendar',
			array( $this, 'render_calendar' )
		);

		\add_submenu_page(
			'mbc_admin_calendar',
			\__( 'Appointments', 'mpeti-booking-calendar' ),
			\__( 'Appointments', 'mpeti-booking-calendar' ),
			$capability,
			'edit.php?post_type=mbc_appointment'
		);

		\add_submenu_page(
			'mbc_admin_calendar',
			\__( 'Staff', 'mpeti-booking-calendar' ),
			\__( 'Staff', 'mpeti-booking-calendar' ),
			$capability,
			'edit.php?post_type=mbc_staff'
		);

		\add_submenu_page(
			'mbc_admin_calendar',
			\__( 'Services', 'mpeti-booking-calendar' ),
			\__( 'Services', 'mpeti-booking-calendar' ),
			$capability,
			'edit.php?post_type=mbc_service'
		);

		\add_submenu_page(
			'mbc_admin_calendar',
			\__( 'Settings', 'mpeti-booking-calendar' ),
			\__( 'Settings', 'mpeti-booking-calendar' ),
			$capability,
			'mbc_settings',
			array( new Settings(), 'render_page' )
		);
	}

	public function render_calendar(): void {
		include MBC_PLUGIN_DIR . 'templates/admin-calendar-root.php';
	}

	public function enqueue_assets( string $hook ): void {
		if ( false === \strpos( $hook, 'mbc_admin_calendar' ) ) {
			return;
		}

		\wp_enqueue_style(
			'mbc-admin',
			MBC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MBC_PLUGIN_VERSION
		);

		\wp_enqueue_script(
			'mbc-admin-calendar',
			MBC_PLUGIN_URL . 'assets/js/admin-calendar.js',
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-data' ),
			MBC_PLUGIN_VERSION,
			true
		);

		\wp_localize_script(
			'mbc-admin-calendar',
			'MBCAdmin',
			array(
				'restUrl' => \esc_url_raw( \rest_url( 'mpeti-booking-calendar/v1' ) ),
				'nonce'   => \wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'loading' => \__( 'Loading...', 'mpeti-booking-calendar' ),
				),
			)
		);
	}
}


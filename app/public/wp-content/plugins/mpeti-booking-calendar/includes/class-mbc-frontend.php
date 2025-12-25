<?php
namespace MBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Frontend {

	protected Timeslots $timeslots;

	public function __construct( Timeslots $timeslots ) {
		$this->timeslots = $timeslots;
	}

	public function register_shortcode(): void {
		\add_shortcode( 'mpeti_booking_calendar', array( $this, 'render_shortcode' ) );
	}

	public function register_block(): void {
		if ( ! \function_exists( 'register_block_type' ) ) {
			return;
		}
		\register_block_type(
			'mpeti-booking-calendar/block',
			array(
				'render_callback' => array( $this, 'render_shortcode' ),
				'attributes'      => array(
					'staff'   => array( 'type' => 'integer' ),
					'service' => array( 'type' => 'integer' ),
				),
			)
		);
	}

	public function enqueue_frontend(): void {
		if ( ! $this->should_load_assets() ) {
			return;
		}
		\wp_enqueue_style( 'mbc-frontend', MBC_PLUGIN_URL . 'assets/css/frontend.css', array(), MBC_PLUGIN_VERSION );
		\wp_enqueue_script(
			'mbc-frontend',
			MBC_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'wp-element', 'wp-api-fetch' ),
			MBC_PLUGIN_VERSION,
			true
		);
			\wp_localize_script(
				'mbc-frontend',
				'MBCBooking',
				array(
					'restUrl'   => \esc_url_raw( \rest_url( 'mpeti-booking-calendar/v1' ) ),
					'restNonce' => \wp_create_nonce( 'wp_rest' ),
					'nonce'     => \wp_create_nonce( 'mbc_frontend_booking' ),
					'i18n'      => array(
						'submit'        => \__( 'Book', 'mpeti-booking-calendar' ),
						'success'       => \__( 'Your appointment has been requested.', 'mpeti-booking-calendar' ),
						'error'         => \__( 'There was an error. Please try again.', 'mpeti-booking-calendar' ),
						'thankYou'      => \__( 'Thank You!', 'mpeti-booking-calendar' ),
						'successMessage' => \__( 'Your appointment request has been submitted successfully.', 'mpeti-booking-calendar' ),
						'successDetails' => \__( 'We will review your request and send you a confirmation email shortly.', 'mpeti-booking-calendar' ),
						'newAppointment' => \__( 'Book Another Appointment', 'mpeti-booking-calendar' ),
					),
				)
			);
	}

	protected function should_load_assets(): bool {
		if ( \is_singular() ) {
			global $post;
			if ( \has_shortcode( $post->post_content, 'mpeti_booking_calendar' ) ) {
				return true;
			}
		}
		return false;
	}

	public function render_shortcode( $atts = array() ): string {
		$atts = \shortcode_atts(
			array(
				'staff'   => '',
				'service' => '',
			),
			$atts
		);

		\ob_start();
		$staff_id   = $atts['staff'] ? \intval( $atts['staff'] ) : null;
		$service_id = $atts['service'] ? \intval( $atts['service'] ) : null;
		$available  = $this->timeslots->get_available_slots( \gmdate( 'Y-m-d' ), $staff_id, $service_id );
		$services   = \get_posts(
			array(
				'post_type'      => 'mbc_service',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);
		$staff      = \get_posts(
			array(
				'post_type'      => 'mbc_staff',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		include MBC_PLUGIN_DIR . 'templates/calendar.php';
		include MBC_PLUGIN_DIR . 'templates/booking-form.php';

		return \ob_get_clean();
	}
}


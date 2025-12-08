<?php
namespace MBC;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest {

	protected Timeslots $timeslots;
	protected Email $email;

	public function __construct( Timeslots $timeslots, Email $email ) {
		$this->timeslots = $timeslots;
		$this->email     = $email;
	}

	public function register_routes(): void {
		\register_rest_route(
			'mpeti-booking-calendar/v1',
			'/available-slots',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_available_slots' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'date'   => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_date' ),
					),
					'staff'  => array(
						'type'              => 'integer',
						'sanitize_callback' => function( $value ) {
							return empty( $value ) ? null : \absint( $value );
						},
						'validate_callback' => function( $value ) {
							return empty( $value ) || \is_numeric( $value );
						},
					),
					'service'=> array(
						'type'              => 'integer',
						'sanitize_callback' => function( $value ) {
							return empty( $value ) ? null : \absint( $value );
						},
						'validate_callback' => function( $value ) {
							return empty( $value ) || \is_numeric( $value );
						},
					),
				),
			)
		);

		\register_rest_route(
			'mpeti-booking-calendar/v1',
			'/book',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'book_slot' ),
				'permission_callback' => '__return_true',
			)
		);

		\register_rest_route(
			'mpeti-booking-calendar/v1',
			'/appointments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_appointments' ),
				'permission_callback' => function() {
					return \current_user_can( 'manage_options' );
				},
			)
		);

		\register_rest_route(
			'mpeti-booking-calendar/v1',
			'/available-staff',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_available_staff' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'service' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'date'    => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_date' ),
					),
				),
			)
		);
	}

	public function validate_date( $value ): bool {
		return (bool) \preg_match( '/^\\d{4}-\\d{2}-\\d{2}$/', $value );
	}

	public function get_available_slots( WP_REST_Request $request ) {
		$date    = $request->get_param( 'date' );
		$staff   = $request->get_param( 'staff' );
		$service = $request->get_param( 'service' );

		if ( ! $this->validate_date( $date ) ) {
			return new WP_Error( 'invalid_date', \__( 'Invalid date format', 'mpeti-booking-calendar' ), array( 'status' => 400 ) );
		}

		// Convert empty strings to null, ensure integers are properly cast
		$staff_id   = ( ! empty( $staff ) && \is_numeric( $staff ) ) ? (int) $staff : null;
		$service_id = ( ! empty( $service ) && \is_numeric( $service ) ) ? (int) $service : null;

		$slots = $this->timeslots->get_available_slots( $date, $staff_id, $service_id );

		return new WP_REST_Response(
			array(
				'date'  => $date,
				'slots' => $slots,
			)
		);
	}

	public function book_slot( WP_REST_Request $request ) {
		$params = $request->get_params();

		$nonce = isset( $params['nonce'] ) ? \sanitize_text_field( $params['nonce'] ) : '';
		if ( ! \wp_verify_nonce( $nonce, 'mbc_frontend_booking' ) ) {
			return new WP_Error( 'invalid_nonce', \__( 'Invalid submission.', 'mpeti-booking-calendar' ), array( 'status' => 403 ) );
		}

		$name   = isset( $params['name'] ) ? \sanitize_text_field( $params['name'] ) : '';
		$email  = isset( $params['email'] ) ? \sanitize_email( $params['email'] ) : '';
		$date   = isset( $params['date'] ) ? \sanitize_text_field( $params['date'] ) : '';
		$time   = isset( $params['time'] ) ? \sanitize_text_field( $params['time'] ) : '';
		$service_id = isset( $params['service_id'] ) ? \intval( $params['service_id'] ) : 0;
		$staff_id   = isset( $params['staff_id'] ) ? \intval( $params['staff_id'] ) : 0;
		$notes      = isset( $params['notes'] ) ? \wp_kses_post( $params['notes'] ) : '';
		$phone      = isset( $params['phone'] ) ? \sanitize_text_field( $params['phone'] ) : '';

		if ( empty( $name ) || empty( $email ) || empty( $date ) || empty( $time ) ) {
			return new WP_Error( 'missing_fields', \__( 'Required fields missing.', 'mpeti-booking-calendar' ), array( 'status' => 400 ) );
		}
		if ( ! \is_email( $email ) ) {
			return new WP_Error( 'invalid_email', \__( 'Invalid email.', 'mpeti-booking-calendar' ), array( 'status' => 400 ) );
		}

		if ( ! $this->timeslots->is_slot_available( $date, $time, $staff_id ?: null, $service_id ?: null ) ) {
			return new WP_Error( 'slot_unavailable', \__( 'Selected time is no longer available.', 'mpeti-booking-calendar' ), array( 'status' => 409 ) );
		}

		$post_id = \wp_insert_post(
			array(
				'post_title'  => \sprintf( '%s - %s', $date, $name ),
				'post_type'   => 'mbc_appointment',
				'post_status' => 'publish',
			)
		);

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta = array(
			'customer_name'        => $name,
			'customer_email'       => $email,
			'customer_phone'       => $phone,
			'appointment_date'     => $date,
			'appointment_time'     => $time,
			'appointment_status'   => 'pending',
			'service_id'           => $service_id,
			'staff_id'             => $staff_id,
			'notes'                => $notes,
			'appointment_datetime' => $date . ' ' . $time,
		);
		foreach ( $meta as $key => $value ) {
			\update_post_meta( $post_id, $key, $value );
		}

		$this->email->send_admin_notification( $post_id );
		$this->email->send_customer_notification( $post_id );

		return new WP_REST_Response(
			array(
				'success'        => true,
				'appointment_id' => $post_id,
				'message'        => \__( 'Your appointment has been requested.', 'mpeti-booking-calendar' ),
			)
		);
	}

	public function get_available_staff( WP_REST_Request $request ) {
		$service_id = $request->get_param( 'service' );
		$date       = $request->get_param( 'date' );

		if ( ! $this->validate_date( $date ) ) {
			return new WP_Error( 'invalid_date', \__( 'Invalid date format', 'mpeti-booking-calendar' ), array( 'status' => 400 ) );
		}

		$service_id_int = (int) $service_id;
		if ( ! $service_id_int ) {
			return new WP_Error( 'invalid_service', \__( 'Invalid service ID', 'mpeti-booking-calendar' ), array( 'status' => 400 ) );
		}

		// Get all staff members assigned to this service
		$all_staff = \get_posts( array(
			'post_type'      => 'mbc_staff',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'    => array(
				array(
					'key'     => 'staff_active',
					'value'   => '1',
					'compare' => '=',
				),
			),
		) );

		$available_staff = array();

		foreach ( $all_staff as $staff ) {
			$staff_services = \get_post_meta( $staff->ID, 'staff_services', true );
			if ( ! \is_array( $staff_services ) ) {
				$staff_services = array();
			}

			// Normalize staff services to integers for comparison
			$staff_services = \array_map( 'intval', $staff_services );

			// Check if staff is assigned to this service
			if ( ! \in_array( $service_id_int, $staff_services, true ) ) {
				continue;
			}

			// Check if staff has available slots on this date
			$slots = $this->timeslots->get_available_slots( $date, $staff->ID, $service_id );
			$has_available = false;
			foreach ( $slots as $slot ) {
				if ( $slot['available'] ) {
					$has_available = true;
					break;
				}
			}

			if ( $has_available ) {
				$photo_id = \get_post_meta( $staff->ID, 'staff_photo', true );
				$photo_url = $photo_id ? \wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
				
				// Get services assigned to this staff member
				$staff_services_ids = \get_post_meta( $staff->ID, 'staff_services', true );
				$services_list = array();
				if ( \is_array( $staff_services_ids ) && ! empty( $staff_services_ids ) ) {
					$staff_services_ids = \array_map( 'intval', $staff_services_ids );
					$services = \get_posts( array(
						'post_type'      => 'mbc_service',
						'post__in'       => $staff_services_ids,
						'posts_per_page' => -1,
						'post_status'    => 'publish',
						'orderby'        => 'title',
						'order'          => 'ASC',
					) );
					foreach ( $services as $service ) {
						$services_list[] = array(
							'id'    => $service->ID,
							'name'  => $service->post_title,
						);
					}
				}

				$available_staff[] = array(
					'id'       => $staff->ID,
					'name'     => $staff->post_title,
					'photo'    => $photo_url,
					'services' => $services_list,
				);
			}
		}

		return new WP_REST_Response(
			array(
				'staff' => $available_staff,
			)
		);
	}

	public function get_appointments( WP_REST_Request $request ) {
		$args = array(
			'post_type'      => 'mbc_appointment',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'meta_query'     => array(),
		);

		if ( $request->get_param( 'status' ) ) {
			$args['meta_query'][] = array(
				'key'   => 'appointment_status',
				'value' => \sanitize_text_field( $request->get_param( 'status' ) ),
			);
		}
		if ( $request->get_param( 'staff' ) ) {
			$args['meta_query'][] = array(
				'key'   => 'staff_id',
				'value' => \intval( $request->get_param( 'staff' ) ),
			);
		}
		if ( $request->get_param( 'service' ) ) {
			$args['meta_query'][] = array(
				'key'   => 'service_id',
				'value' => \intval( $request->get_param( 'service' ) ),
			);
		}

		$query = new \WP_Query( $args );
		$data  = array();

		foreach ( $query->posts as $post_id ) {
			$data[] = array(
				'id'       => $post_id,
				'date'     => \get_post_meta( $post_id, 'appointment_date', true ),
				'time'     => \get_post_meta( $post_id, 'appointment_time', true ),
				'customer' => \get_post_meta( $post_id, 'customer_name', true ),
				'status'   => \get_post_meta( $post_id, 'appointment_status', true ),
				'staff'    => \get_post_meta( $post_id, 'staff_id', true ),
				'service'  => \get_post_meta( $post_id, 'service_id', true ),
				'staff_color'   => \get_post_meta( \get_post_meta( $post_id, 'staff_id', true ), 'staff_color', true ),
				'service_color' => \get_post_meta( \get_post_meta( $post_id, 'service_id', true ), 'service_color', true ),
			);
		}

		return new WP_REST_Response( $data );
	}
}


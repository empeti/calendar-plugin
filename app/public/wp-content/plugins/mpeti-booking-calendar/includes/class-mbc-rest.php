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
			'/appointments/(?P<id>\d+)/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_appointment_status' ),
				'permission_callback' => function() {
					return \current_user_can( 'manage_options' );
				},
				'args'                => array(
					'id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'status' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function( $value ) {
							return in_array( $value, array( 'pending', 'confirmed', 'cancelled' ), true );
						},
					),
				),
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

		\register_rest_route(
			'mpeti-booking-calendar/v1',
			'/staff',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_all_staff' ),
				'permission_callback' => function() {
					return \current_user_can( 'manage_options' );
				},
			)
		);

		\register_rest_route(
			'mpeti-booking-calendar/v1',
			'/services',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_all_services' ),
				'permission_callback' => function() {
					return \current_user_can( 'manage_options' );
				},
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

		// Verify nonce - accept either form nonce or REST API nonce
		$nonce_valid = false;
		
		// First, try the form nonce from FormData
		if ( isset( $params['nonce'] ) && ! empty( $params['nonce'] ) ) {
			$form_nonce = \sanitize_text_field( $params['nonce'] );
			$nonce_valid = \wp_verify_nonce( $form_nonce, 'mbc_frontend_booking' );
		}
		
		// If form nonce fails or doesn't exist, try REST API nonce from headers
		if ( ! $nonce_valid && $request->get_header( 'X-WP-Nonce' ) ) {
			$rest_nonce = $request->get_header( 'X-WP-Nonce' );
			$nonce_valid = \wp_verify_nonce( $rest_nonce, 'wp_rest' );
		}

		if ( ! $nonce_valid ) {
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
			'meta_query'     => array(
				'relation' => 'AND',
			),
		);

		// Handle status filter (can be array for multiselect)
		$status_param = $request->get_param( 'status' );
		if ( $status_param ) {
			$status_values = is_array( $status_param ) ? $status_param : array( $status_param );
			if ( ! empty( $status_values ) ) {
				$sanitized_status = array_map( 'sanitize_text_field', $status_values );
				if ( count( $sanitized_status ) === 1 ) {
					$args['meta_query'][] = array(
						'key'   => 'appointment_status',
						'value' => $sanitized_status[0],
					);
				} else {
					$args['meta_query'][] = array(
						'key'     => 'appointment_status',
						'value'   => $sanitized_status,
						'compare' => 'IN',
					);
				}
			}
		}

		// Handle staff filter (can be array for multiselect)
		$staff_param = $request->get_param( 'staff' );
		if ( $staff_param ) {
			$staff_values = is_array( $staff_param ) ? $staff_param : array( $staff_param );
			if ( ! empty( $staff_values ) ) {
				$sanitized_staff = array_map( 'absint', $staff_values );
				if ( count( $sanitized_staff ) === 1 ) {
					$args['meta_query'][] = array(
						'key'   => 'staff_id',
						'value' => $sanitized_staff[0],
					);
				} else {
					$args['meta_query'][] = array(
						'key'     => 'staff_id',
						'value'   => $sanitized_staff,
						'compare' => 'IN',
					);
				}
			}
		}

		// Handle service filter (can be array for multiselect)
		$service_param = $request->get_param( 'service' );
		if ( $service_param ) {
			$service_values = is_array( $service_param ) ? $service_param : array( $service_param );
			if ( ! empty( $service_values ) ) {
				$sanitized_service = array_map( 'absint', $service_values );
				if ( count( $sanitized_service ) === 1 ) {
					$args['meta_query'][] = array(
						'key'   => 'service_id',
						'value' => $sanitized_service[0],
					);
				} else {
					$args['meta_query'][] = array(
						'key'     => 'service_id',
						'value'   => $sanitized_service,
						'compare' => 'IN',
					);
				}
			}
		}

		$query = new \WP_Query( $args );
		$data  = array();

		foreach ( $query->posts as $post ) {
			$post_id = $post->ID;
			$staff_id = \get_post_meta( $post_id, 'staff_id', true );
			$service_id = \get_post_meta( $post_id, 'service_id', true );
			
			$staff_name = '';
			$staff_photo_url = '';
			if ( $staff_id ) {
				$staff_post = \get_post( $staff_id );
				$staff_name = $staff_post ? $staff_post->post_title : '';
				$photo_id = \get_post_meta( $staff_id, 'staff_photo', true );
				if ( $photo_id ) {
					$staff_photo_url = \wp_get_attachment_image_url( $photo_id, 'thumbnail' );
				}
			}
			
			$service_name = '';
			$service_duration = 60; // Default duration
			if ( $service_id ) {
				$service_post = \get_post( $service_id );
				$service_name = $service_post ? $service_post->post_title : '';
				$service_duration = \get_post_meta( $service_id, 'service_duration', true );
				if ( ! $service_duration ) {
					$settings = \get_option( 'mpeti_booking_calendar_settings', array() );
					$service_duration = isset( $settings['default_duration'] ) ? (int) $settings['default_duration'] : 60;
				} else {
					$service_duration = (int) $service_duration;
				}
			}
			
			$data[] = array(
				'id'       => $post_id,
				'date'     => \get_post_meta( $post_id, 'appointment_date', true ),
				'time'     => \get_post_meta( $post_id, 'appointment_time', true ),
				'customer' => \get_post_meta( $post_id, 'customer_name', true ),
				'customer_email' => \get_post_meta( $post_id, 'customer_email', true ),
				'customer_phone' => \get_post_meta( $post_id, 'customer_phone', true ),
				'status'   => \get_post_meta( $post_id, 'appointment_status', true ) ?: 'pending',
				'staff'    => $staff_id,
				'staff_name' => $staff_name,
				'staff_photo' => $staff_photo_url,
				'service'  => $service_id,
				'service_name' => $service_name,
				'service_duration' => $service_duration,
				'staff_color'   => $staff_id ? \get_post_meta( $staff_id, 'staff_color', true ) : '',
				'service_color' => $service_id ? \get_post_meta( $service_id, 'service_color', true ) : '',
			);
		}

		return new WP_REST_Response( $data );
	}

	public function update_appointment_status( WP_REST_Request $request ) {
		$appointment_id = $request->get_param( 'id' );
		// Get status from body (JSON) or params
		$body = $request->get_json_params();
		$status = isset( $body['status'] ) ? $body['status'] : $request->get_param( 'status' );

		if ( ! \current_user_can( 'edit_post', $appointment_id ) ) {
			return new WP_Error( 'permission_denied', \__( 'You do not have permission to update this appointment.', 'mpeti-booking-calendar' ), array( 'status' => 403 ) );
		}

		$post = \get_post( $appointment_id );
		if ( ! $post || 'mbc_appointment' !== $post->post_type ) {
			return new WP_Error( 'invalid_appointment', \__( 'Invalid appointment ID.', 'mpeti-booking-calendar' ), array( 'status' => 404 ) );
		}

		$old_status = \get_post_meta( $appointment_id, 'appointment_status', true );
		\update_post_meta( $appointment_id, 'appointment_status', $status );

		// Send email notifications if status changed
		if ( $status !== $old_status ) {
			$this->email->send_admin_notification( $appointment_id );
			$this->email->send_customer_notification( $appointment_id );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => $status,
				'message' => \__( 'Appointment status updated.', 'mpeti-booking-calendar' ),
			)
		);
	}

	public function get_all_staff( WP_REST_Request $request ) {
		$staff = \get_posts( array(
			'post_type'      => 'mbc_staff',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$data = array();
		foreach ( $staff as $staff_member ) {
			$data[] = array(
				'id'   => $staff_member->ID,
				'name' => $staff_member->post_title,
			);
		}

		return new WP_REST_Response( $data );
	}

	public function get_all_services( WP_REST_Request $request ) {
		$services = \get_posts( array(
			'post_type'      => 'mbc_service',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$data = array();
		foreach ( $services as $service ) {
			$data[] = array(
				'id'   => $service->ID,
				'name' => $service->post_title,
			);
		}

		return new WP_REST_Response( $data );
	}
}


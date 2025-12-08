<?php
namespace MBC;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appointment CPT and meta.
 */
class CPT_Appointment {

	public function register(): void {
		$labels = array(
			'name'               => \__( 'Appointments', 'mpeti-booking-calendar' ),
			'singular_name'      => \__( 'Appointment', 'mpeti-booking-calendar' ),
			'add_new'            => \__( 'Add Appointment', 'mpeti-booking-calendar' ),
			'add_new_item'       => \__( 'Add New Appointment', 'mpeti-booking-calendar' ),
			'edit_item'          => \__( 'Edit Appointment', 'mpeti-booking-calendar' ),
			'new_item'           => \__( 'New Appointment', 'mpeti-booking-calendar' ),
			'all_items'          => \__( 'Appointments', 'mpeti-booking-calendar' ),
			'view_item'          => \__( 'View Appointment', 'mpeti-booking-calendar' ),
			'search_items'       => \__( 'Search Appointments', 'mpeti-booking-calendar' ),
			'not_found'          => \__( 'No appointments found', 'mpeti-booking-calendar' ),
			'not_found_in_trash' => \__( 'No appointments found in Trash', 'mpeti-booking-calendar' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'exclude_from_search'=> true,
			'show_in_menu'       => false,
			'supports'           => array( 'title' ),
			'capability_type'    => 'post',
			'show_in_rest'       => false,
			'rewrite'            => false,
		);

		\register_post_type( 'mbc_appointment', $args );
	}

	public function register_meta_boxes(): void {
		\add_meta_box(
			'mbc_appointment_details',
			\__( 'Appointment Details', 'mpeti-booking-calendar' ),
			array( $this, 'render_meta_box' ),
			'mbc_appointment',
			'normal',
			'high'
		);
	}

	/**
	 * Handle custom row actions.
	 */
	public function handle_row_actions(): void {
		if ( empty( $_GET['mbc_action'] ) || empty( $_GET['_wpnonce'] ) || empty( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$action  = \sanitize_text_field( \wp_unslash( $_GET['mbc_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = \intval( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) ), 'mbc_row_action_' . $post_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'confirm' === $action ) {
			\update_post_meta( $post_id, 'appointment_status', 'confirmed' );
		} elseif ( 'cancel' === $action ) {
			\update_post_meta( $post_id, 'appointment_status', 'cancelled' );
		}
		\wp_safe_redirect( \remove_query_arg( array( 'mbc_action', '_wpnonce' ) ) );
		exit;
	}

	public function add_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['appointment_date'] = \__( 'Date', 'mpeti-booking-calendar' );
				$new['appointment_time'] = \__( 'Time', 'mpeti-booking-calendar' );
				$new['customer']         = \__( 'Customer', 'mpeti-booking-calendar' );
				$new['status']           = \__( 'Status', 'mpeti-booking-calendar' );
				$new['staff']            = \__( 'Staff', 'mpeti-booking-calendar' );
				$new['service']          = \__( 'Service', 'mpeti-booking-calendar' );
			}
		}
		return $new;
	}

	public function render_columns( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'appointment_date':
				echo \esc_html( \get_post_meta( $post_id, 'appointment_date', true ) );
				break;
			case 'appointment_time':
				echo \esc_html( \get_post_meta( $post_id, 'appointment_time', true ) );
				break;
			case 'customer':
				echo \esc_html( \get_post_meta( $post_id, 'customer_name', true ) );
				break;
			case 'status':
				echo \esc_html( \get_post_meta( $post_id, 'appointment_status', true ) );
				break;
			case 'staff':
				$staff_id = \get_post_meta( $post_id, 'staff_id', true );
				echo \esc_html( $staff_id ? \get_the_title( $staff_id ) : '' );
				break;
			case 'service':
				$service_id = \get_post_meta( $post_id, 'service_id', true );
				echo \esc_html( $service_id ? \get_the_title( $service_id ) : '' );
				break;
		}
	}

	public function row_actions( array $actions, WP_Post $post ): array {
		if ( 'mbc_appointment' !== $post->post_type ) {
			return $actions;
		}
		$nonce = \wp_create_nonce( 'mbc_row_action_' . $post->ID );
		$confirm_url = \add_query_arg(
			array(
				'mbc_action' => 'confirm',
				'post'       => $post->ID,
				'_wpnonce'   => $nonce,
			),
			\admin_url( 'edit.php?post_type=mbc_appointment' )
		);
		$cancel_url = \add_query_arg(
			array(
				'mbc_action' => 'cancel',
				'post'       => $post->ID,
				'_wpnonce'   => $nonce,
			),
			\admin_url( 'edit.php?post_type=mbc_appointment' )
		);

		$actions['confirm'] = '<a href="' . \esc_url( $confirm_url ) . '">' . \esc_html__( 'Confirm', 'mpeti-booking-calendar' ) . '</a>';
		$actions['cancel']  = '<a href="' . \esc_url( $cancel_url ) . '">' . \esc_html__( 'Cancel', 'mpeti-booking-calendar' ) . '</a>';

		return $actions;
	}

	public function render_meta_box( WP_Post $post ): void {
		\wp_nonce_field( 'mbc_save_appointment', 'mbc_appointment_nonce' );

		$fields = array(
			'customer_name'       => \get_post_meta( $post->ID, 'customer_name', true ),
			'customer_email'      => \get_post_meta( $post->ID, 'customer_email', true ),
			'customer_phone'      => \get_post_meta( $post->ID, 'customer_phone', true ),
			'appointment_date'    => \get_post_meta( $post->ID, 'appointment_date', true ),
			'appointment_time'    => \get_post_meta( $post->ID, 'appointment_time', true ),
			'appointment_status'  => \get_post_meta( $post->ID, 'appointment_status', true ),
			'service_id'          => \get_post_meta( $post->ID, 'service_id', true ),
			'staff_id'            => \get_post_meta( $post->ID, 'staff_id', true ),
			'notes'               => \get_post_meta( $post->ID, 'notes', true ),
		);

		$services = \get_posts(
			array(
				'post_type'      => 'mbc_service',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);
		$staff = \get_posts(
			array(
				'post_type'      => 'mbc_staff',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		include MBC_PLUGIN_DIR . 'templates/admin-appointment-edit.php';
	}

	public function save_meta_box( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['mbc_appointment_nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['mbc_appointment_nonce'] ) ), 'mbc_save_appointment' ) ) {
			return;
		}
		if ( \defined( 'DOING_AUTOSAVE' ) && \DOING_AUTOSAVE ) {
			return;
		}
		if ( 'mbc_appointment' !== $post->post_type ) {
			return;
		}
		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'customer_name'      => 'sanitize_text_field',
			'customer_email'     => 'sanitize_email',
			'customer_phone'     => 'sanitize_text_field',
			'appointment_date'   => 'sanitize_text_field',
			'appointment_time'   => 'sanitize_text_field',
			'appointment_status' => 'sanitize_text_field',
			'service_id'         => 'intval',
			'staff_id'           => 'intval',
			'notes'              => 'wp_kses_post',
		);

		foreach ( $fields as $key => $sanitize ) {
			$value = isset( $_POST[ $key ] ) ? $_POST[ $key ] : '';
			if ( \is_array( $value ) ) {
				continue;
			}
			if ( 'intval' === $sanitize ) {
				$value = \intval( $value );
			} elseif ( \is_callable( $sanitize ) ) {
				$value = \call_user_func( $sanitize, \wp_unslash( $value ) );
			}
			\update_post_meta( $post_id, $key, $value );
		}

		$date = isset( $_POST['appointment_date'] ) ? \sanitize_text_field( \wp_unslash( $_POST['appointment_date'] ) ) : '';
		$time = isset( $_POST['appointment_time'] ) ? \sanitize_text_field( \wp_unslash( $_POST['appointment_time'] ) ) : '';
		if ( $date && $time ) {
			\update_post_meta( $post_id, 'appointment_datetime', $date . ' ' . $time );
		}

		$old_status = \get_post_meta( $post_id, 'appointment_status', true );
		$new_status = isset( $_POST['appointment_status'] ) ? \sanitize_text_field( \wp_unslash( $_POST['appointment_status'] ) ) : '';
		if ( $new_status && $new_status !== $old_status ) {
			$email = new Email();
			$email->send_admin_notification( $post_id );
			$email->send_customer_notification( $post_id );
		}
	}
}


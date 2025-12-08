<?php
namespace MBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email {

	public function send_admin_notification( int $appointment_id ): void {
		$settings = \get_option( Settings::OPTION_KEY, array() );
		$to       = isset( $settings['admin_email'] ) ? $settings['admin_email'] : \get_option( 'admin_email' );
		$subject  = \__( 'New appointment request', 'mpeti-booking-calendar' );
		$message  = $this->build_message( $appointment_id, \__( 'A new appointment has been requested.', 'mpeti-booking-calendar' ) );

		\wp_mail( $to, $subject, $message, $this->headers() );
	}

	public function send_customer_notification( int $appointment_id ): void {
		$settings = \get_option( Settings::OPTION_KEY, array() );
		if ( empty( $settings['enable_customer_emails'] ) ) {
			return;
		}
		$email = \get_post_meta( $appointment_id, 'customer_email', true );
		if ( ! $email ) {
			return;
		}
		$subject = \__( 'Your appointment request', 'mpeti-booking-calendar' );
		$message = $this->build_message( $appointment_id, \__( 'Thank you for your request. We will confirm soon.', 'mpeti-booking-calendar' ) );

		\wp_mail( $email, $subject, $message, $this->headers() );
	}

	protected function build_message( int $appointment_id, string $intro ): string {
		$replacements = array(
			'{customer_name}'    => \get_post_meta( $appointment_id, 'customer_name', true ),
			'{appointment_date}' => \get_post_meta( $appointment_id, 'appointment_date', true ),
			'{appointment_time}' => \get_post_meta( $appointment_id, 'appointment_time', true ),
			'{service_name}'     => $this->get_post_title( \get_post_meta( $appointment_id, 'service_id', true ) ),
			'{staff_name}'       => $this->get_post_title( \get_post_meta( $appointment_id, 'staff_id', true ) ),
		);

		$message = $intro . "\n\n";
		foreach ( $replacements as $key => $value ) {
			$message .= \sprintf( "%s: %s\n", $key, $value );
		}
		return $message;
	}

	protected function headers(): array {
		return array( 'Content-Type: text/plain; charset=UTF-8' );
	}

	protected function get_post_title( $id ): string {
		$title = $id ? \get_the_title( $id ) : '';
		return $title ? $title : '';
	}
}


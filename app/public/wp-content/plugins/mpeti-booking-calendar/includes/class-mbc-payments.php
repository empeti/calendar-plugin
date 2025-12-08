<?php
namespace MBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Placeholder payments integration.
 */
class Payments {

	public function validate_stripe_payment( array $payload ): bool {
		// TODO: Implement Stripe signature verification.
		return true;
	}

	public function is_payment_required_for_service( int $service_id ): bool {
		// TODO: Implement logic based on service meta/settings.
		return false;
	}
}


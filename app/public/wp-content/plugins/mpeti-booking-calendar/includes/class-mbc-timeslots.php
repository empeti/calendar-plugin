<?php
namespace MBC;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Timeslots {

	protected function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mbc_timeslots';
	}

	public function maybe_create_table(): void {
		global $wpdb;
		$table   = $this->table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id INT NOT NULL AUTO_INCREMENT,
			weekday TINYINT NOT NULL,
			staff_id BIGINT NOT NULL DEFAULT 0,
			start_time VARCHAR(5) NOT NULL,
			end_time VARCHAR(5) NOT NULL,
			capacity INT NOT NULL DEFAULT 1,
			is_active TINYINT NOT NULL DEFAULT 1,
			PRIMARY KEY  (id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( $sql );
	}

	public function get_available_slots( string $date, ?int $staff_id = null, ?int $service_id = null ): array {
		global $wpdb;
		$weekday = (int) \gmdate( 'w', \strtotime( $date ) );
		$table   = $this->table();

		// If service_id is provided, filter to only staff members assigned to this service
		$staff_ids_for_service = array();
		if ( $service_id ) {
			$all_staff = \get_posts( array(
				'post_type'      => 'mbc_staff',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			) );

			foreach ( $all_staff as $staff_post_id ) {
				$staff_services = \get_post_meta( $staff_post_id, 'staff_services', true );
				if ( ! \is_array( $staff_services ) ) {
					$staff_services = array();
				}
				$staff_services = \array_map( 'intval', $staff_services );
				
				// Check if this staff member is assigned to the service
				if ( \in_array( (int) $service_id, $staff_services, true ) ) {
					$staff_ids_for_service[] = (int) $staff_post_id;
				}
			}

			// If no staff members are assigned to this service, return empty array
			if ( empty( $staff_ids_for_service ) ) {
				return array();
			}
		}

		$where = $wpdb->prepare( 'WHERE weekday = %d AND is_active = 1', $weekday );
		
		// If staff_id is provided, filter by that specific staff
		if ( $staff_id ) {
			$where .= $wpdb->prepare( ' AND (staff_id = %d OR staff_id = 0)', $staff_id );
		} elseif ( $service_id && ! empty( $staff_ids_for_service ) ) {
			// If service_id is provided but no specific staff, only check timeslots for staff assigned to the service
			$staff_ids_placeholders = \implode( ',', \array_fill( 0, \count( $staff_ids_for_service ), '%d' ) );
			$where .= $wpdb->prepare( " AND (staff_id IN ({$staff_ids_placeholders}) OR staff_id = 0)", ...$staff_ids_for_service );
		}

		$rules = $wpdb->get_results( "SELECT * FROM {$table} {$where}", ARRAY_A );

		$duration = $this->get_duration_for_service( $service_id );
		$slots    = array();

		foreach ( $rules as $rule ) {
			$rule_staff_id = (int) $rule['staff_id'];
			
			// If this is a general timeslot (staff_id = 0), we need to check if any staff assigned to the service can use it
			if ( $rule_staff_id === 0 && $service_id && ! empty( $staff_ids_for_service ) ) {
				// For general timeslots, check availability for each staff member assigned to the service
				foreach ( $staff_ids_for_service as $service_staff_id ) {
					// Skip if a specific staff_id was requested and this isn't it
					if ( $staff_id && $service_staff_id !== $staff_id ) {
						continue;
					}
					
					$start = \strtotime( "{$date} {$rule['start_time']}" );
					$end   = \strtotime( "{$date} {$rule['end_time']}" );
					for ( $time = $start; $time < $end; $time += $duration * MINUTE_IN_SECONDS ) {
						$time_str  = \gmdate( 'H:i', $time );
						$available = $this->is_slot_available( $date, $time_str, $service_staff_id, $service_id );
						$slots[]   = array(
							'time'      => $time_str,
							'available' => $available,
						);
					}
				}
			} else {
				// For specific staff timeslots, use the staff_id from the rule or the provided staff_id
				$slot_staff_id = $staff_id ?? $rule_staff_id;
				
				// Skip if service_id is provided and this staff is not assigned to the service
				if ( $service_id && $slot_staff_id > 0 && ! \in_array( $slot_staff_id, $staff_ids_for_service, true ) ) {
					continue;
				}
				
				$start = \strtotime( "{$date} {$rule['start_time']}" );
				$end   = \strtotime( "{$date} {$rule['end_time']}" );
				for ( $time = $start; $time < $end; $time += $duration * MINUTE_IN_SECONDS ) {
					$time_str  = \gmdate( 'H:i', $time );
					$available = $this->is_slot_available( $date, $time_str, $slot_staff_id, $service_id );
					$slots[]   = array(
						'time'      => $time_str,
						'available' => $available,
					);
				}
			}
		}

		return $slots;
	}

	public function is_slot_available( string $date, string $time, ?int $staff_id, ?int $service_id ): bool {
		$current  = $this->count_appointments( $date, $time, $staff_id, $service_id );
		$capacity = $this->get_capacity_for_slot( $date, $time, $staff_id );
		return $current < $capacity;
	}

	protected function count_appointments( string $date, string $time, ?int $staff_id, ?int $service_id ): int {
		// Note: service_id is not used for availability checking - availability is based on staff member only
		// service_id is only used for duration calculation in get_available_slots()
		$args = array(
			'post_type'      => 'mbc_appointment',
			'post_status'    => array( 'publish', 'pending', 'draft' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'appointment_date',
					'value' => $date,
				),
				array(
					'key'   => 'appointment_time',
					'value' => $time,
				),
			),
		);

		if ( $staff_id ) {
			$args['meta_query'][] = array(
				'key'   => 'staff_id',
				'value' => $staff_id,
			);
		}
		// Removed service_id filter - availability is based on staff member only
		// If a staff member has a booking at this time (regardless of service), the slot is unavailable

		$query = new \WP_Query( $args );
		return (int) $query->found_posts;
	}

	protected function get_capacity_for_slot( string $date, string $time, ?int $staff_id ): int {
		global $wpdb;
		$table   = $this->table();
		$weekday = (int) \gmdate( 'w', \strtotime( $date ) );

		$sql = $wpdb->prepare(
			"SELECT capacity FROM {$table} WHERE weekday = %d AND start_time <= %s AND end_time > %s AND is_active = 1 AND (staff_id = %d OR staff_id = 0) ORDER BY staff_id DESC LIMIT 1",
			$weekday,
			$time,
			$time,
			$staff_id ?: 0
		);

		$capacity = $wpdb->get_var( $sql );
		return $capacity ? (int) $capacity : 0;
	}

	protected function get_duration_for_service( ?int $service_id ): int {
		$settings = \get_option( Settings::OPTION_KEY, array() );
		$default  = isset( $settings['default_duration'] ) ? (int) $settings['default_duration'] : 60;

		if ( $service_id ) {
			$duration = \get_post_meta( $service_id, 'service_duration', true );
			if ( $duration ) {
				return (int) $duration;
			}
		}
		return $default > 0 ? $default : 60;
	}
}


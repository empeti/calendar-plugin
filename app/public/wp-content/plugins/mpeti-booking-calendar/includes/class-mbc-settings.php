<?php
namespace MBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	const OPTION_KEY = 'mpeti_booking_calendar_settings';

	public function register_settings(): void {
		\register_setting( 'mbc_settings', self::OPTION_KEY );

		\add_settings_section(
			'mbc_general',
			\__( 'General Settings', 'mpeti-booking-calendar' ),
			'__return_false',
			'mbc_settings'
		);

		\add_settings_section(
			'mbc_notifications',
			\__( 'Notifications', 'mpeti-booking-calendar' ),
			'__return_false',
			'mbc_settings'
		);

		\add_action( 'wp_ajax_mbc_save_timeslot', array( $this, 'ajax_save_timeslot' ) );
		\add_action( 'wp_ajax_mbc_delete_timeslot', array( $this, 'ajax_delete_timeslot' ) );
	}

	public function ajax_save_timeslot(): void {
		\check_ajax_referer( 'mbc_timeslot_nonce', 'nonce' );
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( \__( 'Permission denied', 'mpeti-booking-calendar' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'mbc_timeslots';

		$weekday   = isset( $_POST['weekday'] ) ? \intval( $_POST['weekday'] ) : 0;
		$staff_id  = isset( $_POST['staff_id'] ) ? \intval( $_POST['staff_id'] ) : 0;
		$start_time = isset( $_POST['start_time'] ) ? \sanitize_text_field( \wp_unslash( $_POST['start_time'] ) ) : '';
		$end_time   = isset( $_POST['end_time'] ) ? \sanitize_text_field( \wp_unslash( $_POST['end_time'] ) ) : '';
		$capacity   = isset( $_POST['capacity'] ) ? \intval( $_POST['capacity'] ) : 1;
		$is_active  = isset( $_POST['is_active'] ) ? \intval( $_POST['is_active'] ) : 1;

		if ( empty( $start_time ) || empty( $end_time ) ) {
			\wp_send_json_error( \__( 'Start and end times are required', 'mpeti-booking-calendar' ) );
		}

		// Ensure staff_id is provided (should be set from the staff member page)
		if ( ! $staff_id ) {
			\wp_send_json_error( \__( 'Staff ID is required', 'mpeti-booking-calendar' ) );
		}

		$result = $wpdb->insert(
			$table,
			array(
				'weekday'   => $weekday,
				'staff_id'  => $staff_id,
				'start_time' => $start_time,
				'end_time'   => $end_time,
				'capacity'   => $capacity,
				'is_active'  => $is_active,
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d' )
		);

		if ( false === $result ) {
			\wp_send_json_error( \__( 'Failed to save time slot', 'mpeti-booking-calendar' ) );
		}

		\wp_send_json_success( \__( 'Time slot saved', 'mpeti-booking-calendar' ) );
	}

	public function ajax_delete_timeslot(): void {
		\check_ajax_referer( 'mbc_timeslot_nonce', 'nonce' );
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( \__( 'Permission denied', 'mpeti-booking-calendar' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'mbc_timeslots';
		$id    = isset( $_POST['id'] ) ? \intval( $_POST['id'] ) : 0;

		if ( ! $id ) {
			\wp_send_json_error( \__( 'Invalid ID', 'mpeti-booking-calendar' ) );
		}

		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( false === $result ) {
			\wp_send_json_error( \__( 'Failed to delete time slot', 'mpeti-booking-calendar' ) );
		}

		\wp_send_json_success( \__( 'Time slot deleted', 'mpeti-booking-calendar' ) );
	}

	public function render_page(): void {
		$settings = \get_option( self::OPTION_KEY, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Booking Calendar (mpeti)', 'mpeti-booking-calendar' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				\settings_fields( 'mbc_settings' );
				\do_settings_sections( 'mbc_settings' );
				?>
				<h2><?php \esc_html_e( 'General', 'mpeti-booking-calendar' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="business_name"><?php \esc_html_e( 'Business Name', 'mpeti-booking-calendar' ); ?></label></th>
						<td><input type="text" class="regular-text" id="business_name" name="<?php echo \esc_attr( self::OPTION_KEY ); ?>[business_name]" value="<?php echo isset( $settings['business_name'] ) ? \esc_attr( $settings['business_name'] ) : ''; ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="timezone"><?php \esc_html_e( 'Timezone', 'mpeti-booking-calendar' ); ?></label></th>
						<td><input type="text" class="regular-text" id="timezone" name="<?php echo \esc_attr( self::OPTION_KEY ); ?>[timezone]" value="<?php echo isset( $settings['timezone'] ) ? \esc_attr( $settings['timezone'] ) : ''; ?>" placeholder="<?php \esc_attr_e( 'UTC', 'mpeti-booking-calendar' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="default_duration"><?php \esc_html_e( 'Default Duration (minutes)', 'mpeti-booking-calendar' ); ?></label></th>
						<td><input type="number" class="small-text" min="1" id="default_duration" name="<?php echo \esc_attr( self::OPTION_KEY ); ?>[default_duration]" value="<?php echo isset( $settings['default_duration'] ) ? \intval( $settings['default_duration'] ) : 60; ?>" /></td>
					</tr>
				</table>

				<h2><?php \esc_html_e( 'Notifications', 'mpeti-booking-calendar' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="admin_email"><?php \esc_html_e( 'Admin Email', 'mpeti-booking-calendar' ); ?></label></th>
						<td><input type="email" class="regular-text" id="admin_email" name="<?php echo \esc_attr( self::OPTION_KEY ); ?>[admin_email]" value="<?php echo isset( $settings['admin_email'] ) ? \esc_attr( $settings['admin_email'] ) : \get_option( 'admin_email' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php \esc_html_e( 'Enable Customer Emails', 'mpeti-booking-calendar' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo \esc_attr( self::OPTION_KEY ); ?>[enable_customer_emails]" value="1" <?php \checked( isset( $settings['enable_customer_emails'] ) ? $settings['enable_customer_emails'] : 0, 1 ); ?> />
								<?php \esc_html_e( 'Send confirmation emails to customers', 'mpeti-booking-calendar' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="description"><?php \esc_html_e( 'Time slots are now managed on individual staff member pages. Go to Staff → select a staff member → Time Slots meta box.', 'mpeti-booking-calendar' ); ?></p>

				<h2><?php \esc_html_e( 'Integrations', 'mpeti-booking-calendar' ); ?></h2>
				<p><?php \esc_html_e( 'Google Calendar placeholder. Configure in code.', 'mpeti-booking-calendar' ); ?></p>

				<h2><?php \esc_html_e( 'Payments', 'mpeti-booking-calendar' ); ?></h2>
				<p><?php \esc_html_e( 'Stripe placeholder. Extend class-mbc-payments.php.', 'mpeti-booking-calendar' ); ?></p>

				<?php \submit_button(); ?>
			</form>
		</div>
		<?php
	}

	protected function render_timeslots_section(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'mbc_timeslots';
		$timeslots = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY weekday, start_time", ARRAY_A );
		$staff = \get_posts( array( 'post_type' => 'mbc_staff', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
		$weekdays = array(
			0 => \__( 'Sunday', 'mpeti-booking-calendar' ),
			1 => \__( 'Monday', 'mpeti-booking-calendar' ),
			2 => \__( 'Tuesday', 'mpeti-booking-calendar' ),
			3 => \__( 'Wednesday', 'mpeti-booking-calendar' ),
			4 => \__( 'Thursday', 'mpeti-booking-calendar' ),
			5 => \__( 'Friday', 'mpeti-booking-calendar' ),
			6 => \__( 'Saturday', 'mpeti-booking-calendar' ),
		);
		?>
		<div id="mbc-timeslots-manager">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php \esc_html_e( 'Weekday', 'mpeti-booking-calendar' ); ?></th>
						<th><?php \esc_html_e( 'Staff', 'mpeti-booking-calendar' ); ?></th>
						<th><?php \esc_html_e( 'Start Time', 'mpeti-booking-calendar' ); ?></th>
						<th><?php \esc_html_e( 'End Time', 'mpeti-booking-calendar' ); ?></th>
						<th><?php \esc_html_e( 'Capacity', 'mpeti-booking-calendar' ); ?></th>
						<th><?php \esc_html_e( 'Status', 'mpeti-booking-calendar' ); ?></th>
						<th><?php \esc_html_e( 'Actions', 'mpeti-booking-calendar' ); ?></th>
					</tr>
				</thead>
				<tbody id="mbc-timeslots-list">
					<?php if ( empty( $timeslots ) ) : ?>
						<tr>
							<td colspan="7"><?php \esc_html_e( 'No time slots configured. Add one below.', 'mpeti-booking-calendar' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $timeslots as $slot ) : ?>
							<tr data-id="<?php echo \esc_attr( $slot['id'] ); ?>">
								<td><?php echo \esc_html( $weekdays[ (int) $slot['weekday'] ] ?? '' ); ?></td>
								<td>
									<?php
									if ( 0 === (int) $slot['staff_id'] ) {
										\esc_html_e( 'Any Staff', 'mpeti-booking-calendar' );
									} else {
										$staff_post = \get_post( $slot['staff_id'] );
										echo \esc_html( $staff_post ? $staff_post->post_title : 'N/A' );
									}
									?>
								</td>
								<td><?php echo \esc_html( $slot['start_time'] ); ?></td>
								<td><?php echo \esc_html( $slot['end_time'] ); ?></td>
								<td><?php echo \esc_html( $slot['capacity'] ); ?></td>
								<td>
									<?php if ( 1 === (int) $slot['is_active'] ) : ?>
										<span class="mbc-status-active"><?php \esc_html_e( 'Active', 'mpeti-booking-calendar' ); ?></span>
									<?php else : ?>
										<span class="mbc-status-inactive"><?php \esc_html_e( 'Inactive', 'mpeti-booking-calendar' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="button button-small mbc-edit-slot" data-id="<?php echo \esc_attr( $slot['id'] ); ?>"><?php \esc_html_e( 'Edit', 'mpeti-booking-calendar' ); ?></button>
									<button type="button" class="button button-small mbc-delete-slot" data-id="<?php echo \esc_attr( $slot['id'] ); ?>"><?php \esc_html_e( 'Delete', 'mpeti-booking-calendar' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h3><?php \esc_html_e( 'Add New Time Slot', 'mpeti-booking-calendar' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mbc-new-weekday"><?php \esc_html_e( 'Weekday', 'mpeti-booking-calendar' ); ?></label></th>
					<td>
						<select id="mbc-new-weekday" name="mbc_new_weekday">
							<?php foreach ( $weekdays as $num => $day ) : ?>
								<option value="<?php echo \esc_attr( $num ); ?>"><?php echo \esc_html( $day ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mbc-new-staff"><?php \esc_html_e( 'Staff', 'mpeti-booking-calendar' ); ?></label></th>
					<td>
						<select id="mbc-new-staff" name="mbc_new_staff">
							<option value="0"><?php \esc_html_e( 'Any Staff', 'mpeti-booking-calendar' ); ?></option>
							<?php foreach ( $staff as $member ) : ?>
								<option value="<?php echo \esc_attr( $member->ID ); ?>"><?php echo \esc_html( $member->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mbc-new-start"><?php \esc_html_e( 'Start Time', 'mpeti-booking-calendar' ); ?></label></th>
					<td><input type="time" id="mbc-new-start" name="mbc_new_start" value="09:00" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="mbc-new-end"><?php \esc_html_e( 'End Time', 'mpeti-booking-calendar' ); ?></label></th>
					<td><input type="time" id="mbc-new-end" name="mbc_new_end" value="17:00" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="mbc-new-capacity"><?php \esc_html_e( 'Capacity', 'mpeti-booking-calendar' ); ?></label></th>
					<td><input type="number" id="mbc-new-capacity" name="mbc_new_capacity" value="1" min="1" required /></td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Active', 'mpeti-booking-calendar' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="mbc-new-active" name="mbc_new_active" value="1" checked />
							<?php \esc_html_e( 'Enable this time slot', 'mpeti-booking-calendar' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<p>
				<button type="button" id="mbc-add-timeslot" class="button button-primary"><?php \esc_html_e( 'Add Time Slot', 'mpeti-booking-calendar' ); ?></button>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('#mbc-add-timeslot').on('click', function() {
				const data = {
					action: 'mbc_save_timeslot',
					nonce: '<?php echo \wp_create_nonce( 'mbc_timeslot_nonce' ); ?>',
					weekday: $('#mbc-new-weekday').val(),
					staff_id: $('#mbc-new-staff').val(),
					start_time: $('#mbc-new-start').val(),
					end_time: $('#mbc-new-end').val(),
					capacity: $('#mbc-new-capacity').val(),
					is_active: $('#mbc-new-active').is(':checked') ? 1 : 0
				};
				$.post(ajaxurl, data, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data || 'Error saving time slot');
					}
				});
			});

			$('.mbc-delete-slot').on('click', function() {
				if (!confirm('<?php \esc_html_e( 'Are you sure you want to delete this time slot?', 'mpeti-booking-calendar' ); ?>')) {
					return;
				}
				const id = $(this).data('id');
				$.post(ajaxurl, {
					action: 'mbc_delete_timeslot',
					nonce: '<?php echo \wp_create_nonce( 'mbc_timeslot_nonce' ); ?>',
					id: id
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data || 'Error deleting time slot');
					}
				});
			});
		});
		</script>
		<?php
	}
}


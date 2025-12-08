<?php
/**
 * Admin meta box for appointments.
 *
 * @var array $fields
 * @var array $services
 * @var array $staff
 */
?>
<table class="form-table">
	<tr>
		<th><label for="appointment_date"><?php esc_html_e( 'Date', 'mpeti-booking-calendar' ); ?></label></th>
		<td><input type="date" name="appointment_date" id="appointment_date" value="<?php echo esc_attr( $fields['appointment_date'] ); ?>" /></td>
	</tr>
	<tr>
		<th><label for="appointment_time"><?php esc_html_e( 'Time', 'mpeti-booking-calendar' ); ?></label></th>
		<td><input type="time" name="appointment_time" id="appointment_time" value="<?php echo esc_attr( $fields['appointment_time'] ); ?>" /></td>
	</tr>
	<tr>
		<th><label for="service_id"><?php esc_html_e( 'Service', 'mpeti-booking-calendar' ); ?></label></th>
		<td>
			<select name="service_id" id="service_id">
				<option value=""><?php esc_html_e( 'Select service', 'mpeti-booking-calendar' ); ?></option>
				<?php foreach ( $services as $service ) : ?>
					<option value="<?php echo esc_attr( $service->ID ); ?>" <?php selected( $fields['service_id'], $service->ID ); ?>><?php echo esc_html( $service->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="staff_id"><?php esc_html_e( 'Staff', 'mpeti-booking-calendar' ); ?></label></th>
		<td>
			<select name="staff_id" id="staff_id">
				<option value=""><?php esc_html_e( 'Select staff', 'mpeti-booking-calendar' ); ?></option>
				<?php foreach ( $staff as $member ) : ?>
					<option value="<?php echo esc_attr( $member->ID ); ?>" <?php selected( $fields['staff_id'], $member->ID ); ?>><?php echo esc_html( $member->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="appointment_status"><?php esc_html_e( 'Status', 'mpeti-booking-calendar' ); ?></label></th>
		<td>
			<select name="appointment_status" id="appointment_status">
				<option value="pending" <?php selected( $fields['appointment_status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'mpeti-booking-calendar' ); ?></option>
				<option value="confirmed" <?php selected( $fields['appointment_status'], 'confirmed' ); ?>><?php esc_html_e( 'Confirmed', 'mpeti-booking-calendar' ); ?></option>
				<option value="cancelled" <?php selected( $fields['appointment_status'], 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'mpeti-booking-calendar' ); ?></option>
			</select>
		</td>
	</tr>
	<tr>
		<th><label for="customer_name"><?php esc_html_e( 'Customer Name', 'mpeti-booking-calendar' ); ?></label></th>
		<td><input type="text" name="customer_name" id="customer_name" class="regular-text" value="<?php echo esc_attr( $fields['customer_name'] ); ?>" /></td>
	</tr>
	<tr>
		<th><label for="customer_email"><?php esc_html_e( 'Customer Email', 'mpeti-booking-calendar' ); ?></label></th>
		<td><input type="email" name="customer_email" id="customer_email" class="regular-text" value="<?php echo esc_attr( $fields['customer_email'] ); ?>" /></td>
	</tr>
	<tr>
		<th><label for="customer_phone"><?php esc_html_e( 'Customer Phone', 'mpeti-booking-calendar' ); ?></label></th>
		<td><input type="text" name="customer_phone" id="customer_phone" class="regular-text" value="<?php echo esc_attr( $fields['customer_phone'] ); ?>" /></td>
	</tr>
	<tr>
		<th><label for="notes"><?php esc_html_e( 'Notes', 'mpeti-booking-calendar' ); ?></label></th>
		<td><textarea name="notes" id="notes" class="large-text" rows="4"><?php echo esc_textarea( $fields['notes'] ); ?></textarea></td>
	</tr>
</table>


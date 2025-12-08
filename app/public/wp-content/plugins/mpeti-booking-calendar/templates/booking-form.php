<?php
/**
 * Frontend booking form scaffold.
 *
 * @var array $services
 * @var array $staff
 * @var int|null $service_id
 * @var int|null $staff_id
 */
?>
<div id="mbc-booking-form">
	<h3><?php esc_html_e( 'Book an appointment', 'mpeti-booking-calendar' ); ?></h3>

	<!-- Step 2: Staff Selection (shown after date selection) -->
	<div class="mbc-booking-step mbc-step-staff" style="display: none;">
		<h4><?php esc_html_e( 'Select Staff Member', 'mpeti-booking-calendar' ); ?></h4>
		<div id="mbc-available-staff-list" class="mbc-staff-list"></div>
		
		<!-- Step 3: Timeslots (shown after staff selection) -->
		<div class="mbc-time-slots-container" id="mbc-time-slots-container" style="display: none;">
			<h4 class="mbc-selected-date"></h4>
			<div class="mbc-time-slots-grid" id="mbc-time-slots-grid"></div>
			
			<!-- Selected Appointment Details (shown after timeslot selection) -->
			<div class="mbc-selected-appointment" id="mbc-selected-appointment" style="display: none;">
				<div class="mbc-selection-header">
					<strong><?php esc_html_e( 'Selected Appointment', 'mpeti-booking-calendar' ); ?>:</strong>
				</div>
				<div class="mbc-selection-details">
					<span class="mbc-selection-staff" id="mbc-selection-staff"></span>
					<span class="mbc-selection-date" id="mbc-selection-date"></span>
					<span class="mbc-selection-time" id="mbc-selection-time"></span>
				</div>
			</div>
		</div>
	</div>

	<!-- Step 4: Booking Form (shown after staff and timeslot selection) -->
	<form class="mbc-booking-form" style="display: none;">
		<input type="hidden" name="service_id" id="mbc-form-service-id" />
		<input type="hidden" name="staff_id" id="mbc-form-staff-id" />
		<input type="hidden" name="date" id="mbc-form-date" />
		<input type="hidden" name="time" id="mbc-form-time" />
		
		<p>
			<label><?php esc_html_e( 'Name', 'mpeti-booking-calendar' ); ?> <span class="required">*</span></label>
			<input type="text" name="name" required />
		</p>
		<p>
			<label><?php esc_html_e( 'Email', 'mpeti-booking-calendar' ); ?> <span class="required">*</span></label>
			<input type="email" name="email" required />
		</p>
		<p>
			<label><?php esc_html_e( 'Phone', 'mpeti-booking-calendar' ); ?></label>
			<input type="text" name="phone" />
		</p>
		<p>
			<label><?php esc_html_e( 'Notes', 'mpeti-booking-calendar' ); ?></label>
			<textarea name="notes" rows="3"></textarea>
		</p>
		<button type="submit" class="button"><?php esc_html_e( 'Submit', 'mpeti-booking-calendar' ); ?></button>
		<div class="mbc-form-message" style="display:none;"></div>
	</form>
</div>


<?php
/**
 * Frontend calendar scaffold with service selection.
 *
 * @var array    $available
 * @var array    $services Array of service post objects
 * @var int|null $staff_id
 * @var int|null $service_id
 */
$today = new \DateTime();
$current_month = $today->format( 'Y-m' );
$first_day = new \DateTime( $current_month . '-01' );
$last_day = new \DateTime( $first_day->format( 'Y-m-t' ) );
$start_date = clone $first_day;
$start_date->modify( 'monday this week' );
$end_date = clone $last_day;
$end_date->modify( 'sunday this week' );
?>
<!-- Step 1: Service Selection -->
<div class="mbc-booking-step mbc-step-service">
	<h3><?php esc_html_e( 'Select a Service', 'mpeti-booking-calendar' ); ?> <span class="mbc-required">*</span></h3>
	<div class="mbc-service-grid" id="mbc-service-grid">
		<?php foreach ( $services as $service ) : 
			$service_color = get_post_meta( $service->ID, 'service_color', true ) ?: '#4caf50';
			$service_price = get_post_meta( $service->ID, 'service_price', true );
			$service_duration = get_post_meta( $service->ID, 'service_duration', true );
			$service_image_id = get_post_meta( $service->ID, 'service_image', true );
			$service_image_url = $service_image_id ? wp_get_attachment_image_url( $service_image_id, 'large' ) : '';
			$is_selected = ( $service_id && (int) $service_id === (int) $service->ID );
			
			$card_style = '--service-color: ' . esc_attr( $service_color ) . ';';
			if ( $service_image_url ) {
				$card_style .= ' background-image: url(' . esc_url( $service_image_url ) . ');';
			}
		?>
			<div class="mbc-service-card<?php echo $is_selected ? ' selected' : ''; ?><?php echo $service_image_url ? ' has-background-image' : ''; ?>" 
				 data-service-id="<?php echo esc_attr( $service->ID ); ?>"
				 style="<?php echo $card_style; ?>">
				<div class="mbc-service-card-overlay"></div>
				<div class="mbc-service-card-content">
					<h4 class="mbc-service-card-title"><?php echo esc_html( $service->post_title ); ?></h4>
					<?php if ( $service_duration ) : ?>
						<div class="mbc-service-card-duration">
							<?php echo esc_html( $service_duration ); ?> <?php esc_html_e( 'minutes', 'mpeti-booking-calendar' ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<input type="hidden" name="service_id" id="mbc-service-select" value="<?php echo esc_attr( $service_id ); ?>" />
	<?php if ( $service_id ) : ?>
		<input type="hidden" name="service_id" value="<?php echo esc_attr( $service_id ); ?>" />
	<?php endif; ?>
</div>

<div id="mbc-booking-calendar">
	<div class="mbc-calendar-header">
		<h3><?php esc_html_e( 'Select a date', 'mpeti-booking-calendar' ); ?></h3>
		<div class="mbc-calendar-nav">
			<button type="button" class="mbc-prev-month" aria-label="<?php esc_attr_e( 'Previous month', 'mpeti-booking-calendar' ); ?>">‹</button>
			<span class="mbc-current-month"><?php echo esc_html( $first_day->format( 'F Y' ) ); ?></span>
			<button type="button" class="mbc-next-month" aria-label="<?php esc_attr_e( 'Next month', 'mpeti-booking-calendar' ); ?>">›</button>
		</div>
	</div>
	<div class="mbc-calendar-month">
		<div class="mbc-calendar-weekdays">
			<div><?php esc_html_e( 'Mon', 'mpeti-booking-calendar' ); ?></div>
			<div><?php esc_html_e( 'Tue', 'mpeti-booking-calendar' ); ?></div>
			<div><?php esc_html_e( 'Wed', 'mpeti-booking-calendar' ); ?></div>
			<div><?php esc_html_e( 'Thu', 'mpeti-booking-calendar' ); ?></div>
			<div><?php esc_html_e( 'Fri', 'mpeti-booking-calendar' ); ?></div>
			<div><?php esc_html_e( 'Sat', 'mpeti-booking-calendar' ); ?></div>
			<div><?php esc_html_e( 'Sun', 'mpeti-booking-calendar' ); ?></div>
		</div>
		<div class="mbc-calendar-grid">
			<?php
			$current = clone $start_date;
			while ( $current <= $end_date ) :
				$date_str = $current->format( 'Y-m-d' );
				$is_current_month = $current->format( 'Y-m' ) === $current_month;
				$is_today = $date_str === $today->format( 'Y-m-d' );
				$is_past = $current < $today;
				$classes = array( 'mbc-calendar-day' );
				if ( ! $is_current_month ) {
					$classes[] = 'mbc-other-month';
				}
				if ( $is_today ) {
					$classes[] = 'mbc-today';
				}
				if ( $is_past ) {
					$classes[] = 'mbc-past';
				}
				?>
				<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" 
					data-date="<?php echo esc_attr( $date_str ); ?>"
					<?php if ( ! $is_past && $is_current_month ) : ?>
						role="button" tabindex="0"
					<?php endif; ?>
				>
					<div class="mbc-day-content">
						<?php echo esc_html( $current->format( 'j' ) ); ?>
					</div>
					<span class="mbc-availability-dot" aria-hidden="true"></span>
				</div>
				<?php
				$current->modify( '+1 day' );
			endwhile;
			?>
		</div>
	</div>
</div>


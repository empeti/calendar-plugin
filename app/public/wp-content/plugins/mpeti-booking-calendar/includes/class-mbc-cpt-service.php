<?php
namespace MBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPT_Service {

	public function register(): void {
		$labels = array(
			'name'          => \__( 'Services', 'mpeti-booking-calendar' ),
			'singular_name' => \__( 'Service', 'mpeti-booking-calendar' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'exclude_from_search'=> true,
			'show_in_menu'       => false,
			'supports'           => array( 'title' ),
			'rewrite'            => false,
		);

		\register_post_type( 'mbc_service', $args );

		\add_action( 'add_meta_boxes_mbc_service', array( $this, 'register_meta_boxes' ) );
		\add_action( 'save_post_mbc_service', array( $this, 'save_meta_box' ), 10, 2 );
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_uploader' ) );
	}

	public function register_meta_boxes(): void {
		\add_meta_box(
			'mbc_service_details',
			\__( 'Service Details', 'mpeti-booking-calendar' ),
			array( $this, 'render_meta_box' ),
			'mbc_service',
			'normal',
			'high'
		);
	}

	public function enqueue_media_uploader( $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		global $post;
		if ( ! $post || 'mbc_service' !== $post->post_type ) {
			return;
		}
		\wp_enqueue_media();
		\wp_enqueue_script(
			'mbc-service-media',
			MBC_PLUGIN_URL . 'assets/js/service-media.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);
	}

	public function render_meta_box( $post ): void {
		\wp_nonce_field( 'mbc_service_meta_box', 'mbc_service_meta_box_nonce' );
		
		$image_id = \get_post_meta( $post->ID, 'service_image', true );
		$image_url = '';
		if ( $image_id ) {
			$image_url = \wp_get_attachment_image_url( $image_id, 'medium' );
		}
		?>
		<table class="form-table">
			<tr>
				<th><label for="service_image"><?php \esc_html_e( 'Service Image', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<div class="mbc-service-image-upload">
						<input type="hidden" id="service_image" name="service_image" value="<?php echo \esc_attr( $image_id ); ?>" />
						<div class="mbc-image-preview" style="margin-bottom: 10px;">
							<?php if ( $image_url ) : ?>
								<img src="<?php echo \esc_url( $image_url ); ?>" style="max-width: 300px; height: auto; border-radius: 8px; border: 1px solid #ddd;" />
							<?php endif; ?>
						</div>
						<button type="button" class="button mbc-upload-image-btn">
							<?php \esc_html_e( 'Upload Image', 'mpeti-booking-calendar' ); ?>
						</button>
						<button type="button" class="button mbc-remove-image-btn" style="<?php echo $image_id ? '' : 'display:none;'; ?>">
							<?php \esc_html_e( 'Remove Image', 'mpeti-booking-calendar' ); ?>
						</button>
						<p class="description"><?php \esc_html_e( 'Upload an image to use as the background for this service on the frontend.', 'mpeti-booking-calendar' ); ?></p>
					</div>
				</td>
			</tr>
			<tr>
				<th><label for="service_duration"><?php \esc_html_e( 'Duration (minutes)', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<input type="number" id="service_duration" name="service_duration" value="<?php echo \esc_attr( \get_post_meta( $post->ID, 'service_duration', true ) ); ?>" min="1" class="small-text" />
				</td>
			</tr>
			<tr>
				<th><label for="service_price"><?php \esc_html_e( 'Price', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<input type="number" id="service_price" name="service_price" value="<?php echo \esc_attr( \get_post_meta( $post->ID, 'service_price', true ) ); ?>" step="0.01" min="0" class="small-text" />
				</td>
			</tr>
			<tr>
				<th><label for="service_color"><?php \esc_html_e( 'Color', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<input type="color" id="service_color" name="service_color" value="<?php echo \esc_attr( \get_post_meta( $post->ID, 'service_color', true ) ?: '#4caf50' ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="service_active"><?php \esc_html_e( 'Active', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="service_active" name="service_active" value="1" <?php \checked( \get_post_meta( $post->ID, 'service_active', true ), '1' ); ?> />
						<?php \esc_html_e( 'This service is active', 'mpeti-booking-calendar' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_meta_box( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['mbc_service_meta_box_nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['mbc_service_meta_box_nonce'] ) ), 'mbc_service_meta_box' ) ) {
			return;
		}

		if ( \defined( 'DOING_AUTOSAVE' ) && \DOING_AUTOSAVE ) {
			return;
		}

		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'mbc_service' !== $post->post_type ) {
			return;
		}

		$fields = array( 'service_image', 'service_duration', 'service_price', 'service_color', 'service_active' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = 'service_active' === $field ? '1' : \sanitize_text_field( \wp_unslash( $_POST[ $field ] ) );
				\update_post_meta( $post_id, $field, $value );
			} else {
				\delete_post_meta( $post_id, $field );
			}
		}
	}
}


<?php
namespace MBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPT_Staff {

	public function register(): void {
		$labels = array(
			'name'          => \__( 'Staff', 'mpeti-booking-calendar' ),
			'singular_name' => \__( 'Staff Member', 'mpeti-booking-calendar' ),
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

		\register_post_type( 'mbc_staff', $args );

		\add_action( 'add_meta_boxes_mbc_staff', array( $this, 'register_meta_boxes' ) );
		\add_action( 'save_post_mbc_staff', array( $this, 'save_meta_box' ), 10, 2 );
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_uploader' ) );
		\add_filter( 'manage_mbc_staff_posts_columns', array( $this, 'add_columns' ) );
		\add_action( 'manage_mbc_staff_posts_custom_column', array( $this, 'render_columns' ), 10, 2 );
	}

	public function register_meta_boxes(): void {
		\add_meta_box(
			'mbc_staff_details',
			\__( 'Staff Details', 'mpeti-booking-calendar' ),
			array( $this, 'render_meta_box' ),
			'mbc_staff',
			'normal',
			'high'
		);
	}

	public function enqueue_media_uploader( $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		global $post;
		if ( ! $post || 'mbc_staff' !== $post->post_type ) {
			return;
		}
		\wp_enqueue_media();
		\wp_enqueue_script(
			'mbc-staff-media',
			MBC_PLUGIN_URL . 'assets/js/staff-media.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);
	}

	public function render_meta_box( $post ): void {
		\wp_nonce_field( 'mbc_staff_meta_box', 'mbc_staff_meta_box_nonce' );
		
		$photo_id = \get_post_meta( $post->ID, 'staff_photo', true );
		$photo_url = '';
		if ( $photo_id ) {
			$photo_url = \wp_get_attachment_image_url( $photo_id, 'thumbnail' );
		}
		?>
		<table class="form-table">
			<tr>
				<th><label for="staff_photo"><?php \esc_html_e( 'Photo', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<div class="mbc-staff-photo-upload">
						<input type="hidden" id="staff_photo" name="staff_photo" value="<?php echo \esc_attr( $photo_id ); ?>" />
						<div class="mbc-photo-preview" style="margin-bottom: 10px;">
							<?php if ( $photo_url ) : ?>
								<img src="<?php echo \esc_url( $photo_url ); ?>" style="max-width: 150px; height: auto; border-radius: 8px;" />
							<?php endif; ?>
						</div>
						<button type="button" class="button mbc-upload-photo-btn">
							<?php \esc_html_e( 'Upload Photo', 'mpeti-booking-calendar' ); ?>
						</button>
						<button type="button" class="button mbc-remove-photo-btn" style="<?php echo $photo_id ? '' : 'display:none;'; ?>">
							<?php \esc_html_e( 'Remove Photo', 'mpeti-booking-calendar' ); ?>
						</button>
					</div>
				</td>
			</tr>
			<tr>
				<th><label for="staff_email"><?php \esc_html_e( 'Email', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<input type="email" id="staff_email" name="staff_email" value="<?php echo \esc_attr( \get_post_meta( $post->ID, 'staff_email', true ) ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th><label for="staff_color"><?php \esc_html_e( 'Color', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<input type="color" id="staff_color" name="staff_color" value="<?php echo \esc_attr( \get_post_meta( $post->ID, 'staff_color', true ) ?: '#4caf50' ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="staff_active"><?php \esc_html_e( 'Active', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="staff_active" name="staff_active" value="1" <?php \checked( \get_post_meta( $post->ID, 'staff_active', true ), '1' ); ?> />
						<?php \esc_html_e( 'This staff member is active', 'mpeti-booking-calendar' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="staff_introduction"><?php \esc_html_e( 'Introduction', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<?php
					$intro_content = \get_post_meta( $post->ID, 'staff_introduction', true );
					\wp_editor(
						$intro_content,
						'staff_introduction',
						array(
							'textarea_name' => 'staff_introduction',
							'textarea_rows' => 5,
							'media_buttons' => false,
							'teeny'         => true,
						)
					);
					?>
					<p class="description"><?php \esc_html_e( 'A brief introduction about this staff member. This will be shown in a popup when users hover over the staff member on the frontend.', 'mpeti-booking-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label><?php \esc_html_e( 'Services', 'mpeti-booking-calendar' ); ?></label></th>
				<td>
					<?php
					$services = \get_posts( array(
						'post_type'      => 'mbc_service',
						'posts_per_page' => -1,
						'post_status'    => 'publish',
						'orderby'        => 'title',
						'order'          => 'ASC',
					) );
					$assigned_services = \get_post_meta( $post->ID, 'staff_services', true );
					if ( ! is_array( $assigned_services ) ) {
						$assigned_services = array();
					}
					// Normalize assigned services to integers for comparison
					$assigned_services = \array_map( 'intval', $assigned_services );
					
					if ( empty( $services ) ) {
						echo '<p>' . \esc_html__( 'No services available. Please create services first.', 'mpeti-booking-calendar' ) . '</p>';
					} else {
						echo '<div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
						foreach ( $services as $service ) {
							$checked = \in_array( (int) $service->ID, $assigned_services, true ) ? 'checked' : '';
							echo '<label style="display: block; margin-bottom: 8px;">';
							echo '<input type="checkbox" name="staff_services[]" value="' . \esc_attr( $service->ID ) . '" ' . $checked . ' /> ';
							echo \esc_html( $service->post_title );
							echo '</label>';
						}
						echo '</div>';
						echo '<p class="description">' . \esc_html__( 'Select which services this staff member can provide.', 'mpeti-booking-calendar' ) . '</p>';
					}
					?>
				</td>
			</tr>
		</table>
		<?php
	}

	public function save_meta_box( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['mbc_staff_meta_box_nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['mbc_staff_meta_box_nonce'] ) ), 'mbc_staff_meta_box' ) ) {
			return;
		}

		if ( \defined( 'DOING_AUTOSAVE' ) && \DOING_AUTOSAVE ) {
			return;
		}

		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'mbc_staff' !== $post->post_type ) {
			return;
		}

		$fields = array( 'staff_photo', 'staff_email', 'staff_color', 'staff_active' );
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = 'staff_active' === $field ? '1' : \sanitize_text_field( \wp_unslash( $_POST[ $field ] ) );
				\update_post_meta( $post_id, $field, $value );
			} else {
				\delete_post_meta( $post_id, $field );
			}
		}

		// Handle introduction (rich text)
		if ( isset( $_POST['staff_introduction'] ) ) {
			$intro = \wp_kses_post( \wp_unslash( $_POST['staff_introduction'] ) );
			\update_post_meta( $post_id, 'staff_introduction', $intro );
		} else {
			\delete_post_meta( $post_id, 'staff_introduction' );
		}

		// Handle services assignment
		if ( isset( $_POST['staff_services'] ) && \is_array( $_POST['staff_services'] ) ) {
			$services = \array_map( 'absint', \wp_unslash( $_POST['staff_services'] ) );
			\update_post_meta( $post_id, 'staff_services', $services );
		} else {
			\update_post_meta( $post_id, 'staff_services', array() );
		}
	}

	public function add_columns( array $columns ): array {
		$new_columns = array();
		$new_columns['cb'] = $columns['cb'];
		$new_columns['staff_photo'] = \__( 'Photo', 'mpeti-booking-calendar' );
		$new_columns['title'] = $columns['title'];
		unset( $columns['cb'], $columns['title'] );
		return \array_merge( $new_columns, $columns );
	}

	public function render_columns( string $column, int $post_id ): void {
		if ( 'staff_photo' === $column ) {
			$photo_id = \get_post_meta( $post_id, 'staff_photo', true );
			if ( $photo_id ) {
				$photo_url = \wp_get_attachment_image_url( $photo_id, 'thumbnail' );
				if ( $photo_url ) {
					echo '<img src="' . \esc_url( $photo_url ) . '" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" />';
				} else {
					echo '—';
				}
			} else {
				echo '—';
			}
		}
	}
}


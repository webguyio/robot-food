<?php

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

class Robot_Food_Meta {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
	}

	public static function add_meta_box( $post_type ) {
		$excluded = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' );
		if ( in_array( $post_type, $excluded, true ) ) {
			return;
		}
		add_meta_box(
			'robot_food',
			__( 'SEO', 'robot-food' ),
			array( __CLASS__, 'render' ),
			null,
			'normal',
			'high'
		);
	}

	public static function render( $post ) {
		wp_nonce_field( 'robot_food_meta', 'robot_food_nonce' );
		$title       = Robot_Food::get_post_meta( $post->ID, 'title' );
		$description = Robot_Food::get_post_meta( $post->ID, 'description' );
		$canonical   = Robot_Food::get_post_meta( $post->ID, 'canonical' );
		$og_title    = Robot_Food::get_post_meta( $post->ID, 'og_title' );
		$og_desc     = Robot_Food::get_post_meta( $post->ID, 'og_description' );
		$og_image    = Robot_Food::get_post_meta( $post->ID, 'og_image' );
		$noindex     = Robot_Food::get_post_meta( $post->ID, 'noindex', '0' );
		$schema_type = Robot_Food::get_post_meta( $post->ID, 'schema_type' );
		$og_image_url = $og_image ? wp_get_attachment_image_url( (int) $og_image, 'thumbnail' ) : '';
		?>
		<div class="rf-meta">
			<div class="rf-meta-field">
				<label for="rf_title"><?php esc_html_e( 'Title', 'robot-food' ); ?></label>
				<input
					type="text"
					id="rf_title"
					name="rf_title"
					value="<?php echo esc_attr( $title ); ?>"
					placeholder="<?php esc_attr_e( 'Leave blank to use default title', 'robot-food' ); ?>"
				>
			</div>
			<div class="rf-meta-field">
				<label for="rf_description"><?php esc_html_e( 'Description', 'robot-food' ); ?></label>
				<textarea
					id="rf_description"
					name="rf_description"
					rows="2"
					placeholder="<?php esc_attr_e( 'Leave blank to use excerpt or site default', 'robot-food' ); ?>"
				><?php echo esc_textarea( $description ); ?></textarea>
			</div>
			<div class="rf-meta-field">
				<label for="rf_canonical"><?php esc_html_e( 'Canonical URL', 'robot-food' ); ?></label>
				<input
					type="url"
					id="rf_canonical"
					name="rf_canonical"
					value="<?php echo esc_url( $canonical ); ?>"
					placeholder="<?php esc_attr_e( 'Leave blank to use the permalink', 'robot-food' ); ?>"
				>
			</div>
			<div class="rf-meta-row">
				<div class="rf-meta-field">
					<label for="rf_og_title"><?php esc_html_e( 'Social Title', 'robot-food' ); ?></label>
					<input
						type="text"
						id="rf_og_title"
						name="rf_og_title"
						value="<?php echo esc_attr( $og_title ); ?>"
						placeholder="<?php esc_attr_e( 'Leave blank to use title', 'robot-food' ); ?>"
					>
				</div>
				<div class="rf-meta-field">
					<label for="rf_og_description"><?php esc_html_e( 'Social Description', 'robot-food' ); ?></label>
					<input
						type="text"
						id="rf_og_description"
						name="rf_og_description"
						value="<?php echo esc_attr( $og_desc ); ?>"
						placeholder="<?php esc_attr_e( 'Leave blank to use description', 'robot-food' ); ?>"
					>
				</div>
			</div>
			<div class="rf-meta-field">
				<label><?php esc_html_e( 'Social Image', 'robot-food' ); ?></label>
				<div class="rf-image-picker">
					<input type="hidden" id="rf_og_image" name="rf_og_image" value="<?php echo esc_attr( $og_image ); ?>">
					<div class="rf-image-preview" id="rf_og_image_preview">
						<?php if ( $og_image_url ) : ?>
							<img src="<?php echo esc_url( $og_image_url ); ?>" alt="">
						<?php endif; ?>
					</div>
					<button type="button" class="button" id="rf_og_image_select"><?php esc_html_e( 'Select Image', 'robot-food' ); ?></button>
					<button type="button" class="button rf-image-remove<?php echo $og_image ? '' : ' hidden'; ?>" id="rf_og_image_remove"><?php esc_html_e( 'Remove', 'robot-food' ); ?></button>
				</div>
			</div>
			<div class="rf-meta-row">
				<div class="rf-meta-field">
					<label for="rf_schema_type"><?php esc_html_e( 'Schema Type', 'robot-food' ); ?></label>
					<select id="rf_schema_type" name="rf_schema_type">
						<option value="" <?php selected( $schema_type, '' ); ?>><?php esc_html_e( 'Auto', 'robot-food' ); ?></option>
						<option value="WebPage" <?php selected( $schema_type, 'WebPage' ); ?>>WebPage</option>
						<option value="Article" <?php selected( $schema_type, 'Article' ); ?>>Article</option>
						<option value="BlogPosting" <?php selected( $schema_type, 'BlogPosting' ); ?>>BlogPosting</option>
						<option value="NewsArticle" <?php selected( $schema_type, 'NewsArticle' ); ?>>NewsArticle</option>
						<option value="Product" <?php selected( $schema_type, 'Product' ); ?>>Product</option>
						<option value="FAQPage" <?php selected( $schema_type, 'FAQPage' ); ?>>FAQPage</option>
						<option value="HowTo" <?php selected( $schema_type, 'HowTo' ); ?>>HowTo</option>
						<option value="Event" <?php selected( $schema_type, 'Event' ); ?>>Event</option>
						<option value="LocalBusiness" <?php selected( $schema_type, 'LocalBusiness' ); ?>>LocalBusiness</option>
					</select>
				</div>
				<div class="rf-meta-field rf-noindex">
					<label for="rf_noindex">
						<input type="checkbox" id="rf_noindex" name="rf_noindex" value="1" <?php checked( $noindex, '1' ); ?>>
						<?php esc_html_e( 'Noindex', 'robot-food' ); ?>
					</label>
					<label for="rf_sitemap_exclude">
						<input type="checkbox" id="rf_sitemap_exclude" name="rf_sitemap_exclude" value="1" <?php checked( Robot_Food::get_post_meta( $post->ID, 'sitemap_exclude', '0' ), '1' ); ?>>
						<?php esc_html_e( 'Exclude from sitemap', 'robot-food' ); ?>
					</label>
					<label for="rf_llms_exclude">
						<input type="checkbox" id="rf_llms_exclude" name="rf_llms_exclude" value="1" <?php checked( Robot_Food::get_post_meta( $post->ID, 'llms_exclude', '0' ), '1' ); ?>>
						<?php esc_html_e( 'Exclude from llms.txt', 'robot-food' ); ?>
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( !isset( $_POST['robot_food_nonce'] ) || !wp_verify_nonce( sanitize_key( $_POST['robot_food_nonce'] ), 'robot_food_meta' ) ) {
			return;
		}
		if ( !current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$excluded = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' );
		if ( in_array( $post->post_type, $excluded, true ) ) {
			return;
		}
		$fields = array(
			'title'          => 'sanitize_text_field',
			'description'    => 'sanitize_textarea_field',
			'canonical'      => 'esc_url_raw',
			'og_title'       => 'sanitize_text_field',
			'og_description' => 'sanitize_text_field',
			'schema_type'    => 'sanitize_text_field',
		);
		$text_fields = array( 'title', 'og_title', 'og_description', 'schema_type' );
		foreach ( $text_fields as $field ) {
			$value = isset( $_POST[ 'rf_' . $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'rf_' . $field ] ) ) : '';
			update_post_meta( $post_id, '_robot_food_' . $field, $value );
		}
		$description = isset( $_POST['rf_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rf_description'] ) ) : '';
		update_post_meta( $post_id, '_robot_food_description', $description );
		$canonical = isset( $_POST['rf_canonical'] ) ? esc_url_raw( wp_unslash( $_POST['rf_canonical'] ) ) : '';
		update_post_meta( $post_id, '_robot_food_canonical', $canonical );
		$og_image = isset( $_POST['rf_og_image'] ) ? absint( $_POST['rf_og_image'] ) : 0;
		update_post_meta( $post_id, '_robot_food_og_image', $og_image );
		$noindex         = isset( $_POST['rf_noindex'] ) && '1' === $_POST['rf_noindex'] ? '1' : '0';
		$sitemap_exclude = isset( $_POST['rf_sitemap_exclude'] ) && '1' === $_POST['rf_sitemap_exclude'] ? '1' : '0';
		$llms_exclude    = isset( $_POST['rf_llms_exclude'] ) && '1' === $_POST['rf_llms_exclude'] ? '1' : '0';
		update_post_meta( $post_id, '_robot_food_noindex', $noindex );
		update_post_meta( $post_id, '_robot_food_sitemap_exclude', $sitemap_exclude );
		update_post_meta( $post_id, '_robot_food_llms_exclude', $llms_exclude );
	}
}
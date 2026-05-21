<?php

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

class Robot_Food_User {

	public static function init() {
		add_action( 'show_user_profile', array( __CLASS__, 'render' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save' ) );
	}

	public static function render( $user ) {
		$can_edit = current_user_can( 'edit_user', $user->ID );
		$is_own   = get_current_user_id() === $user->ID;
		if ( !$can_edit && !$is_own ) {
			return;
		}
		$readonly  = !$can_edit && !current_user_can( 'edit_posts' ) ? ' disabled' : '';
		$name      = get_user_meta( $user->ID, '_robot_food_user_name', true );
		$job_title = get_user_meta( $user->ID, '_robot_food_user_job_title', true );
		$email     = get_user_meta( $user->ID, '_robot_food_user_email', true );
		$phone     = get_user_meta( $user->ID, '_robot_food_user_phone', true );
		$url       = get_user_meta( $user->ID, '_robot_food_user_url', true );
		$socials   = get_user_meta( $user->ID, '_robot_food_user_socials', true );
		?>
		<h2><?php esc_html_e( 'Schema', 'robot-food' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Public information used for search optimization.', 'robot-food' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="robot_food_user_name"><?php esc_html_e( 'Name', 'robot-food' ); ?></label></th>
				<td>
					<input type="text" id="robot_food_user_name" name="robot_food_user_name" value="<?php echo esc_attr( $name ); ?>" class="regular-text"<?php echo esc_attr( $readonly ); ?> placeholder="<?php echo esc_attr( $user->display_name ); ?>">
					<p class="description"><?php esc_html_e( 'Defaults to display name if left blank.', 'robot-food' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="robot_food_user_job_title"><?php esc_html_e( 'Job Title', 'robot-food' ); ?></label></th>
				<td>
					<input type="text" id="robot_food_user_job_title" name="robot_food_user_job_title" value="<?php echo esc_attr( $job_title ); ?>" class="regular-text"<?php echo esc_attr( $readonly ); ?>>
				</td>
			</tr>
			<tr>
				<th><label for="robot_food_user_email"><?php esc_html_e( 'Email', 'robot-food' ); ?></label></th>
				<td>
					<input type="email" id="robot_food_user_email" name="robot_food_user_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text"<?php echo esc_attr( $readonly ); ?>>
				</td>
			</tr>
			<tr>
				<th><label for="robot_food_user_phone"><?php esc_html_e( 'Phone', 'robot-food' ); ?></label></th>
				<td>
					<input type="text" id="robot_food_user_phone" name="robot_food_user_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text"<?php echo esc_attr( $readonly ); ?>>
				</td>
			</tr>
			<tr>
				<th><label for="robot_food_user_url"><?php esc_html_e( 'Website', 'robot-food' ); ?></label></th>
				<td>
					<input type="url" id="robot_food_user_url" name="robot_food_user_url" value="<?php echo esc_url( $url ); ?>" class="regular-text"<?php echo esc_attr( $readonly ); ?>>
				</td>
			</tr>
			<tr>
				<th><label for="robot_food_user_socials"><?php esc_html_e( 'Social Profiles', 'robot-food' ); ?></label></th>
				<td>
					<textarea id="robot_food_user_socials" name="robot_food_user_socials" rows="4" class="large-text"<?php echo esc_attr( $readonly ); ?>><?php echo esc_textarea( $socials ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One URL per line.', 'robot-food' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save( $user_id ) {
		if ( !current_user_can( 'edit_user', $user_id ) ) {
			if ( get_current_user_id() !== $user_id || !current_user_can( 'edit_posts' ) ) {
				return;
			}
		}
		if ( !isset( $_POST['_wpnonce'] ) || !wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'update-user_' . $user_id ) ) {
			return;
		}
		$text_fields = array( 'name', 'job_title', 'phone' );
		foreach ( $text_fields as $field ) {
			$value = isset( $_POST[ 'robot_food_user_' . $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'robot_food_user_' . $field ] ) ) : '';
			update_user_meta( $user_id, '_robot_food_user_' . $field, $value );
		}
		$email = isset( $_POST['robot_food_user_email'] ) ? sanitize_email( wp_unslash( $_POST['robot_food_user_email'] ) ) : '';
		update_user_meta( $user_id, '_robot_food_user_email', $email );
		$url = isset( $_POST['robot_food_user_url'] ) ? esc_url_raw( wp_unslash( $_POST['robot_food_user_url'] ) ) : '';
		update_user_meta( $user_id, '_robot_food_user_url', $url );
		$socials = isset( $_POST['robot_food_user_socials'] ) ? sanitize_textarea_field( wp_unslash( $_POST['robot_food_user_socials'] ) ) : '';
		update_user_meta( $user_id, '_robot_food_user_socials', $socials );
	}
}
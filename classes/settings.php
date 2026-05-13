<?php

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

class Robot_Food_Settings {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice_search_discouraged' ) );
		add_action( 'wp_ajax_robot_food_dismiss_notice', array( __CLASS__, 'dismiss_notice' ) );
	}

	public static function add_page() {
		add_options_page(
			__( 'Robot Food', 'robot-food' ),
			__( 'SEO', 'robot-food' ),
			'manage_options',
			'robot-food',
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue( $hook ) {
		if ( 'settings_page_robot-food' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'robot-food', ROBOT_FOOD_URL . 'assets/admin.css', array(), ROBOT_FOOD_VER );
		wp_enqueue_script( 'robot-food', ROBOT_FOOD_URL . 'assets/admin.js', array(), ROBOT_FOOD_VER, true );
		wp_localize_script( 'robot-food', 'rfL10n', array(
			'selectImage' => __( 'Select Image', 'robot-food' ),
			'useImage'    => __( 'Use This Image', 'robot-food' ),
		) );
	}

	public static function register() {
		register_setting( 'robot_food', 'robot_food', array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$output = array();
		$text_fields = array(
			'homepage_title',
			'title_separator',
			'homepage_description',
			'default_description',
			'schema_org_name',
			'schema_socials',
			'noindex_custom_slugs',
			'sitemap_exclude_posts',
			'sitemap_exclude_terms',
			'llms_header',
			'llms_extra',
			'llms_exclude_posts',
		);
		foreach ( $text_fields as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) ? sanitize_textarea_field( wp_unslash( $input[ $field ] ) ) : '';
		}
		$allowed_schema_types   = array( 'Organization', 'Person' );
		$schema_type            = isset( $input['schema_type'] ) ? sanitize_text_field( wp_unslash( $input['schema_type'] ) ) : 'Organization';
		$output['schema_type']  = in_array( $schema_type, $allowed_schema_types, true ) ? $schema_type : 'Organization';
		$allowed_title_formats  = array( 'title-site', 'site-title' );
		$title_format           = isset( $input['title_format'] ) ? sanitize_text_field( wp_unslash( $input['title_format'] ) ) : 'title-site';
		$output['title_format'] = in_array( $title_format, $allowed_title_formats, true ) ? $title_format : 'title-site';
		$image_fields = array( 'default_og_image', 'schema_logo' );
		foreach ( $image_fields as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) ? absint( $input[ $field ] ) : 0;
		}
		$bool_fields = array(
			'sitemap_disable',
			'llms_disable',
			'htaccess_https',
			'htaccess_nowww',
			'noindex_404',
			'noindex_search',
			'noindex_login',
			'noindex_logout',
			'noindex_register',
			'noindex_category',
			'noindex_tag',
			'noindex_date',
			'noindex_archive',
			'noindex_author',
			'noindex_attachment',
			'noindex_feed',
			'noindex_pagination',
		);
		foreach ( $bool_fields as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) && '1' === $input[ $field ] ? '1' : '0';
		}
		$array_fields = array( 'sitemap_exclude_post_types' );
		foreach ( $array_fields as $field ) {
			if ( isset( $input[ $field ] ) && is_array( $input[ $field ] ) ) {
				$output[ $field ] = array_map( 'sanitize_key', $input[ $field ] );
			} else {
				$output[ $field ] = array();
			}
		}
		$output['robots_txt'] = isset( $input['robots_txt'] ) ? sanitize_textarea_field( wp_unslash( $input['robots_txt'] ) ) : '';
		$redirects = array();
		if ( isset( $input['htaccess_redirects'] ) && is_array( $input['htaccess_redirects'] ) ) {
			foreach ( $input['htaccess_redirects'] as $redirect ) {
				$from = isset( $redirect['from'] ) ? esc_url_raw( wp_unslash( $redirect['from'] ) ) : '';
				$to   = isset( $redirect['to'] ) ? esc_url_raw( wp_unslash( $redirect['to'] ) ) : '';
				if ( $from && $to ) {
					$redirects[] = array( 'from' => $from, 'to' => $to );
				}
			}
		}
		$output['htaccess_redirects'] = $redirects;
		self::write_htaccess_markers( $output );
		$tracking_fields = array(
			'ga4_id'            => '/^G-[A-Z0-9]+$/',
			'gtm_id'            => '/^GTM-[A-Z0-9]+$/',
			'gsc_verification'  => '/^[A-Za-z0-9_-]+$/',
			'bing_verification' => '/^[A-Za-z0-9]+$/',
			'meta_pixel_id'     => '/^[0-9]+$/',
			'clarity_id'        => '/^[a-z0-9]{10}$/',
			'hotjar_id'         => '/^[0-9]+$/',
			'pinterest_id'      => '/^[0-9]+$/',
			'tiktok_id'         => '/^[A-Za-z0-9]+$/',
			'x_pixel_id'        => '/^[A-Za-z0-9]+$/',
		);
		foreach ( $tracking_fields as $field => $pattern ) {
			$value = isset( $input[ $field ] ) ? sanitize_text_field( wp_unslash( $input[ $field ] ) ) : '';
			$output[ $field ] = ( $value && preg_match( $pattern, $value ) ) ? $value : '';
		}
		return $output;
	}

	public static function write_htaccess_markers( $options ) {
		$path = ABSPATH . '.htaccess';
		if ( !file_exists( $path ) ) {
			return;
		}
		$lines = array();
		if ( !empty( $options['htaccess_https'] ) && '1' === $options['htaccess_https'] ) {
			$lines[] = 'RewriteEngine On';
			$lines[] = 'RewriteCond %{HTTPS} off';
			$lines[] = 'RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]';
		}
		if ( !empty( $options['htaccess_nowww'] ) && '1' === $options['htaccess_nowww'] ) {
			$lines[] = 'RewriteEngine On';
			$lines[] = 'RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]';
			$lines[] = 'RewriteRule ^(.*)$ https://%1%{REQUEST_URI} [R=301,L]';
		}
		if ( !empty( $options['htaccess_redirects'] ) ) {
			foreach ( $options['htaccess_redirects'] as $redirect ) {
				if ( !empty( $redirect['from'] ) && !empty( $redirect['to'] ) ) {
					$from    = '/' . ltrim( wp_parse_url( $redirect['from'], PHP_URL_PATH ) ?? '', '/' );
					$to      = esc_url_raw( $redirect['to'] );
					$lines[] = 'Redirect 301 ' . $from . ' ' . $to;
				}
			}
		}
		insert_with_markers( $path, 'Robot Food', $lines );
	}

	public static function read_robots_txt() {
		$saved = Robot_Food::get_option( 'robots_txt', '' );
		if ( $saved !== '' ) {
			return $saved;
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- robots_txt is a core WordPress filter, not a custom hook defined by this plugin.
		return apply_filters( 'robots_txt', "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php", get_option( 'blog_public' ) );
	}

	public static function read_htaccess() {
		$path = ABSPATH . '.htaccess';
		if ( !file_exists( $path ) ) {
			return '';
		}
		global $wp_filesystem;
		WP_Filesystem();
		return $wp_filesystem->get_contents( $path );
	}

	public static function notice_search_discouraged() {
		if ( get_option( 'blog_public' ) ) {
			return;
		}
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_user_meta( get_current_user_id(), 'robot_food_dismiss_discouraged', true ) ) {
			return;
		}
		$url = admin_url( 'options-reading.php' );
		echo '<div class="notice notice-error is-dismissible" id="rf-notice-discouraged">';
		echo '<p>';
		printf(
			wp_kses(
				/* translators: %s: URL to Reading Settings page */
				__( '<strong>Robot Food:</strong> Search engines are discouraged from indexing this site. <a href="%s">Fix this in Reading Settings</a>.', 'robot-food' ),
				array(
					'strong' => array(),
					'a'      => array( 'href' => array() ),
				)
			),
			esc_url( $url )
		);
		echo '</p>';
		echo '</div>';
		echo '<script>
		document.addEventListener("DOMContentLoaded", function() {
			var notice = document.getElementById("rf-notice-discouraged");
			if (!notice) return;
			notice.addEventListener("click", function(e) {
				if (e.target.classList.contains("notice-dismiss")) {
					fetch("' . esc_url( admin_url( 'admin-ajax.php' ) ) . '", {
						method: "POST",
						headers: { "Content-Type": "application/x-www-form-urlencoded" },
						body: "action=robot_food_dismiss_notice&nonce=' . esc_js( wp_create_nonce( 'robot_food_dismiss_notice' ) ) . '"
					});
				}
			});
		});
		</script>';
	}

	public static function dismiss_notice() {
		check_ajax_referer( 'robot_food_dismiss_notice', 'nonce' );
		update_user_meta( get_current_user_id(), 'robot_food_dismiss_discouraged', '1' );
		wp_die();
	}

	public static function render() {
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( !get_option( 'blog_public' ) ) {
			$url = admin_url( 'options-reading.php' );
			echo '<div class="notice notice-error">';
			echo '<p>';
			printf(
				wp_kses(
					/* translators: %s: URL to Reading Settings page */
					__( '<strong>Search engines are currently discouraged from indexing this site.</strong> <a href="%s">Fix this in Reading Settings</a>.', 'robot-food' ),
					array(
						'strong' => array(),
						'a'      => array( 'href' => array() ),
					)
				),
				esc_url( $url )
			);
			echo '</p>';
			echo '</div>';
		}
		$options              = get_option( 'robot_food', array() );
		$post_types           = get_post_types( array( 'public' => true ), 'objects' );
		$excluded_pts         = isset( $options['sitemap_exclude_post_types'] ) ? (array) $options['sitemap_exclude_post_types'] : array();
		$robots_txt           = self::read_robots_txt();
		$htaccess             = self::read_htaccess();
		$htaccess_redirects   = isset( $options['htaccess_redirects'] ) && is_array( $options['htaccess_redirects'] ) ? $options['htaccess_redirects'] : array();
		$htaccess_redirects[] = array( 'from' => '', 'to' => '' );
		?>
		<div class="wrap" id="robot-food">
			<h1>
				<svg viewBox="0 0 1200 1200" width="48" height="48" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><radialGradient id="a" cx="50%" cy="45%" r="55%"><stop offset="0" stop-color="#2d1060"/><stop offset="1" stop-color="#0d0520"/></radialGradient><filter id="b"><feTurbulence baseFrequency=".75" numOctaves="4" result="noise" stitchTiles="stitch" type="fractalNoise"/><feColorMatrix in="noise" result="gray" type="saturate" values="0"/><feBlend in="SourceGraphic" in2="gray" mode="overlay" result="blended"/><feComposite in="blended" in2="SourceGraphic" operator="in"/></filter><clipPath id="c"><circle cx="600" cy="600" r="600"/></clipPath><circle cx="600" cy="600" fill="url(#a)" r="600"/><path clip-path="url(#c)" d="m0 0h1200v1200h-1200z" fill="url(#a)" filter="url(#b)" opacity=".18"/><g fill="none" stroke="#f5e800" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><g opacity=".5" transform="matrix(7 0 0 7 516 126)"><path d="m12 14-1 1"/><path d="m13.75 18.25-1.25 1.42"/><path d="m17.775 5.654a15.68 15.68 0 0 0 -12.121 12.12"/><path d="m18.8 9.3a1 1 0 0 0 2.1 7.7"/><path d="m21.964 20.732a1 1 0 0 1 -1.232 1.232l-18-5a1 1 0 0 1 -.695-1.232 19.68 19.68 0 0 1 13.695-13.695 1 1 0 0 1 1.232.695z"/></g><g opacity=".5" transform="matrix(7 0 0 7 853 321)"><path d="m12 16h-8a2 2 0 1 1 0-4h16a2 2 0 1 1 0 4h-4.25"/><path d="m5 12a2 2 0 0 1 -2-2 9 7 0 0 1 18 0 2 2 0 0 1 -2 2"/><path d="m5 16a2 2 0 0 0 -2 2 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 2 2 0 0 0 -2-2q0 0 0 0"/><path d="m6.67 12 6.13 4.6a2 2 0 0 0 2.8-.4l3.15-4.2"/></g><g opacity=".5" transform="matrix(7 0 0 7 853 711)"><path d="m16 13h-13"/><path d="m16 17h-13"/><path d="m7.2 7.9-3.388 2.5a2 2 0 0 0 -.812 1.61v7.99a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-8.654c0-2-2.44-6.026-6.44-8.026a1 1 0 0 0 -1.082.057l-3.078 2.223"/><circle cx="9" cy="7" r="2"/></g><g opacity=".5" transform="matrix(7 0 0 7 516 906)"><path d="m6 8 1.75 12.28a2 2 0 0 0 2 1.72h4.54a2 2 0 0 0 2-1.72l1.71-12.28"/><path d="m5 8h14"/><path d="m7 15a6.47 6.47 0 0 1 5 0 6.47 6.47 0 0 0 5 0"/><path d="m12 8 1-6h2"/></g><g opacity=".5" transform="matrix(7 0 0 7 179 711)"><path d="m18 8a2 2 0 0 0 0-4 2 2 0 0 0 -4 0 2 2 0 0 0 -4 0 2 2 0 0 0 -4 0 2 2 0 0 0 0 4"/><path d="m10 22-1-14"/><path d="m14 22 1-14"/><path d="m20 8c.5 0 .9.4.8 1l-2.6 12c-.1.5-.7 1-1.2 1h-10c-.6 0-1.1-.4-1.2-1l-2.6-12c-.1-.6.3-1 .8-1z"/></g><g opacity=".5" transform="matrix(7 0 0 7 179 321)"><path d="m7 11 4.08 10.35a1 1 0 0 0 1.84 0l4.08-10.35"/><path d="m17 7a5 5 0 0 0 -10 0"/><path d="m17 7a2 2 0 0 1 0 4h-10a2 2 0 0 1 0-4"/></g><g transform="matrix(25 0 0 25 300 265)"><path d="m12 8v-4h-4"/><rect height="12" rx="2" width="16" x="4" y="8"/><path d="m2 14h2"/><path d="m20 14h2"/><path d="m15 13v2"/><path d="m9 13v2"/></g></g></svg>
				<?php esc_html_e( 'Robot Food', 'robot-food' ); ?>
			</h1>
			<div id="rf-search-wrap">
				<label for="rf-search" class="screen-reader-text"><?php esc_html_e( 'Search settings', 'robot-food' ); ?></label>
				<input
					type="search"
					id="rf-search"
					placeholder="<?php esc_attr_e( 'Search settings...', 'robot-food' ); ?>"
					autocomplete="off"
				>
			</div>
			<form method="post" action="options.php">
				<?php settings_fields( 'robot_food' ); ?>

				<?php submit_button(); ?>

				<section class="rf-section" data-keywords="title separator format site name">
					<h2><?php esc_html_e( 'Title', 'robot-food' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr data-keywords="homepage title front page home">
							<th scope="row"><label for="rf_homepage_title"><?php esc_html_e( 'Homepage Title', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_homepage_title" name="robot_food[homepage_title]" value="<?php echo esc_attr( Robot_Food::get_option( 'homepage_title' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
								<p class="description"><?php esc_html_e( 'Overrides the default site name used as the homepage title.', 'robot-food' ); ?></p>
							</td>
						</tr>
						<tr data-keywords="title format order site name">
							<th scope="row"><label for="rf_title_format"><?php esc_html_e( 'Format', 'robot-food' ); ?></label></th>
							<td>
								<?php $sep = Robot_Food::get_option( 'title_separator', '|' ); ?>
								<select id="rf_title_format" name="robot_food[title_format]">
									<option value="title-site" <?php selected( Robot_Food::get_option( 'title_format', 'title-site' ), 'title-site' ); ?>><?php /* translators: %s: title separator character */ echo esc_html( sprintf( __( 'Page Title %s Site Name', 'robot-food' ), $sep ) ); ?></option>
									<option value="site-title" <?php selected( Robot_Food::get_option( 'title_format', 'title-site' ), 'site-title' ); ?>><?php /* translators: %s: title separator character */ echo esc_html( sprintf( __( 'Site Name %s Page Title', 'robot-food' ), $sep ) ); ?></option>
								</select>
							</td>
						</tr>
						<tr data-keywords="title separator divider pipe dash">
							<th scope="row"><label for="rf_title_separator"><?php esc_html_e( 'Separator', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_title_separator" name="robot_food[title_separator]" value="<?php echo esc_attr( Robot_Food::get_option( 'title_separator', '|' ) ); ?>" class="small-text">
								<p class="description"><?php esc_html_e( 'Popular options: | • — ← →', 'robot-food' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="rf-section" data-keywords="description meta default">
					<h2><?php esc_html_e( 'Description', 'robot-food' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr data-keywords="homepage description front page home">
							<th scope="row"><label for="rf_homepage_description"><?php esc_html_e( 'Homepage Description', 'robot-food' ); ?></label></th>
							<td>
								<textarea id="rf_homepage_description" name="robot_food[homepage_description]" rows="3" class="large-text"><?php echo esc_textarea( Robot_Food::get_option( 'homepage_description' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Used as the meta description for the homepage.', 'robot-food' ); ?></p>
							</td>
						</tr>
						<tr data-keywords="default description meta fallback">
							<th scope="row"><label for="rf_default_description"><?php esc_html_e( 'Default Description', 'robot-food' ); ?></label></th>
							<td>
								<textarea id="rf_default_description" name="robot_food[default_description]" rows="3" class="large-text"><?php echo esc_textarea( Robot_Food::get_option( 'default_description' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Used when a page has no description or excerpt.', 'robot-food' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="rf-section" data-keywords="social og open graph twitter image facebook x">
					<h2><?php esc_html_e( 'Social', 'robot-food' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr data-keywords="default social image og open graph facebook twitter x fallback">
							<th scope="row"><label for="rf_default_og_image_select"><?php esc_html_e( 'Default Social Image', 'robot-food' ); ?></label></th>
							<td>
								<?php
								$og_id  = Robot_Food::get_option( 'default_og_image' );
								$og_url = $og_id ? wp_get_attachment_image_url( (int) $og_id, 'thumbnail' ) : '';
								?>
								<div class="rf-image-picker">
									<input type="hidden" id="rf_default_og_image" name="robot_food[default_og_image]" value="<?php echo esc_attr( $og_id ); ?>">
									<div class="rf-image-preview" id="rf_default_og_image_preview">
										<?php if ( $og_url ) : ?>
											<img src="<?php echo esc_url( $og_url ); ?>" alt="">
										<?php endif; ?>
									</div>
									<button type="button" id="rf_default_og_image_select" class="button" data-target="rf_default_og_image"><?php esc_html_e( 'Select Image', 'robot-food' ); ?></button>
									<button type="button" class="button rf-image-remove<?php echo $og_id ? '' : ' hidden'; ?>" data-target="rf_default_og_image"><?php esc_html_e( 'Remove', 'robot-food' ); ?></button>
								</div>
								<p class="description"><?php esc_html_e( 'Fallback image for Open Graph and X Cards.', 'robot-food' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="rf-section" data-keywords="schema org organization person type logo social profiles sameAs">
					<h2><?php esc_html_e( 'Schema', 'robot-food' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr data-keywords="schema type organization person">
							<th scope="row"><label for="rf_schema_type"><?php esc_html_e( 'Site Type', 'robot-food' ); ?></label></th>
							<td>
								<select id="rf_schema_type" name="robot_food[schema_type]">
									<option value="Organization" <?php selected( Robot_Food::get_option( 'schema_type', 'Organization' ), 'Organization' ); ?>><?php esc_html_e( 'Organization', 'robot-food' ); ?></option>
									<option value="Person" <?php selected( Robot_Food::get_option( 'schema_type', 'Organization' ), 'Person' ); ?>><?php esc_html_e( 'Person', 'robot-food' ); ?></option>
								</select>
							</td>
						</tr>
						<tr data-keywords="schema organization name">
							<th scope="row"><label for="rf_schema_org_name"><?php esc_html_e( 'Organization / Person Name', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_schema_org_name" name="robot_food[schema_org_name]" value="<?php echo esc_attr( Robot_Food::get_option( 'schema_org_name' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
							</td>
						</tr>
						<tr data-keywords="schema logo image">
							<th scope="row"><label for="rf_schema_logo_select"><?php esc_html_e( 'Logo', 'robot-food' ); ?></label></th>
							<td>
								<?php
								$logo_id  = Robot_Food::get_option( 'schema_logo' );
								$logo_url = $logo_id ? wp_get_attachment_image_url( (int) $logo_id, 'thumbnail' ) : '';
								?>
								<div class="rf-image-picker">
									<input type="hidden" id="rf_schema_logo" name="robot_food[schema_logo]" value="<?php echo esc_attr( $logo_id ); ?>">
									<div class="rf-image-preview" id="rf_schema_logo_preview">
										<?php if ( $logo_url ) : ?>
											<img src="<?php echo esc_url( $logo_url ); ?>" alt="">
										<?php endif; ?>
									</div>
									<button type="button" id="rf_schema_logo_select" class="button" data-target="rf_schema_logo"><?php esc_html_e( 'Select Image', 'robot-food' ); ?></button>
									<button type="button" class="button rf-image-remove<?php echo $logo_id ? '' : ' hidden'; ?>" data-target="rf_schema_logo"><?php esc_html_e( 'Remove', 'robot-food' ); ?></button>
								</div>
							</td>
						</tr>
						<tr data-keywords="schema social profiles sameAs facebook twitter linkedin instagram">
							<th scope="row"><label for="rf_schema_socials"><?php esc_html_e( 'Social Profiles', 'robot-food' ); ?></label></th>
							<td>
								<textarea id="rf_schema_socials" name="robot_food[schema_socials]" rows="5" class="large-text" placeholder="https://x.com/example&#10;https://facebook.com/example"><?php echo esc_textarea( Robot_Food::get_option( 'schema_socials' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'One URL per line. Used for sameAs in schema output.', 'robot-food' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="rf-section" data-keywords="sitemap xml post types taxonomies exclude disable">
					<h2><?php esc_html_e( 'Sitemap', 'robot-food' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr data-keywords="disable sitemap xml">
							<th scope="row"><?php esc_html_e( 'sitemap.xml', 'robot-food' ); ?></th>
							<td>
								<label for="rf_sitemap_disable">
									<input type="checkbox" id="rf_sitemap_disable" name="robot_food[sitemap_disable]" value="1" <?php checked( Robot_Food::get_option( 'sitemap_disable', '0' ), '1' ); ?>>
									<?php esc_html_e( 'Disable sitemap.xml', 'robot-food' ); ?>
								</label>
								<p class="description">
									<?php
									printf(
										wp_kses(
											/* translators: %s: sitemap URL */
											__( 'Your sitemap.xml is at %s', 'robot-food' ),
											array( 'a' => array( 'href' => array(), 'target' => array() ) )
										),
										'<a href="' . esc_url( home_url( '/sitemap.xml' ) ) . '" target="_blank">' . esc_html( home_url( '/sitemap.xml' ) ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
						<tr data-keywords="exclude post types sitemap">
							<th scope="row"><?php esc_html_e( 'Exclude Post Types', 'robot-food' ); ?></th>
							<td>
								<?php foreach ( $post_types as $pt ) : ?>
									<label>
										<input
											type="checkbox"
											name="robot_food[sitemap_exclude_post_types][]"
											value="<?php echo esc_attr( $pt->name ); ?>"
											<?php checked( in_array( $pt->name, $excluded_pts, true ) ); ?>
										>
										<?php echo esc_html( $pt->labels->name ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>
						<tr data-keywords="exclude posts ids sitemap individual">
							<th scope="row"><label for="rf_sitemap_exclude_posts"><?php esc_html_e( 'Exclude Posts by ID', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_sitemap_exclude_posts" name="robot_food[sitemap_exclude_posts]" value="<?php echo esc_attr( Robot_Food::get_option( 'sitemap_exclude_posts' ) ); ?>" class="regular-text" placeholder="1,2,3">
								<p class="description"><?php esc_html_e( 'Comma-separated post IDs to exclude from the sitemap.', 'robot-food' ); ?></p>
							</td>
						</tr>
						<tr data-keywords="exclude terms categories tags ids sitemap individual">
							<th scope="row"><label for="rf_sitemap_exclude_terms"><?php esc_html_e( 'Exclude Terms by ID', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_sitemap_exclude_terms" name="robot_food[sitemap_exclude_terms]" value="<?php echo esc_attr( Robot_Food::get_option( 'sitemap_exclude_terms' ) ); ?>" class="regular-text" placeholder="1,2,3">
								<p class="description"><?php esc_html_e( 'Comma-separated term IDs (categories, tags, etc.) to exclude from the sitemap.', 'robot-food' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="rf-section" data-keywords="llms txt ai bots crawlers language models">
					<h2><?php esc_html_e( 'LLMs', 'robot-food' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr data-keywords="disable llms txt">
							<th scope="row"><?php esc_html_e( 'llms.txt', 'robot-food' ); ?></th>
							<td>
								<label for="rf_llms_disable">
									<input type="checkbox" id="rf_llms_disable" name="robot_food[llms_disable]" value="1" <?php checked( Robot_Food::get_option( 'llms_disable', '0' ), '1' ); ?>>
									<?php esc_html_e( 'Disable llms.txt', 'robot-food' ); ?>
								</label>
								<p class="description">
									<?php
									printf(
										wp_kses(
											/* translators: %s: llms.txt URL */
											__( 'Your llms.txt is at %s', 'robot-food' ),
											array( 'a' => array( 'href' => array(), 'target' => array() ) )
										),
										'<a href="' . esc_url( home_url( '/llms.txt' ) ) . '" target="_blank">' . esc_html( home_url( '/llms.txt' ) ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
						<tr data-keywords="llms header custom greeting ai">
							<th scope="row"><label for="rf_llms_header"><?php esc_html_e( 'Custom Header', 'robot-food' ); ?></label></th>
							<td>
								<textarea id="rf_llms_header" name="robot_food[llms_header]" rows="5" class="large-text"><?php echo esc_textarea( Robot_Food::get_option( 'llms_header' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Replaces the auto-generated header. Use Markdown. Leave blank to use the default.', 'robot-food' ); ?></p>
							</td>
						</tr>
						<tr data-keywords="llms extra additional content append">
							<th scope="row"><label for="rf_llms_extra"><?php esc_html_e( 'Additional Content', 'robot-food' ); ?></label></th>
							<td>
								<textarea id="rf_llms_extra" name="robot_food[llms_extra]" rows="5" class="large-text"><?php echo esc_textarea( Robot_Food::get_option( 'llms_extra' ) ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Appended after the auto-generated pages and posts list. Use Markdown.', 'robot-food' ); ?></p>
							</td>
						</tr>
						<tr data-keywords="llms exclude posts ids individual">
							<th scope="row"><label for="rf_llms_exclude_posts"><?php esc_html_e( 'Exclude Posts by ID', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_llms_exclude_posts" name="robot_food[llms_exclude_posts]" value="<?php echo esc_attr( Robot_Food::get_option( 'llms_exclude_posts' ) ); ?>" class="regular-text" placeholder="1,2,3">
								<p class="description"><?php esc_html_e( 'Comma-separated post IDs to exclude from llms.txt.', 'robot-food' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="rf-section" data-keywords="robots txt crawl disallow allow user agent">
					<h2><?php esc_html_e( 'Robots', 'robot-food' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr data-keywords="robots txt editor crawl disallow allow">
							<th scope="row"><label for="rf_robots_txt"><?php esc_html_e( 'robots.txt', 'robot-food' ); ?></label></th>
							<td>
								<textarea id="rf_robots_txt" name="robot_food[robots_txt]" rows="10" class="large-text code" placeholder="User-agent: *&#10;Disallow:"><?php echo esc_textarea( $robots_txt ); ?></textarea>
								<p class="description">
									<?php
									printf(
										wp_kses(
											/* translators: %s: robots.txt URL */
											__( 'Overrides the default WordPress robots.txt via filter. View at %s', 'robot-food' ),
											array( 'a' => array( 'href' => array(), 'target' => array() ) )
										),
										'<a href="' . esc_url( home_url( '/robots.txt' ) ) . '" target="_blank">' . esc_html( home_url( '/robots.txt' ) ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
						<tr data-keywords="noindex 404 search login logout register category tag archive author attachment feed pagination">
							<th scope="row"><?php esc_html_e( 'Noindex', 'robot-food' ); ?></th>
							<td>
								<?php
								$noindex_options = array(
									'noindex_404'        => __( '404 Pages', 'robot-food' ),
									'noindex_search'     => __( 'Search Results', 'robot-food' ),
									'noindex_login'      => __( 'Login Pages', 'robot-food' ),
									'noindex_logout'     => __( 'Logout Pages', 'robot-food' ),
									'noindex_register'   => __( 'Register Pages', 'robot-food' ),
									'noindex_category'   => __( 'Categories', 'robot-food' ),
									'noindex_tag'        => __( 'Tags', 'robot-food' ),
									'noindex_date'       => __( 'Date Archives', 'robot-food' ),
									'noindex_archive'    => __( 'Post Type Archives', 'robot-food' ),
									'noindex_author'     => __( 'Author Archives', 'robot-food' ),
									'noindex_attachment' => __( 'Attachments', 'robot-food' ),
									'noindex_feed'       => __( 'Feed', 'robot-food' ),
									'noindex_pagination' => __( 'Pagination (page 2+)', 'robot-food' ),
								);
								foreach ( $noindex_options as $key => $label ) :
								?>
									<label>
										<input type="checkbox" name="robot_food[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( Robot_Food::get_option( $key, '0' ), '1' ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
								<br>
								<label for="rf_noindex_custom_slugs"><?php esc_html_e( 'Also noindex these URL paths', 'robot-food' ); ?></label>
								<input type="text" id="rf_noindex_custom_slugs" name="robot_food[noindex_custom_slugs]" value="<?php echo esc_attr( Robot_Food::get_option( 'noindex_custom_slugs' ) ); ?>" class="large-text" placeholder="/cart, /checkout, /thank-you">
								<p class="description"><?php esc_html_e( 'Comma-separated URL paths. Prefix matching, so /cart also covers /cart/, /cart?session=123, etc.', 'robot-food' ); ?></p>
							</td>
						</tr>
					</table>
				</section>

				<section class="rf-section" data-keywords="htaccess apache https www redirects rewrite rules server">
					<h2><?php esc_html_e( 'HT Access', 'robot-food' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php if ( $htaccess ) : ?>
						<tr data-keywords="htaccess file contents view current">
							<th scope="row"><?php esc_html_e( 'Current .htaccess', 'robot-food' ); ?></th>
							<td>
								<textarea readonly rows="10" class="large-text code"><?php echo esc_textarea( $htaccess ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Read-only. Edit via FTP/SSH or use the options below.', 'robot-food' ); ?></p>
							</td>
						</tr>
						<?php endif; ?>
						<tr data-keywords="force https ssl redirect">
							<th scope="row"><?php esc_html_e( 'Force HTTPS', 'robot-food' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="robot_food[htaccess_https]" value="1" <?php checked( Robot_Food::get_option( 'htaccess_https', '0' ), '1' ); ?>>
									<?php esc_html_e( 'Redirect all HTTP traffic to HTTPS', 'robot-food' ); ?>
								</label>
							</td>
						</tr>
						<tr data-keywords="www non-www redirect domain">
							<th scope="row"><?php esc_html_e( 'Remove www', 'robot-food' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="robot_food[htaccess_nowww]" value="1" <?php checked( Robot_Food::get_option( 'htaccess_nowww', '0' ), '1' ); ?>>
									<?php esc_html_e( 'Redirect www to non-www', 'robot-food' ); ?>
								</label>
							</td>
						</tr>
						<tr data-keywords="301 redirects from to url">
							<th scope="row"><?php esc_html_e( '301 Redirects', 'robot-food' ); ?></th>
							<td>
								<div
									class="rf-redirects"
									data-from-label="<?php esc_attr_e( 'From URL', 'robot-food' ); ?>"
									data-to-label="<?php esc_attr_e( 'To URL', 'robot-food' ); ?>"
								>
									<?php foreach ( $htaccess_redirects as $i => $redirect ) : ?>
									<div class="rf-redirect-row">
										<input
											type="url"
											name="robot_food[htaccess_redirects][<?php echo (int) $i; ?>][from]"
											value="<?php echo esc_url( $redirect['from'] ); ?>"
											placeholder="<?php esc_attr_e( 'From URL', 'robot-food' ); ?>"
											class="regular-text"
										>
										<input
											type="url"
											name="robot_food[htaccess_redirects][<?php echo (int) $i; ?>][to]"
											value="<?php echo esc_url( $redirect['to'] ); ?>"
											placeholder="<?php esc_attr_e( 'To URL', 'robot-food' ); ?>"
											class="regular-text"
										>
									</div>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php esc_html_e( 'A new row will appear as you fill in each one.', 'robot-food' ); ?></p>
							</td>
						</tr>
					</table>
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: %s: URL to .htaccess documentation */
								__( 'Need to edit .htaccess directly? We recommend %s.', 'robot-food' ),
								array(
									'code' => array(),
									'a'    => array( 'href' => array(), 'target' => array() ),
								)
							),
							'<a href="https://wordpress.org/plugins/wp-htaccess-editor/" target="_blank">WP Htaccess Editor</a>'
						);
						?>
					</p>
				</section>

				<section class="rf-section" data-keywords="tracking verification analytics google analytics tag manager search console bing meta pixel clarity hotjar pinterest tiktok x twitter">
					<h2><?php esc_html_e( 'Tracking &amp; Verification', 'robot-food' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr data-keywords="google analytics ga4 measurement id">
							<th scope="row"><label for="rf_ga4_id"><?php esc_html_e( 'Google Analytics 4', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_ga4_id" name="robot_food[ga4_id]" value="<?php echo esc_attr( Robot_Food::get_option( 'ga4_id' ) ); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
							</td>
						</tr>
						<tr data-keywords="google tag manager gtm container id">
							<th scope="row"><label for="rf_gtm_id"><?php esc_html_e( 'Google Tag Manager', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_gtm_id" name="robot_food[gtm_id]" value="<?php echo esc_attr( Robot_Food::get_option( 'gtm_id' ) ); ?>" class="regular-text" placeholder="GTM-XXXXXXX">
							</td>
						</tr>
						<tr data-keywords="google search console verification meta tag">
							<th scope="row"><label for="rf_gsc_verification"><?php esc_html_e( 'Google Search Console', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_gsc_verification" name="robot_food[gsc_verification]" value="<?php echo esc_attr( Robot_Food::get_option( 'gsc_verification' ) ); ?>" class="regular-text" placeholder="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
								<p class="description"><?php esc_html_e( 'Enter only the content value from the meta tag verification code.', 'robot-food' ); ?></p>
							</td>
						</tr>
						<tr data-keywords="bing microsoft webmaster verification meta tag">
							<th scope="row"><label for="rf_bing_verification"><?php esc_html_e( 'Bing Webmaster', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_bing_verification" name="robot_food[bing_verification]" value="<?php echo esc_attr( Robot_Food::get_option( 'bing_verification' ) ); ?>" class="regular-text" placeholder="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
								<p class="description"><?php esc_html_e( 'Enter only the content value from the meta tag verification code.', 'robot-food' ); ?></p>
							</td>
						</tr>
						<tr data-keywords="facebook meta pixel id">
							<th scope="row"><label for="rf_meta_pixel_id"><?php esc_html_e( 'Meta Pixel', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_meta_pixel_id" name="robot_food[meta_pixel_id]" value="<?php echo esc_attr( Robot_Food::get_option( 'meta_pixel_id' ) ); ?>" class="regular-text" placeholder="XXXXXXXXXXXXXXXXXX">
							</td>
						</tr>
						<tr data-keywords="microsoft clarity project id">
							<th scope="row"><label for="rf_clarity_id"><?php esc_html_e( 'Microsoft Clarity', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_clarity_id" name="robot_food[clarity_id]" value="<?php echo esc_attr( Robot_Food::get_option( 'clarity_id' ) ); ?>" class="regular-text" placeholder="XXXXXXXXXX">
							</td>
						</tr>
						<tr data-keywords="hotjar site id">
							<th scope="row"><label for="rf_hotjar_id"><?php esc_html_e( 'Hotjar', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_hotjar_id" name="robot_food[hotjar_id]" value="<?php echo esc_attr( Robot_Food::get_option( 'hotjar_id' ) ); ?>" class="regular-text" placeholder="XXXXXXX">
							</td>
						</tr>
						<tr data-keywords="pinterest tag id">
							<th scope="row"><label for="rf_pinterest_id"><?php esc_html_e( 'Pinterest Tag', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_pinterest_id" name="robot_food[pinterest_id]" value="<?php echo esc_attr( Robot_Food::get_option( 'pinterest_id' ) ); ?>" class="regular-text" placeholder="XXXXXXXXXXXXXXXXX">
							</td>
						</tr>
						<tr data-keywords="tiktok pixel id">
							<th scope="row"><label for="rf_tiktok_id"><?php esc_html_e( 'TikTok Pixel', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_tiktok_id" name="robot_food[tiktok_id]" value="<?php echo esc_attr( Robot_Food::get_option( 'tiktok_id' ) ); ?>" class="regular-text" placeholder="XXXXXXXXXXXXXXXXXXXX">
							</td>
						</tr>
						<tr data-keywords="x twitter pixel id">
							<th scope="row"><label for="rf_x_pixel_id"><?php esc_html_e( 'X Pixel', 'robot-food' ); ?></label></th>
							<td>
								<input type="text" id="rf_x_pixel_id" name="robot_food[x_pixel_id]" value="<?php echo esc_attr( Robot_Food::get_option( 'x_pixel_id' ) ); ?>" class="regular-text" placeholder="XXXXX">
							</td>
						</tr>
					</table>
					<p class="description">
						<?php
						printf(
							wp_kses(
								/* translators: %s: WPCode plugin URL */
								__( 'Need to add custom scripts? We recommend %s.', 'robot-food' ),
								array( 'a' => array( 'href' => array(), 'target' => array() ) )
							),
							'<a href="https://wordpress.org/plugins/insert-headers-and-footers/" target="_blank">WPCode</a>'
						);
						?>
					</p>
				</section>

				<?php submit_button(); ?>

				<section class="rf-section recommendations" data-keywords="recommendations speed social sharing rss image primary category like button title case">
					<h2><?php esc_html_e( 'Recommendations', 'robot-food' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<td colspan="2">
								<p class="description"><?php esc_html_e( 'A few free plugins that pair well with Robot Food.', 'robot-food' ); ?></p>
								<ul class="rf-recommendations">
									<li><a href="https://wordpress.org/plugins/snappy/" target="_blank">Snappy</a> (<?php esc_html_e( 'speed optimization', 'robot-food' ); ?>)</li>
									<li><a href="https://wordpress.org/plugins/simpleshare/" target="_blank">SimpleShare</a> (<?php esc_html_e( 'social sharing and auto-posting', 'robot-food' ); ?>)</li>
									<li><a href="https://wordpress.org/plugins/rss-image/" target="_blank">RSS Image</a> (<?php esc_html_e( 'adds featured images to RSS feeds', 'robot-food' ); ?>)</li>
									<li><a href="https://wordpress.org/plugins/primary-cat/" target="_blank">Primary Cat</a> (<?php esc_html_e( 'set a primary category per post', 'robot-food' ); ?>)</li>
									<li><a href="https://wordpress.org/plugins/love-button/" target="_blank">Love Button</a> (<?php esc_html_e( 'like button for posts', 'robot-food' ); ?>)</li>
									<li><a href="https://wordpress.org/plugins/auto-title-case/" target="_blank">Auto Title Case</a> (<?php esc_html_e( 'automatically formats post titles in title case', 'robot-food' ); ?>)</li>
								</ul>
							</td>
						</tr>
					</table>
				</section>
			</form>
		</div>
		<?php
	}
}
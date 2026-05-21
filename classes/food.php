<?php

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

class Robot_Food {

	public static function init() {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ) );
		add_filter( 'post_link_category', array( __CLASS__, 'filter_post_link_category' ), 10, 3 );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots_noindex' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'filter_robots_txt' ), 10, 2 );
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_action( 'init', array( __CLASS__, 'register_post_meta' ) );
		add_action( 'wp_head', array( __CLASS__, 'output_head' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'output_tracking_head' ), 2 );
		add_action( 'wp_head', array( __CLASS__, 'output_pagination_links' ), 3 );
		add_action( 'wp_head', array( __CLASS__, 'output_author_schema' ), 4 );
		add_action( 'wp_body_open', array( __CLASS__, 'output_tracking_body' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracking_scripts' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_sitemap_xml' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_llms_txt' ) );
		add_action( 'rss2_item', array( __CLASS__, 'output_rss_image' ) );
		Robot_Food_Meta::init();
		Robot_Food_User::init();
		Robot_Food_Settings::init();
	}

	public static function get_option( $key, $default = '' ) {
		static $options = null;
		if ( null === $options ) {
			$options = get_option( 'robot_food', array() );
		}
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	public static function get_post_meta( $post_id, $key, $default = '' ) {
		$value = get_post_meta( $post_id, '_robot_food_' . $key, true );
		return ( $value !== '' && $value !== false ) ? $value : $default;
	}

	public static function current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}

	public static function get_title() {
		if ( is_singular() ) {
			$post_id = get_the_ID();
			$title   = self::get_post_meta( $post_id, 'title' );
			if ( $title ) {
				return $title;
			}
		}
		return '';
	}

	public static function get_description() {
		if ( is_singular() ) {
			$post_id = get_the_ID();
			$desc    = self::get_post_meta( $post_id, 'description' );
			if ( $desc ) {
				return $desc;
			}
			if ( has_excerpt( $post_id ) ) {
				return wp_strip_all_tags( get_the_excerpt( $post_id ) );
			}
		}
		if ( is_home() || is_front_page() || is_front_page() && is_home() ) {
			$desc = self::get_option( 'homepage_description' );
			if ( $desc ) {
				return $desc;
			}
			$desc = self::get_option( 'default_description' );
			if ( $desc ) {
				return $desc;
			}
			return get_bloginfo( 'description' );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$desc = term_description();
			if ( $desc ) {
				return wp_strip_all_tags( $desc );
			}
		}
		return self::get_option( 'default_description' );
	}

	public static function get_canonical() {
		if ( is_singular() ) {
			$post_id   = get_the_ID();
			$canonical = self::get_post_meta( $post_id, 'canonical' );
			if ( $canonical ) {
				return esc_url( $canonical );
			}
			return get_permalink( $post_id );
		}
		if ( is_home() || is_front_page() ) {
			return home_url( '/' );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			return get_term_link( get_queried_object() );
		}
		if ( is_author() ) {
			return get_author_posts_url( get_queried_object_id() );
		}
		if ( is_archive() ) {
			return get_post_type_archive_link( get_post_type() );
		}
		return self::current_url();
	}

	public static function get_og_image() {
		if ( is_singular() ) {
			$post_id  = get_the_ID();
			$image_id = self::get_post_meta( $post_id, 'og_image' );
			if ( $image_id ) {
				$src = wp_get_attachment_image_url( (int) $image_id, 'large' );
				if ( $src ) {
					return $src;
				}
			}
			if ( has_post_thumbnail( $post_id ) ) {
				return get_the_post_thumbnail_url( $post_id, 'large' );
			}
		}
		$default_image_id = self::get_option( 'default_og_image' );
		if ( $default_image_id ) {
			$src = wp_get_attachment_image_url( (int) $default_image_id, 'large' );
			if ( $src ) {
				return $src;
			}
		}
		return '';
	}

	public static function register_post_meta() {
		register_post_meta( 'post', '_robot_food_primary_cat', array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'integer',
			'auth_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
		) );
	}

	public static function filter_post_link_category( $cat, $cats, $post ) {
		$primary_cat_id = (int) self::get_post_meta( $post->ID, 'primary_cat', 0 );
		if ( $primary_cat_id ) {
			$term = get_term( $primary_cat_id, 'category' );
			if ( $term && !is_wp_error( $term ) ) {
				return $term;
			}
		}
		return $cat;
	}

	public static function output_author_schema() {
		$user_id = 0;
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post && isset( $post->post_author ) ) {
				$user_id = (int) $post->post_author;
			}
		} elseif ( is_author() ) {
			$author = get_queried_object();
			if ( $author && isset( $author->ID ) ) {
				$user_id = (int) $author->ID;
			}
		}
		if ( !$user_id ) {
			return;
		}
		$name     = get_user_meta( $user_id, '_robot_food_user_name', true );
		if ( !$name ) {
			$name = get_the_author_meta( 'display_name', $user_id );
		}
		if ( !$name ) {
			return;
		}
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'name'     => $name,
		);
		$job_title = get_user_meta( $user_id, '_robot_food_user_job_title', true );
		if ( $job_title ) {
			$schema['jobTitle'] = $job_title;
		}
		$email = get_user_meta( $user_id, '_robot_food_user_email', true );
		if ( $email ) {
			$schema['email'] = $email;
		}
		$phone = get_user_meta( $user_id, '_robot_food_user_phone', true );
		if ( $phone ) {
			$schema['telephone'] = $phone;
		}
		$url = get_user_meta( $user_id, '_robot_food_user_url', true );
		if ( $url ) {
			$schema['url'] = $url;
		}
		$socials = get_user_meta( $user_id, '_robot_food_user_socials', true );
		if ( $socials ) {
			$same_as = array_values( array_filter( array_map( 'esc_url_raw', array_map( 'trim', explode( "\n", $socials ) ) ) ) );
			if ( !empty( $same_as ) ) {
				$schema['sameAs'] = $same_as;
			}
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
	}

	public static function output_rss_image() {
		$thumbnail_id = null;
		$thumbnail    = null;
		if ( has_post_thumbnail() ) {
			$thumbnail_id = get_post_thumbnail_id();
			$thumbnail    = wp_get_attachment_image_src( $thumbnail_id, 'full' );
		}
		if ( !$thumbnail ) {
			preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', get_the_content(), $matches );
			if ( !empty( $matches[1] ) ) {
				$src           = preg_replace( '/-\d+x\d+(\.[a-z]+)$/', '$1', $matches[1] );
				$attachment_id = attachment_url_to_postid( $src );
				if ( $attachment_id ) {
					$thumbnail_id = $attachment_id;
					$thumbnail    = wp_get_attachment_image_src( $attachment_id, 'full' );
				}
			}
		}
		if ( !$thumbnail ) {
			$icon_id = get_option( 'site_icon' );
			if ( $icon_id ) {
				$thumbnail_id = $icon_id;
				$thumbnail    = wp_get_attachment_image_src( $icon_id, 'full' );
			}
		}
		if ( $thumbnail ) {
			$mime_type = get_post_mime_type( $thumbnail_id );
			echo '<media:content xmlns:media="http://search.yahoo.com/mrss/" medium="image" type="' . esc_attr( $mime_type ) . '" url="' . esc_url( $thumbnail[0] ) . '" width="' . esc_attr( $thumbnail[1] ) . '" height="' . esc_attr( $thumbnail[2] ) . '" />';
		}
	}

	public static function filter_document_title( $title ) {
		$custom = self::get_title();
		if ( $custom ) {
			return $custom;
		}
		$sep    = self::get_option( 'title_separator', '|' );
		$site   = get_bloginfo( 'name' );
		$format = self::get_option( 'title_format', 'title-site' );
		if ( is_home() || is_front_page() || is_front_page() && is_home() ) {
			$homepage_title = self::get_option( 'homepage_title' );
			return $homepage_title ? $homepage_title : $site;
		}
		if ( is_singular() ) {
			$base = get_the_title( get_the_ID() );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$base = single_term_title( '', false );
		} elseif ( is_author() ) {
			$base = get_the_author_meta( 'display_name', get_queried_object_id() );
		} elseif ( is_archive() ) {
			$base = post_type_archive_title( '', false );
		} elseif ( is_search() ) {
			$base = get_search_query();
		} else {
			$base = get_bloginfo( 'name' );
		}
		if ( 'site-title' === $format ) {
			return $site . ' ' . $sep . ' ' . $base;
		}
		return $base . ' ' . $sep . ' ' . $site;
	}

	public static function output_head() {
		$description = self::get_description();
		$canonical   = self::get_canonical();
		$og_image    = self::get_og_image();
		$site_name   = get_bloginfo( 'name' );
		$page_title  = wp_get_document_title();
		if ( is_singular() ) {
			$post_id    = get_the_ID();
			$og_title   = self::get_post_meta( $post_id, 'og_title', $page_title );
			$og_desc    = self::get_post_meta( $post_id, 'og_description', $description );
		} else {
			$og_title = $page_title;
			$og_desc  = $description;
		}
		$og_type = ( is_singular( 'post' ) ) ? 'article' : 'website';
		echo "\n";
		if ( $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		}
		if ( $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}
		echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
		if ( $og_desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $og_desc ) . '">' . "\n";
		}
		echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
		if ( $og_image ) {
			echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
			echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '">' . "\n";
		} else {
			echo '<meta name="twitter:card" content="summary">' . "\n";
		}
		self::output_schema();
	}

	public static function output_tracking_head() {
		$gsc = self::get_option( 'gsc_verification' );
		if ( $gsc && preg_match( '/^[A-Za-z0-9_-]+$/', $gsc ) ) {
			echo '<meta name="google-site-verification" content="' . esc_attr( $gsc ) . '">' . "\n";
		}
		$bing = self::get_option( 'bing_verification' );
		if ( $bing && preg_match( '/^[A-Za-z0-9]+$/', $bing ) ) {
			echo '<meta name="msvalidate.01" content="' . esc_attr( $bing ) . '">' . "\n";
		}
	}

	public static function enqueue_tracking_scripts() {
		$ga4_id = self::get_option( 'ga4_id' );
		if ( $ga4_id && preg_match( '/^G-[A-Z0-9]+$/', $ga4_id ) ) {
			wp_enqueue_script( 'robot-food-ga4', 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga4_id ), array(), ROBOT_FOOD_VER, false );
			wp_script_add_data( 'robot-food-ga4', 'async', true );
			wp_add_inline_script( 'robot-food-ga4', 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","' . esc_js( $ga4_id ) . '");' );
		}
		$gtm_id = self::get_option( 'gtm_id' );
		if ( $gtm_id && preg_match( '/^GTM-[A-Z0-9]+$/', $gtm_id ) ) {
			wp_register_script( 'robot-food-gtm', '', array(), ROBOT_FOOD_VER, false );
			wp_enqueue_script( 'robot-food-gtm' );
			wp_add_inline_script( 'robot-food-gtm', '(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({"gtm.start":new Date().getTime(),event:"gtm.js"});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!="dataLayer"?"&l="+l:"";j.async=true;j.src="https://www.googletagmanager.com/gtm.js?id="+i+dl;f.parentNode.insertBefore(j,f);})(window,document,"script","dataLayer","' . esc_js( $gtm_id ) . '");' );
		}
		$meta_pixel = self::get_option( 'meta_pixel_id' );
		if ( $meta_pixel && preg_match( '/^[0-9]+$/', $meta_pixel ) ) {
			wp_register_script( 'robot-food-meta-pixel', '', array(), ROBOT_FOOD_VER, false );
			wp_enqueue_script( 'robot-food-meta-pixel' );
			wp_add_inline_script( 'robot-food-meta-pixel', '!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,"script","https://connect.facebook.net/en_US/fbevents.js");fbq("init","' . esc_js( $meta_pixel ) . '");fbq("track","PageView");' );
		}
		$clarity = self::get_option( 'clarity_id' );
		if ( $clarity && preg_match( '/^[a-z0-9]{10}$/', $clarity ) ) {
			wp_register_script( 'robot-food-clarity', '', array(), ROBOT_FOOD_VER, false );
			wp_enqueue_script( 'robot-food-clarity' );
			wp_add_inline_script( 'robot-food-clarity', '(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y)})(window,document,"clarity","script","' . esc_js( $clarity ) . '");' );
		}
		$hotjar = self::get_option( 'hotjar_id' );
		if ( $hotjar && preg_match( '/^[0-9]+$/', $hotjar ) ) {
			wp_register_script( 'robot-food-hotjar', '', array(), ROBOT_FOOD_VER, false );
			wp_enqueue_script( 'robot-food-hotjar' );
			wp_add_inline_script( 'robot-food-hotjar', '(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};h._hjSettings={hjid:' . esc_js( $hotjar ) . ',hjsv:6};a=o.getElementsByTagName("head")[0];r=o.createElement("script");r.async=1;r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;a.appendChild(r)})(window,document,"https://static.hotjar.com/c/hotjar-",".js?sv=");' );
		}
		$pinterest = self::get_option( 'pinterest_id' );
		if ( $pinterest && preg_match( '/^[0-9]+$/', $pinterest ) ) {
			wp_register_script( 'robot-food-pinterest', '', array(), ROBOT_FOOD_VER, false );
			wp_enqueue_script( 'robot-food-pinterest' );
			wp_add_inline_script( 'robot-food-pinterest', '!function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version="3.0";var t=document.createElement("script");t.async=!0,t.src=e;var r=document.getElementsByTagName("script")[0];r.parentNode.insertBefore(t,r)}}("https://s.pinimg.com/ct/core.js");pintrk("load","' . esc_js( $pinterest ) . '");pintrk("page");' );
		}
		$tiktok = self::get_option( 'tiktok_id' );
		if ( $tiktok && preg_match( '/^[A-Za-z0-9]+$/', $tiktok ) ) {
			wp_register_script( 'robot-food-tiktok', '', array(), ROBOT_FOOD_VER, false );
			wp_enqueue_script( 'robot-food-tiktok' );
			wp_add_inline_script( 'robot-food-tiktok', '!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._r=ttq._r||{};n&&(ttq._r[e]=n);var o=document.createElement("script");o.type="text/javascript";o.async=!0;o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load("' . esc_js( $tiktok ) . '");ttq.page();}(window,document,"ttq");' );
		}
		$x_pixel = self::get_option( 'x_pixel_id' );
		if ( $x_pixel && preg_match( '/^[A-Za-z0-9]+$/', $x_pixel ) ) {
			wp_register_script( 'robot-food-x-pixel', '', array(), ROBOT_FOOD_VER, false );
			wp_enqueue_script( 'robot-food-x-pixel' );
			wp_add_inline_script( 'robot-food-x-pixel', '!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments)},s.version="1.1",s.queue=[],u=t.createElement(n),u.async=!0,u.src="https://static.ads-twitter.com/uwt.js",a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,"script");twq("config","' . esc_js( $x_pixel ) . '");' );
		}
	}

	public static function output_tracking_body() {
		$gtm_id = self::get_option( 'gtm_id' );
		if ( $gtm_id && preg_match( '/^GTM-[A-Z0-9]+$/', $gtm_id ) ) {
			echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( $gtm_id ) . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
		}
	}

	public static function output_schema() {
		$schema_type = self::get_option( 'schema_type', 'Organization' );
		$site_name   = get_bloginfo( 'name' );
		$site_url    = home_url( '/' );
		$org_name    = self::get_option( 'schema_org_name', $site_name );
		$logo_id     = self::get_option( 'schema_logo' );
		$logo_url    = $logo_id ? wp_get_attachment_image_url( (int) $logo_id, 'full' ) : '';
		$socials     = self::get_option( 'schema_socials', '' );
		$same_as     = array();
		if ( $socials ) {
			$same_as = array_values( array_filter( array_map( 'esc_url_raw', array_map( 'trim', explode( "\n", $socials ) ) ) ) );
		}
		$website_schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => $site_name,
			'url'      => $site_url,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $website_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
		$entity = array(
			'@context' => 'https://schema.org',
			'@type'    => $schema_type,
			'name'     => $org_name,
			'url'      => $site_url,
		);
		if ( $logo_url ) {
			$entity['logo'] = $logo_url;
		}
		if ( !empty( $same_as ) ) {
			$entity['sameAs'] = $same_as;
		}
		if ( is_singular() ) {
			$post_id     = get_the_ID();
			$post        = get_post( $post_id );
			if ( !$post ) {
				echo '<script type="application/ld+json">' . wp_json_encode( $entity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
				self::output_breadcrumb_schema();
				return;
			}
			$schema_override = self::get_post_meta( $post_id, 'schema_type' );
			$post_type   = $schema_override ? $schema_override : ( is_singular( 'post' ) ? 'Article' : 'WebPage' );
			$post_schema = array(
				'@context'      => 'https://schema.org',
				'@type'         => $post_type,
				'headline'      => get_the_title( $post_id ),
				'url'           => get_permalink( $post_id ),
				'datePublished' => get_the_date( 'c', $post_id ),
				'dateModified'  => get_the_modified_date( 'c', $post_id ),
				'author'        => array(
					'@type' => 'Person',
					'name'  => get_the_author_meta( 'display_name', $post->post_author ),
				),
				'publisher'       => array(
					'@type' => $schema_type,
					'name'  => $org_name,
				),
			);
			if ( $logo_url ) {
				$post_schema['publisher']['logo'] = $logo_url;
			}
			$og_image = self::get_og_image();
			if ( $og_image ) {
				$post_schema['image'] = $og_image;
			}
			$desc = self::get_description();
			if ( $desc ) {
				$post_schema['description'] = $desc;
			}
			echo '<script type="application/ld+json">' . wp_json_encode( $post_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $entity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
		self::output_breadcrumb_schema();
	}

	public static function output_breadcrumb_schema() {
		$crumbs = array();
		$pos    = 1;
		$crumbs[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => get_bloginfo( 'name' ),
			'item'     => home_url( '/' ),
		);
		if ( is_singular() ) {
			$post_id    = get_the_ID();
			$post_type  = get_post_type( $post_id );
			if ( 'post' === $post_type ) {
				$categories     = get_the_category( $post_id );
				$primary_cat_id = (int) self::get_post_meta( $post_id, 'primary_cat', 0 );
				$cat            = null;
				if ( $primary_cat_id ) {
					$primary_term = get_term( $primary_cat_id, 'category' );
					if ( $primary_term && !is_wp_error( $primary_term ) ) {
						$cat = $primary_term;
					}
				}
				if ( !$cat && !empty( $categories ) ) {
					$cat = $categories[0];
				}
				if ( $cat ) {
					$crumbs[] = array(
						'@type'    => 'ListItem',
						'position' => $pos++,
						'name'     => $cat->name,
						'item'     => get_category_link( $cat->term_id ),
					);
				}
			} elseif ( 'page' === $post_type ) {
				$ancestors = get_post_ancestors( $post_id );
				foreach ( array_reverse( $ancestors ) as $ancestor_id ) {
					$crumbs[] = array(
						'@type'    => 'ListItem',
						'position' => $pos++,
						'name'     => get_the_title( $ancestor_id ),
						'item'     => get_permalink( $ancestor_id ),
					);
				}
			}
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => get_the_title( $post_id ),
				'item'     => get_permalink( $post_id ),
			);
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term     = get_queried_object();
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => $term->name,
				'item'     => get_term_link( $term ),
			);
		} elseif ( is_author() ) {
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => get_the_author_meta( 'display_name', get_queried_object_id() ),
				'item'     => get_author_posts_url( get_queried_object_id() ),
			);
		} elseif ( is_archive() ) {
			$post_type_obj = get_post_type_object( get_post_type() );
			if ( $post_type_obj ) {
				$crumbs[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => $post_type_obj->labels->name,
					'item'     => get_post_type_archive_link( get_post_type() ),
				);
			}
		}
		if ( count( $crumbs ) < 2 ) {
			return;
		}
		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $crumbs,
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
	}

	public static function output_pagination_links() {
		global $wp_query;
		if ( !is_singular() && !is_home() && !is_archive() && !is_search() ) {
			return;
		}
		$max  = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 1;
		$page = max( 1, (int) ( get_query_var( 'paged' ) ?: 1 ) );
		if ( is_singular() ) {
			$max  = isset( $wp_query->post->post_content ) ? substr_count( $wp_query->post->post_content, '<!--nextpage-->' ) + 1 : 1;
			$page = max( 1, (int) ( get_query_var( 'page' ) ?: 1 ) );
		}
		if ( $max < 2 ) {
			return;
		}
		if ( $page > 1 ) {
			echo '<link rel="prev" href="' . esc_url( get_pagenum_link( $page - 1 ) ) . '">' . "\n";
		}
		if ( $page < $max ) {
			echo '<link rel="next" href="' . esc_url( get_pagenum_link( $page + 1 ) ) . '">' . "\n";
		}
	}

	public static function filter_robots( $robots ) {
		if ( is_singular() ) {
			$post_id = get_the_ID();
			$noindex = self::get_post_meta( $post_id, 'noindex', '0' );
			if ( '1' === $noindex ) {
				$robots['noindex']  = true;
				$robots['nofollow'] = true;
				unset( $robots['max-image-preview'] );
				unset( $robots['max-snippet'] );
				unset( $robots['max-video-preview'] );
			}
		}
		return $robots;
	}

	public static function filter_robots_noindex( $robots ) {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		$path = trailingslashit( $path );
		$noindex = false;
		if ( self::get_option( 'noindex_404', '0' ) === '1' && is_404() ) {
			$noindex = true;
		}
		if ( self::get_option( 'noindex_search', '0' ) === '1' && is_search() ) {
			$noindex = true;
		}
		if ( self::get_option( 'noindex_category', '0' ) === '1' && is_category() ) {
			$noindex = true;
		}
		if ( self::get_option( 'noindex_tag', '0' ) === '1' && is_tag() ) {
			$noindex = true;
		}
		if ( self::get_option( 'noindex_date', '0' ) === '1' && is_date() ) {
			$noindex = true;
		}
		if ( self::get_option( 'noindex_archive', '0' ) === '1' && is_post_type_archive() ) {
			$noindex = true;
		}
		if ( self::get_option( 'noindex_author', '0' ) === '1' && is_author() ) {
			$noindex = true;
		}
		if ( self::get_option( 'noindex_attachment', '0' ) === '1' && is_attachment() ) {
			$noindex = true;
		}
		if ( self::get_option( 'noindex_feed', '0' ) === '1' && is_feed() ) {
			$noindex = true;
		}
		if ( self::get_option( 'noindex_pagination', '0' ) === '1' ) {
			if ( get_query_var( 'paged' ) > 1 || get_query_var( 'page' ) > 1 ) {
				$noindex = true;
			}
		}
		$slug_checks = array();
		if ( self::get_option( 'noindex_login', '0' ) === '1' ) {
			$slug_checks = array_merge( $slug_checks, array( '/login/', '/log-in/', '/sign-in/', '/signin/', '/wp-login.php' ) );
		}
		if ( self::get_option( 'noindex_logout', '0' ) === '1' ) {
			$slug_checks = array_merge( $slug_checks, array( '/logout/', '/log-out/', '/sign-out/', '/signout/' ) );
		}
		if ( self::get_option( 'noindex_register', '0' ) === '1' ) {
			$slug_checks = array_merge( $slug_checks, array( '/register/', '/registration/', '/sign-up/', '/signup/' ) );
		}
		$custom_slugs = self::get_option( 'noindex_custom_slugs', '' );
		if ( $custom_slugs ) {
			$custom = array_filter( array_map( 'trim', explode( ',', $custom_slugs ) ) );
			foreach ( $custom as $slug ) {
				$slug_checks[] = trailingslashit( '/' . ltrim( $slug, '/' ) );
			}
		}
		foreach ( $slug_checks as $slug ) {
			if ( str_starts_with( $path, $slug ) || $path === rtrim( $slug, '/' ) ) {
				$noindex = true;
				break;
			}
		}
		if ( $noindex ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['max-image-preview'] );
			unset( $robots['max-snippet'] );
			unset( $robots['max-video-preview'] );
		}
		return $robots;
	}

	public static function filter_robots_txt( $output, $_public ) {
		$saved = self::get_option( 'robots_txt' );
		if ( $saved ) {
			return $saved;
		}
		if ( '1' !== self::get_option( 'sitemap_disable', '0' ) ) {
			$output .= "\nSitemap: " . esc_url( home_url( '/sitemap.xml' ) );
		}
		return $output;
	}

	public static function handle_sitemap_xml() {
		if ( !isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$uri  = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$path = rtrim( wp_parse_url( home_url(), PHP_URL_PATH ) ?? '', '/' );
		if ( $path . '/sitemap.xml' !== $uri && '/sitemap.xml' !== $uri ) {
			return;
		}
		$disable = self::get_option( 'sitemap_disable', '0' );
		if ( '1' === $disable ) {
			status_header( 404 );
			exit;
		}
		$excluded_post_types = self::get_option( 'sitemap_exclude_post_types', array() );
		$excluded_post_ids   = array_filter( array_map( 'intval', explode( ',', self::get_option( 'sitemap_exclude_posts', '' ) ) ) );
		$excluded_term_ids   = array_filter( array_map( 'intval', explode( ',', self::get_option( 'sitemap_exclude_terms', '' ) ) ) );
		$urls = array();
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type, $excluded_post_types, true ) ) {
				continue;
			}
			$posts = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to exclude individually marked posts from sitemap.
				'meta_query'   => array(
					'relation' => 'OR',
					array(
						'key'     => '_robot_food_sitemap_exclude',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_robot_food_sitemap_exclude',
						'value'   => '1',
						'compare' => '!=',
					),
				),
			) );
			foreach ( $posts as $post_id ) {
				if ( in_array( $post_id, $excluded_post_ids, true ) ) {
					continue;
				}
				$urls[] = array(
					'loc'     => get_permalink( $post_id ),
					'lastmod' => get_the_modified_date( 'c', $post_id ),
				);
			}
		}
		$taxonomies = get_taxonomies( array( 'public' => true ) );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'exclude'    => $excluded_term_ids, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Intentional user-configured exclusion list for sitemap; not a performance concern at this scale.
			) );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$urls[] = array(
					'loc' => get_term_link( $term ),
				);
			}
		}
		header( 'Content-Type: application/xml; charset=utf-8' );
		status_header( 200 );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $urls as $url ) {
			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url( $url['loc'] ) . "</loc>\n";
			if ( !empty( $url['lastmod'] ) ) {
				echo "\t\t<lastmod>" . esc_html( $url['lastmod'] ) . "</lastmod>\n";
			}
			echo "\t</url>\n";
		}
		echo '</urlset>';
		exit;
	}

	public static function handle_llms_txt() {
		if ( !isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$uri = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$path = rtrim( wp_parse_url( home_url(), PHP_URL_PATH ) ?? '', '/' );
		if ( $path . '/llms.txt' !== $uri && '/llms.txt' !== $uri ) {
			return;
		}
		$disable = self::get_option( 'llms_disable', '0' );
		if ( '1' === $disable ) {
			status_header( 404 );
			exit;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		status_header( 200 );
		$site_name = get_bloginfo( 'name' );
		$site_desc = get_bloginfo( 'description' );
		$site_url  = home_url( '/' );
		$custom_header = self::get_option( 'llms_header', '' );
		if ( $custom_header ) {
			echo esc_html( $custom_header ) . "\n\n";
		} else {
			echo '# ' . esc_html( $site_name ) . "\n\n";
			if ( $site_desc ) {
				echo '> ' . esc_html( $site_desc ) . "\n\n";
			}
			echo esc_html(
				sprintf(
					/* translators: 1: site name, 2: site URL */
					__( 'This is the official website of %1$s, located at %2$s.', 'robot-food' ),
					$site_name,
					$site_url
				)
			) . "\n\n";
		}
		echo "## Pages\n\n";
		$llms_excluded_ids = array_filter( array_map( 'intval', explode( ',', self::get_option( 'llms_exclude_posts', '' ) ) ) );
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );
		foreach ( $pages as $page ) {
			$noindex  = get_post_meta( $page->ID, '_robot_food_noindex', true );
			$excluded = get_post_meta( $page->ID, '_robot_food_llms_exclude', true );
			if ( '1' === $noindex || '1' === $excluded || in_array( $page->ID, $llms_excluded_ids, true ) ) {
				continue;
			}
			echo '- [' . esc_html( get_the_title( $page ) ) . '](' . esc_url( get_permalink( $page ) ) . ')' . "\n";
		}
		echo "\n## Posts\n\n";
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		foreach ( $posts as $post ) {
			$noindex  = get_post_meta( $post->ID, '_robot_food_noindex', true );
			$excluded = get_post_meta( $post->ID, '_robot_food_llms_exclude', true );
			if ( '1' === $noindex || '1' === $excluded || in_array( $post->ID, $llms_excluded_ids, true ) ) {
				continue;
			}
			echo '- [' . esc_html( get_the_title( $post ) ) . '](' . esc_url( get_permalink( $post ) ) . ')' . "\n";
		}
		$post_types = get_post_types( array(
			'public'   => true,
			'_builtin' => false,
		) );
		foreach ( $post_types as $post_type ) {
			$cpt_posts = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			) );
			if ( empty( $cpt_posts ) ) {
				continue;
			}
			$post_type_obj = get_post_type_object( $post_type );
			$label         = $post_type_obj ? $post_type_obj->labels->name : $post_type;
			echo "\n## " . esc_html( $label ) . "\n\n";
			foreach ( $cpt_posts as $cpt_post ) {
				$noindex  = get_post_meta( $cpt_post->ID, '_robot_food_noindex', true );
				$excluded = get_post_meta( $cpt_post->ID, '_robot_food_llms_exclude', true );
				if ( '1' === $noindex || '1' === $excluded || in_array( $cpt_post->ID, $llms_excluded_ids, true ) ) {
					continue;
				}
				echo '- [' . esc_html( get_the_title( $cpt_post ) ) . '](' . esc_url( get_permalink( $cpt_post ) ) . ')' . "\n";
			}
		}
		$extra = self::get_option( 'llms_extra', '' );
		if ( $extra ) {
			echo "\n" . esc_html( $extra ) . "\n";
		}
		exit;
	}
}
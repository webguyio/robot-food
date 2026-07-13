<?php
/*
Plugin Name: Robot Food
Plugin URI: https://robotfood.me/
Description: Feed the robots tasty snacks to improve your site's SEO and AIO.
Version: 0.2
Author: Web Guy
Author URI: https://webguy.io/
Requires at least: 6.0
Requires PHP: 8.0
License: CC0
License URI: https://creativecommons.org/public-domain/cc0/
Text Domain: robot-food
*/

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

define( 'ROBOT_FOOD_VER', '0.2' );
define( 'ROBOT_FOOD_DIR', plugin_dir_path( __FILE__ ) );
define( 'ROBOT_FOOD_URL', plugin_dir_url( __FILE__ ) );

require_once ROBOT_FOOD_DIR . 'classes/food.php';
require_once ROBOT_FOOD_DIR . 'classes/meta.php';
require_once ROBOT_FOOD_DIR . 'classes/user.php';
require_once ROBOT_FOOD_DIR . 'classes/settings.php';

register_activation_hook( __FILE__, 'robot_food_activate' );

function robot_food_activate() {
	if ( !get_option( 'robot_food_indexnow_key' ) ) {
		update_option( 'robot_food_indexnow_key', wp_generate_password( 32, false ) );
	}
	flush_rewrite_rules();
}

Robot_Food::init();
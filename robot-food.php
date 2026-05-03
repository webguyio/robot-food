<?php
/*
Plugin Name: Robot Food
Plugin URI: https://robotfood.me/
Description: Feed the robots tasty snacks to improve your site's SEO and AIO.
Version: 0.1
Author: Web Guy
Author URI: https://webguy.io/
Requires at least: 5.9
Requires PHP: 7.4
License: CC0
License URI: https://creativecommons.org/public-domain/cc0/
Text Domain: robot-food
*/

if ( !defined( 'ABSPATH' ) ) {
	status_header( 404 );
	exit;
}

define( 'ROBOT_FOOD_VER', '0.1' );
define( 'ROBOT_FOOD_DIR', plugin_dir_path( __FILE__ ) );
define( 'ROBOT_FOOD_URL', plugin_dir_url( __FILE__ ) );

require_once ROBOT_FOOD_DIR . 'classes/food.php';
require_once ROBOT_FOOD_DIR . 'classes/meta.php';
require_once ROBOT_FOOD_DIR . 'classes/settings.php';

Robot_Food::init();
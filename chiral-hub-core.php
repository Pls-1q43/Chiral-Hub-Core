<?php
/**
 * Plugin Name: Chiral Hub Core
 * Plugin URI: https://ckc.akashio.com
 * Description: Transforms a WordPress site into a Chiral Hub, managing connected Chiral Nodes, aggregating data, and providing related content via Jetpack.
 * Version: 1.0.0
 * Author: 评论尸(Pls)
 * Author URI: https://1q43.blog
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: chiral-hub-core
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define CHIRAL_HUB_CORE_PLUGIN_FILE.
if ( ! defined( 'CHIRAL_HUB_CORE_PLUGIN_FILE' ) ) {
    define( 'CHIRAL_HUB_CORE_PLUGIN_FILE', __FILE__ );
}

// Define CHIRAL_HUB_CORE_PLUGIN_DIR.
if ( ! defined( 'CHIRAL_HUB_CORE_PLUGIN_DIR' ) ) {
    define( 'CHIRAL_HUB_CORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Define CHIRAL_HUB_CORE_PLUGIN_URL.
if ( ! defined( 'CHIRAL_HUB_CORE_PLUGIN_URL' ) ) {
    define( 'CHIRAL_HUB_CORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Define CHIRAL_HUB_CORE_VERSION.
if ( ! defined( 'CHIRAL_HUB_CORE_VERSION' ) ) {
    define( 'CHIRAL_HUB_CORE_VERSION', '1.0.0' );
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-core.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_chiral_hub_core() {
    $plugin = new Chiral_Hub_Core();
    $plugin->run();
}
run_chiral_hub_core();

/**
 * Activation hook.
 */
register_activation_hook( CHIRAL_HUB_CORE_PLUGIN_FILE, 'chiral_hub_core_activate' );

/**
 * Deactivation hook.
 */
register_deactivation_hook( CHIRAL_HUB_CORE_PLUGIN_FILE, 'chiral_hub_core_deactivate' );

/**
 * The function responsible for firing when the plugin is activated.
 *
 * @since    1.0.0
 */
function chiral_hub_core_activate() {
    require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-activator.php';
    Chiral_Hub_Activator::activate();
}

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/Pls-1q43/Chiral-Hub-Core/',
    __FILE__,
    'chiral-hub-core'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

/**
 * The function responsible for firing when the plugin is deactivated.
 *
 * @since    1.0.0
 */
function chiral_hub_core_deactivate() {
    require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-deactivator.php';
    Chiral_Hub_Deactivator::deactivate();
}
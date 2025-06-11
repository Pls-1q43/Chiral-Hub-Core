<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 */

/**
 * Fired during plugin activation.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_Activator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Ensure dependent classes are available if needed for activation tasks
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-roles.php';
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-cpt.php';
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-node-checker.php';

        // Add Chiral Porter role and capabilities
        Chiral_Hub_Roles::add_roles_and_caps();

        // Register CPT to ensure rewrite rules are available
        $cpt_handler = new Chiral_Hub_CPT( 'chiral-hub-core', '1.0.0' );
        $cpt_handler->register_chiral_data_cpt();
        $cpt_handler->register_meta_fields();

        // Set up default options if they don't exist
        self::setup_default_options();

        // Schedule node status checking
        $node_checker = new Chiral_Hub_Node_Checker( 'chiral-hub-core', '1.0.0' );
        $node_checker->schedule_daily_checks();

        // Flush rewrite rules to ensure CPTs and custom roles are recognized.
        flush_rewrite_rules();
    }

    /**
     * Sets up default plugin options if they are not already set.
     *
     * @since 1.0.0
     * @access private
     */
    private static function setup_default_options() {
        $options = array(
            'chiral_hub_core_network_name' => get_bloginfo( 'name' ) . ' Chiral Network',
            'chiral_hub_core_new_porter_sync_strategy' => 'publish', // 'publish', 'pending', 'draft'
            'chiral_hub_core_default_related_count' => 5,
        );

        foreach ( $options as $option_name => $default_value ) {
            if ( get_option( $option_name ) === false ) {
                update_option( $option_name, $default_value );
            }
        }
    }
}
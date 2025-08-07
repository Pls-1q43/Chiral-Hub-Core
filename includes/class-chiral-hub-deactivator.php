<?php

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_Deactivator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Remove Chiral Porter role (optional, consider if data should remain accessible)
        // require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-roles.php';
        // Chiral_Hub_Roles::remove_role(); // Be cautious with this, might orphan users or content.

        // Clear any scheduled cron jobs if used by the plugin
        // wp_clear_scheduled_hook('chiral_hub_core_daily_event');
        
        // Clear node status checking cron jobs
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-node-checker.php';
        $node_checker = new Chiral_Hub_Node_Checker( 'chiral-hub-core', '1.0.0' );
        $node_checker->unschedule_daily_checks();

        // Clear RSS sync cron jobs
        wp_clear_scheduled_hook( 'chiral_hub_hourly_rss_sync' );
        wp_clear_scheduled_hook( 'chiral_hub_daily_rss_patrol' );
        wp_clear_scheduled_hook( 'chiral_hub_process_sitemap_import' ); // Also clear any pending sitemap imports

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

}
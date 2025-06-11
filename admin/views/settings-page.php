<?php

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * Provides the HTML for the Chiral Hub Core settings page.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/admin/views
 */

// Ensure this file is loaded within WordPress.
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>

<div class="wrap chiral-hub-settings-wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <form method="post" action="options.php">
        <?php
        // This prints out all hidden "option_page" fields for the "chiral_hub_core_options_group".
        settings_fields( $this->plugin_name . '_options_group' );

        // This prints out all settings sections and fields for the "chiral_hub_core_settings_page".
        // The slug used for do_settings_sections() must match the slug used in add_settings_section().
        do_settings_sections( $this->plugin_name . '_settings_page' );
        ?>
        
        <?php submit_button( __( 'Save Settings', 'chiral-hub-core' ) ); ?>
    </form>
</div>
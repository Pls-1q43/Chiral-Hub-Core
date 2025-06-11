<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/public
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_Public {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Chiral_Hub_Core_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Chiral_Hub_Core_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        // wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/chiral-hub-public.css', array(), $this->version, 'all' );
        // No public facing styles needed for the hub core as it primarily redirects or serves API.

    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Chiral_Hub_Core_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Chiral_Hub_Core_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        // wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/chiral-hub-public.js', array( 'jquery' ), $this->version, false );
        // No public facing scripts needed for the hub core as it primarily redirects or serves API.
    }

    // Add public-specific methods here. For Chiral Hub Core, this class might be minimal
    // as most functionality is admin, CPT, REST API, or redirect based.
    // If shortcodes were to be provided by the Hub (less likely), they would be defined here.

    /**
     * Exclude chiral_data from main search results while keeping them available for Jetpack related posts.
     * 
     * This ensures that Chiral Data items don't appear in Hub site search results,
     * but remain accessible to Jetpack for generating related articles.
     *
     * @since    1.0.0
     * @param    WP_Query $query The WP_Query instance (passed by reference).
     */
    public function exclude_chiral_data_from_search( $query ) {
        // Only apply to main queries on the frontend that are search queries
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
            return;
        }

        // Get the current post types being queried
        $post_types = $query->get( 'post_type' );
        
        // If no specific post types are set, WordPress searches all public post types
        if ( empty( $post_types ) ) {
            // Get all public post types
            $public_post_types = get_post_types( array( 'public' => true ) );
            
            // Remove chiral_data from the list
            if ( isset( $public_post_types[Chiral_Hub_CPT::CPT_SLUG] ) ) {
                unset( $public_post_types[Chiral_Hub_CPT::CPT_SLUG] );
            }
            
            // Set the modified post types array
            $query->set( 'post_type', array_keys( $public_post_types ) );
        } else {
            // If specific post types are already set, ensure chiral_data is not included
            if ( is_string( $post_types ) ) {
                $post_types = array( $post_types );
            }
            
            // Remove chiral_data if it's in the array
            $post_types = array_diff( $post_types, array( Chiral_Hub_CPT::CPT_SLUG ) );
            
            // Set the modified post types array
            $query->set( 'post_type', $post_types );
        }
    }

}
<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_Core {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Chiral_Hub_Core_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if ( defined( 'CHIRAL_HUB_CORE_VERSION' ) ) {
            $this->version = CHIRAL_HUB_CORE_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'chiral-hub-core';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cpt_hooks();
        $this->define_roles_hooks();
        $this->define_porter_admin_hooks();
        $this->define_rest_api_hooks();
        $this->define_sync_hooks();
        $this->define_redirect_hooks();
        $this->define_jetpack_hooks();
        $this->define_node_checker_hooks();
        $this->define_rss_hooks();

    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Chiral_Hub_Core_Loader. Orchestrates the hooks of the plugin.
     * - Chiral_Hub_Core_i18n. Defines internationalization functionality.
     * - Chiral_Hub_Core_Admin. Defines all hooks for the admin area.
     * - Chiral_Hub_Core_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-core-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'admin/class-chiral-hub-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'public/class-chiral-hub-public.php';
        
        /**
         * The class responsible for registering the Chiral Data CPT.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-cpt.php';

        /**
         * The class responsible for registering the Chiral Porter Role.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-roles.php';

        /**
         * The class responsible for Porter admin interface.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-porter-admin.php';

        /**
         * The class responsible for the custom REST API endpoints.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-rest-api.php';

        /**
         * The class responsible for sync logic.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-sync.php';

        /**
         * The class responsible for redirect logic.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-redirect.php';

        /**
         * The class responsible for Jetpack integration.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-jetpack.php';

        /**
         * The class responsible for node status checking.
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-node-checker.php';

        /**
         * The class responsible for RSS crawling and processing (1.2.0+).
         */
        require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-rss-crawler.php';

        /**
         * The class responsible for utility functions.
         */
        // require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-utils.php'; // TODO: Create this class if needed


        $this->loader = new Chiral_Hub_Core_Loader();

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Chiral_Hub_Core_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {

        $plugin_i18n = new Chiral_Hub_i18n();

        $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {

        $plugin_admin = new Chiral_Hub_Admin( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
        
        // AJAX hooks for node status checking
        $this->loader->add_action( 'wp_ajax_chiral_hub_check_node_status', $plugin_admin, 'ajax_check_node_status' );
        $this->loader->add_action( 'wp_ajax_chiral_hub_check_all_nodes_status', $plugin_admin, 'ajax_check_all_nodes_status' );
        
        // Add more admin hooks here
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {

        $plugin_public = new Chiral_Hub_Public( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
        
        // Exclude chiral_data from site search results while keeping them accessible to Jetpack
        $this->loader->add_action( 'pre_get_posts', $plugin_public, 'exclude_chiral_data_from_search' );
        
        // Add robots meta tag to prevent search engine indexing of chiral_data pages
        $this->loader->add_action( 'wp_head', $plugin_public, 'add_chiral_data_robots_meta' );
    }

    /**
     * Register CPT hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_cpt_hooks() {
        $cpt_handler = new Chiral_Hub_CPT( $this->get_plugin_name(), $this->get_version() );
        $this->loader->add_action( 'init', $cpt_handler, 'register_chiral_data_cpt' );
        $this->loader->add_action( 'init', $cpt_handler, 'register_meta_fields' );
        $this->loader->add_filter( 'rest_api_allowed_public_metadata', $cpt_handler, 'filter_allowed_public_metadata', 10, 1 );
        // Customize menu for Chiral Porter
        $this->loader->add_action( 'admin_menu', $cpt_handler, 'customize_porter_menu', 999 );
        
        // 确保 REST API 响应包含动态的 chiral_network_name
        $this->loader->add_filter( 'rest_prepare_' . Chiral_Hub_CPT::CPT_SLUG, $cpt_handler, 'inject_network_name_in_rest_response', 10, 3 );
    }

    /**
     * Register Roles hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_roles_hooks() {
        $roles_handler = new Chiral_Hub_Roles();
        // Only register roles if they don't exist - this will handle both activation and init cases
        $this->loader->add_action( 'init', $roles_handler, 'add_roles_and_caps', 1 );
        // Temporarily disable all Porter admin access to fix WordPress 6.1+ compatibility
        // Add filter for capability mapping  
        $this->loader->add_filter( 'map_meta_cap', $roles_handler, 'map_chiral_data_meta_cap', 10, 4 );
        // Disable Porter admin access related filters
        // $this->loader->add_filter( 'user_has_cap', $roles_handler, 'handle_porter_publish_capabilities', 10, 4 );
        // $this->loader->add_filter( 'user_has_cap', $roles_handler, 'check_porter_admin_access', 5, 3 );
        $this->loader->add_action( 'admin_init', $roles_handler, 'restrict_porter_dashboard_access' );
        $this->loader->add_action( 'admin_bar_menu', $roles_handler, 'customize_porter_admin_bar', 999 );
        // Disable all standard CPT admin hooks - we use custom Porter admin page instead
        // $this->loader->add_action( 'pre_get_posts', $roles_handler, 'filter_porter_chiral_data_query' );
        // $this->loader->add_filter( 'post_row_actions', $roles_handler, 'remove_porter_edit_actions', 10, 2 );
        // $this->loader->add_filter( 'bulk_actions-edit-' . Chiral_Hub_CPT::CPT_SLUG, $roles_handler, 'remove_porter_bulk_actions' );
        // Debug capabilities (only when WP_DEBUG is enabled)
        $this->loader->add_action( 'admin_init', $roles_handler, 'debug_user_capabilities' );
    }

    /**
     * Register Porter Admin hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_porter_admin_hooks() {
        $porter_admin = new Chiral_Hub_Porter_Admin();
        
        // Handle Porter admin actions EARLY in admin_init to prevent headers already sent errors
        $this->loader->add_action( 'admin_init', $porter_admin, 'handle_porter_admin_actions', 5 );
        
        // Add custom Porter admin menu
        $this->loader->add_action( 'admin_menu', $porter_admin, 'add_porter_admin_menu' );
        
        // Redirect Porter users from standard CPT admin pages
        $this->loader->add_action( 'admin_init', $porter_admin, 'redirect_porter_from_cpt_admin' );
        
        // AJAX hooks for Porter admin functionality
        $this->loader->add_action( 'wp_ajax_chiral_test_rss_connection', $porter_admin, 'ajax_test_rss_connection' );
        $this->loader->add_action( 'wp_ajax_chiral_start_sitemap_import', $porter_admin, 'ajax_start_sitemap_import' );
        $this->loader->add_action( 'wp_ajax_chiral_get_import_progress', $porter_admin, 'ajax_get_import_progress' );
        $this->loader->add_action( 'wp_ajax_chiral_reset_import_status', $porter_admin, 'ajax_reset_import_status' );
        $this->loader->add_action( 'wp_ajax_chiral_sync_rss_now', $porter_admin, 'ajax_sync_rss_now' );
        $this->loader->add_action( 'wp_ajax_chiral_re_sync_post', $porter_admin, 'ajax_re_sync_post' );

        // Cron hooks for background processing
        $rss_crawler = new Chiral_Hub_RSS_Crawler( 'chiral-hub-core', CHIRAL_HUB_CORE_VERSION );
        $this->loader->add_action( 'chiral_hub_process_sitemap_import', $rss_crawler, 'process_sitemap_import', 10, 2 );
    }

    /**
     * Register REST API hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_rest_api_hooks() {
        $rest_api_handler = new Chiral_Hub_REST_API( $this->get_plugin_name() );
        $this->loader->add_action( 'rest_api_init', $rest_api_handler, 'register_routes' );
        $this->loader->add_filter( 'rest_prepare_' . Chiral_Hub_CPT::CPT_SLUG, $rest_api_handler, 'control_source_url_visibility_in_response', 10, 3 );
    }

    /**
     * Register Sync hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_sync_hooks() {
        $plugin_sync = new Chiral_Hub_Sync();
        // Filter data before saving (e.g. for content cleaning, not used by process_incoming_data directly but good for general CPT save)
        // $this->loader->add_filter( 'wp_insert_post_data', $plugin_sync, 'filter_chiral_data_on_save', 10, 2 );

        // ULTIMATE SECURITY: Force correct post status at WordPress core level (highest priority)
        $this->loader->add_filter( 'wp_insert_post_data', $plugin_sync, 'force_post_status_at_core_level', 1, 2 );

        // Generate random slug for all chiral_data posts to avoid Chinese slug issues
        $this->loader->add_filter( 'wp_insert_post_data', $plugin_sync, 'generate_random_slug_for_chiral_data', 2, 2 );

        // Security: Intercept REST API requests early to prevent status bypass attacks
        $this->loader->add_filter( 'rest_pre_dispatch', $plugin_sync, 'intercept_chiral_data_requests', 10, 3 );

        // Hook into REST API pre-insert for chiral_data CPT
        $this->loader->add_action( 'rest_pre_insert_' . Chiral_Hub_CPT::CPT_SLUG, $plugin_sync, 'handle_rest_pre_insert_chiral_data', 10, 2 );

        // Hook into REST API after-insert for chiral_data CPT to handle featured image
        $this->loader->add_action( 'rest_after_insert_' . Chiral_Hub_CPT::CPT_SLUG, $plugin_sync, 'handle_rest_after_insert_chiral_data', 10, 3 );

        // Security: Final verification of post status compliance
        $this->loader->add_action( 'rest_after_insert_' . Chiral_Hub_CPT::CPT_SLUG, $plugin_sync, 'verify_post_status_compliance', 20, 3 );

        // Auto-set network name (for manual post editing cases)
        $this->loader->add_action( 'save_post', $plugin_sync, 'auto_set_network_name_on_save', 10, 3 );
    }

    /**
     * Register Redirect hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_redirect_hooks() {
        $redirect_handler = new Chiral_Hub_Redirect();
        // Initialize the redirect hooks.
        $redirect_handler->init();
    }

    /**
     * Register Jetpack hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_jetpack_hooks() {
        $jetpack_handler = new Chiral_Hub_Jetpack();

        // CRITICAL: Allow our CPT in REST API for Jetpack Related Posts to work
        $this->loader->add_filter( 'rest_api_allowed_post_types', $jetpack_handler, 'filter_rest_api_allowed_post_types', 10, 1 );

        // Ensure our CPT is considered by Jetpack Related Posts
        $this->loader->add_filter( 'jetpack_relatedposts_filter_post_type', $jetpack_handler, 'filter_jetpack_related_post_types', 10, 2 );

        // Enable related posts for our CPT
        $this->loader->add_filter( 'jetpack_relatedposts_filter_enabled_for_request', $jetpack_handler, 'enable_related_posts_for_cpt', 10, 1 );

        // CRITICAL: Configure Jetpack Sync metadata whitelist
        $this->loader->add_filter( 'jetpack_sync_post_meta_whitelist', $jetpack_handler, 'configure_jetpack_sync_metadata', 10 );

        // Force register filters on init to ensure they are properly loaded
        $this->loader->add_action( 'init', $jetpack_handler, 'force_register_filters', 999 );

        // Emergency fallback filters
        $this->loader->add_filter( 'rest_api_allowed_post_types', $jetpack_handler, 'emergency_rest_api_filter', 999 );
        $this->loader->add_filter( 'jetpack_relatedposts_filter_post_type', $jetpack_handler, 'emergency_jetpack_filter', 999, 2 );
    }

    /**
     * Register Node Checker hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_node_checker_hooks() {
        $node_checker = new Chiral_Hub_Node_Checker( $this->get_plugin_name(), $this->get_version() );
        
        // Schedule daily checks
        $this->loader->add_action( 'wp', $node_checker, 'schedule_daily_checks' );
        
        // Handle cron jobs
        $this->loader->add_action( 'chiral_hub_daily_node_check', $node_checker, 'handle_daily_node_check' );
        $this->loader->add_action( 'chiral_hub_staggered_node_check', $node_checker, 'handle_staggered_node_check' );
    }

    /**
     * Register RSS hooks.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_rss_hooks() {
        $rss_crawler = new Chiral_Hub_RSS_Crawler( $this->get_plugin_name(), $this->get_version() );
        
        // Schedule RSS sync tasks (these will be handled by WordPress cron)
        $this->loader->add_action( 'chiral_hub_hourly_rss_sync', $rss_crawler, 'handle_hourly_rss_sync' );
        $this->loader->add_action( 'chiral_hub_daily_rss_patrol', $rss_crawler, 'handle_daily_rss_patrol' );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Chiral_Hub_Core_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

}
<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/admin
 * @author     Your Name <email@example.com>
 * @since      1.0.0
 */
class Chiral_Hub_Admin {

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
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, CHIRAL_HUB_CORE_PLUGIN_URL . 'admin/assets/css/chiral-hub-admin.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, CHIRAL_HUB_CORE_PLUGIN_URL . 'admin/assets/js/chiral-hub-admin.js', array( 'jquery' ), $this->version, false );
    }

    /**
     * Add options page.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __( 'Chiral Hub Core Settings', 'chiral-hub-core' ),
            __( 'Chiral Hub', 'chiral-hub-core' ),
            'manage_options',
            $this->plugin_name,
            array( $this, 'display_plugin_setup_page' ),
            'dashicons-share-alt2',
            26
        );

        add_submenu_page(
            $this->plugin_name,
            __( 'Chiral Hub Settings', 'chiral-hub-core' ),
            __( 'Settings', 'chiral-hub-core' ),
            'manage_options',
            $this->plugin_name,
            array( $this, 'display_plugin_setup_page' )
        );

        add_submenu_page(
            $this->plugin_name,
            __( 'Node Management', 'chiral-hub-core' ),
            __( 'Node Management', 'chiral-hub-core' ),
            'manage_options',
            $this->plugin_name . '-node-management',
            array( $this, 'display_node_management_page' )
        );
        
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Display the plugin setup page
     *
     * @since    1.0.0
     */
    public function display_plugin_setup_page() {
        include_once( CHIRAL_HUB_CORE_PLUGIN_DIR . 'admin/views/settings-page.php' );
    }

    /**
     * Display the node management page
     *
     * @since    1.0.0
     */
    public function display_node_management_page() {
        include_once( CHIRAL_HUB_CORE_PLUGIN_DIR . 'admin/views/node-management-page.php' );
    }
    
    /**
     * Register the settings for the plugin.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        register_setting(
            $this->plugin_name . '_options_group',
            $this->plugin_name . '_options',
            array( $this, 'sanitize_settings' )
        );

        // General Settings Section
        add_settings_section(
            $this->plugin_name . '_general_section',
            __( 'General Settings', 'chiral-hub-core' ),
            null,
            $this->plugin_name . '_settings_page'
        );

        $general_fields = array(
            'network_name' => array(
                'title' => __( 'Network Name/Description', 'chiral-hub-core' ),
                'callback' => 'render_network_name_field'
            ),
            'new_porter_registration' => array(
                'title' => __( 'Content Review Policy', 'chiral-hub-core' ),
                'callback' => 'render_new_porter_registration_field'
            ),
            'default_related_posts_count' => array(
                'title' => __( 'Default Related Posts Count', 'chiral-hub-core' ),
                'callback' => 'render_default_related_posts_count_field'
            ),
            'enable_hub_transfer_mode' => array(
                'title' => __( 'Enable Hub Transfer Mode', 'chiral-hub-core' ),
                'callback' => 'render_enable_hub_transfer_mode_field'
            )
        );

        foreach ( $general_fields as $field_id => $field_config ) {
            add_settings_field(
                $field_id,
                $field_config['title'],
                array( $this, $field_config['callback'] ),
                $this->plugin_name . '_settings_page',
                $this->plugin_name . '_general_section'
            );
        }

        // Jetpack Section
        add_settings_section(
            $this->plugin_name . '_jetpack_section',
            __( 'Jetpack Integration', 'chiral-hub-core' ),
            array( $this, 'render_jetpack_section_description' ),
            $this->plugin_name . '_settings_page'
        );
    }

    /**
     * Sanitize settings.
     *
     * @since    1.0.0
     * @param    array    $input    The input array.
     * @return   array              The sanitized array.
     */
    public function sanitize_settings( $input ) {
        $sanitized_input = array();
        
        // Get old options to compare
        $old_options = get_option( $this->plugin_name . '_options' );
        $old_network_name = $old_options['network_name'] ?? '';
        
        if ( isset( $input['network_name'] ) ) {
            $sanitized_input['network_name'] = sanitize_text_field( $input['network_name'] );
            
            // If network name changed, update all historical chiral_data posts
            if ( $sanitized_input['network_name'] !== $old_network_name ) {
                $this->auto_update_historical_posts_network_name( $sanitized_input['network_name'] );
            }
        }
        
        if ( isset( $input['new_porter_registration'] ) ) {
            $sanitized_input['new_porter_registration'] = sanitize_text_field( $input['new_porter_registration'] );
        }
        
        if ( isset( $input['default_related_posts_count'] ) ) {
            $sanitized_input['default_related_posts_count'] = absint( $input['default_related_posts_count'] );
        }
        
        $sanitized_input['enable_hub_transfer_mode'] = isset( $input['enable_hub_transfer_mode'] );

        return $sanitized_input;
    }

    /**
     * Automatically update all historical chiral_data posts with new network name
     *
     * @since 1.0.0
     * @param string $network_name The new network name
     */
    private function auto_update_historical_posts_network_name( $network_name ) {
        if ( empty( $network_name ) ) {
            return;
        }

        $posts = get_posts( array(
            'post_type'   => 'chiral_data',
            'post_status' => array( 'publish', 'pending', 'draft', 'private', 'trash' ),
            'numberposts' => -1,
            'fields'      => 'ids',
        ) );

        $updated_count = 0;
        foreach ( $posts as $post_id ) {
            update_post_meta( $post_id, 'chiral_network_name', $network_name );
            $updated_count++;
        }

        // Add success notice
        add_action( 'admin_notices', function() use ( $updated_count, $network_name ) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf( 
                /* translators: %1$d: number of updated posts, %2$s: network name */
                esc_html__( 'Network name updated. Automatically updated %1$d historical Chiral Data posts with network name "%2$s".', 'chiral-hub-core' ), 
                absint( $updated_count ), 
                esc_html( $network_name ) 
            );
            echo '</p></div>';
        } );
    }

    /**
     * Render network name field.
     *
     * @since    1.0.0
     */
    public function render_network_name_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $value = $options['network_name'] ?? '';
        
        printf(
            '<input type="text" id="network_name" name="%s" value="%s" class="regular-text" />
            <p class="description">%s</p>',
            esc_attr( $this->plugin_name . '_options[network_name]' ),
            esc_attr( $value ),
            esc_html__( 'Enter the name or description for your Chiral Network. Changing this will automatically update all existing Chiral Data posts.', 'chiral-hub-core' )
        );
    }

    /**
     * Render new porter registration field.
     *
     * @since    1.0.0
     */
    public function render_new_porter_registration_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $value = $options['new_porter_registration'] ?? 'default_status';
        
        $select_options = array(
            'default_status' => __( 'Publish Directly (Default)', 'chiral-hub-core' ),
            'pending' => __( 'Review Required (Set to Pending)', 'chiral-hub-core' )
        );
        
        printf( '<select id="new_porter_registration" name="%s">', esc_attr( $this->plugin_name . '_options[new_porter_registration]' ) );
        
        foreach ( $select_options as $option_value => $option_label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $option_value ),
                selected( $value, $option_value, false ),
                esc_html( $option_label )
            );
        }
        
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Determine whether new submitted content requires review before being published.', 'chiral-hub-core' ) . '</p>';
    }

    /**
     * Render default related posts count field.
     *
     * @since    1.0.0
     */
    public function render_default_related_posts_count_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $value = $options['default_related_posts_count'] ?? 5;
        
        printf(
            '<input type="number" id="default_related_posts_count" name="%s" value="%s" class="small-text" min="1" max="20"/>
            <p class="description">%s</p>',
            esc_attr( $this->plugin_name . '_options[default_related_posts_count]' ),
            esc_attr( $value ),
            esc_html__( 'Default number of related posts to fetch and display.', 'chiral-hub-core' )
        );
    }

    /**
     * Render enable hub transfer mode field.
     *
     * @since    1.0.0
     */
    public function render_enable_hub_transfer_mode_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $value = (bool) ( $options['enable_hub_transfer_mode'] ?? true );
        
        printf(
            '<input type="checkbox" id="enable_hub_transfer_mode" name="%s" value="1" %s />
            <label for="enable_hub_transfer_mode"> %s</label>
            <p class="description">%s</p>',
            esc_attr( $this->plugin_name . '_options[enable_hub_transfer_mode]' ),
            checked( 1, $value, false ),
            esc_html__( 'Redirect related post links through this Hub site.', 'chiral-hub-core' ),
            esc_html__( 'If enabled, links to related posts on Node sites will first point to this Hub site, which then redirects to the original Node post. If disabled, links will point directly to the original Node post.', 'chiral-hub-core' )
        );
    }

    /**
     * Render Jetpack section description.
     *
     * @since    1.0.0
     */
    public function render_jetpack_section_description() {
        echo '<p>' . esc_html__( 'Chiral Hub Core relies on Jetpack for its related posts functionality. Please ensure Jetpack is installed, active, and connected to WordPress.com, with the "Related posts" module enabled.', 'chiral-hub-core' ) . '</p>';
        
        if ( class_exists( 'Jetpack' ) ) {
            printf(
                '<p><a href="%s" class="button">%s</a></p>',
                esc_url( admin_url( 'admin.php?page=jetpack#/settings' ) ),
                esc_html__( 'Go to Jetpack Settings', 'chiral-hub-core' )
            );
        } else {
            printf(
                '<p><a href="%s" class="button button-primary">%s</a></p>',
                esc_url( admin_url( 'plugin-install.php?s=jetpack&tab=search&type=term' ) ),
                esc_html__( 'Install Jetpack', 'chiral-hub-core' )
            );
        }
    }

    /**
     * AJAX handler for checking single node status.
     *
     * @since 1.0.0
     */
    public function ajax_check_node_status() {
        check_ajax_referer( 'chiral_hub_node_check', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'chiral-hub-core' ) ) );
        }

        $porter_id = isset( $_POST['porter_id'] ) ? absint( $_POST['porter_id'] ) : 0;
        
        if ( ! $porter_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid Porter ID', 'chiral-hub-core' ) ) );
        }

        if ( ! class_exists( 'Chiral_Hub_Node_Checker' ) ) {
            require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-node-checker.php';
        }

        $node_checker = new Chiral_Hub_Node_Checker( $this->plugin_name, $this->version );
        $status = $node_checker->check_node_status( $porter_id );
        
        // Cache the result
        update_user_meta( $porter_id, '_chiral_node_status_cache', $status );
        update_user_meta( $porter_id, '_chiral_node_last_status_check', current_time( 'timestamp' ) );

        wp_send_json_success( $status );
    }

    /**
     * AJAX handler for checking all nodes status.
     *
     * @since 1.0.0
     */
    public function ajax_check_all_nodes_status() {
        check_ajax_referer( 'chiral_hub_node_check', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'chiral-hub-core' ) ) );
        }

        if ( ! class_exists( 'Chiral_Hub_Node_Checker' ) ) {
            require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-node-checker.php';
        }

        $node_checker = new Chiral_Hub_Node_Checker( $this->plugin_name, $this->version );
        $statuses = $node_checker->check_all_nodes_status( true );

        wp_send_json_success( $statuses );
    }
}
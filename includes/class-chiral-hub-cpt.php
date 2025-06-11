<?php

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * The Custom Post Type functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 */

/**
 * The Custom Post Type functionality of the plugin.
 *
 * Defines the CPT Chiral Data and its meta fields.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_CPT {

    /**
     * The CPT slug.
     *
     * @since    1.0.0
     * @access   public
     * @var      string    CPT_SLUG    The slug for Chiral Data CPT.
     */
    const CPT_SLUG = 'chiral_data';

    /**
     * The ID of this plugin.
     *
     * @since    1.1.0 // Assuming new version for this change
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.1.0 // Assuming new version for this change
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the Chiral Data Custom Post Type.
     *
     * @since    1.0.0
     */
    public function register_chiral_data_cpt() {
        $labels = array(
            'name'                  => _x( 'Chiral Data', 'Post Type General Name', 'chiral-hub-core' ),
            'singular_name'         => _x( 'Chiral Data Item', 'Post Type Singular Name', 'chiral-hub-core' ),
            'menu_name'             => __( 'Chiral Data', 'chiral-hub-core' ),
            'name_admin_bar'        => __( 'Chiral Data Item', 'chiral-hub-core' ),
            'archives'              => __( 'Chiral Data Archives', 'chiral-hub-core' ),
            'attributes'            => __( 'Chiral Data Attributes', 'chiral-hub-core' ),
            'parent_item_colon'     => __( 'Parent Chiral Data Item:', 'chiral-hub-core' ),
            'all_items'             => __( 'All Chiral Data', 'chiral-hub-core' ),
            'add_new_item'          => __( 'Add New Chiral Data Item', 'chiral-hub-core' ),
            'add_new'               => __( 'Add New', 'chiral-hub-core' ),
            'new_item'              => __( 'New Chiral Data Item', 'chiral-hub-core' ),
            'edit_item'             => __( 'Edit Chiral Data Item', 'chiral-hub-core' ),
            'update_item'           => __( 'Update Chiral Data Item', 'chiral-hub-core' ),
            'view_item'             => __( 'View Chiral Data Item', 'chiral-hub-core' ),
            'view_items'            => __( 'View Chiral Data', 'chiral-hub-core' ),
            'search_items'          => __( 'Search Chiral Data', 'chiral-hub-core' ),
            'not_found'             => __( 'Not found', 'chiral-hub-core' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'chiral-hub-core' ),
            'featured_image'        => __( 'Featured Image', 'chiral-hub-core' ),
            'set_featured_image'    => __( 'Set featured image', 'chiral-hub-core' ),
            'remove_featured_image' => __( 'Remove featured image', 'chiral-hub-core' ),
            'use_featured_image'    => __( 'Use as featured image', 'chiral-hub-core' ),
            'insert_into_item'      => __( 'Insert into item', 'chiral-hub-core' ),
            'uploaded_to_this_item' => __( 'Uploaded to this Chiral Data item', 'chiral-hub-core' ),
            'items_list'            => __( 'Chiral Data list', 'chiral-hub-core' ),
            'items_list_navigation' => __( 'Chiral Data list navigation', 'chiral-hub-core' ),
            'filter_items_list'     => __( 'Filter Chiral Data list', 'chiral-hub-core' ),
        );
        $args = array(
            'label'                 => __( 'Chiral Data Item', 'chiral-hub-core' ),
            'description'           => __( 'Represents a piece of content syndicated from a Chiral Node.', 'chiral-hub-core' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'author', 'custom-fields', 'thumbnail', 'jetpack-related-posts', 'excerpt', 'publicize' ), 
            'taxonomies'            => array(), // No native taxonomies for now
            'hierarchical'          => false,
            'public'                => true, // Needs to be public for redirection and Jetpack
            'show_ui'               => true,
            'show_in_menu'          => true, // Show as independent menu item for Porter access
            'menu_position'         => 5,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false, // No archive page, direct access redirects
            'exclude_from_search'   => false, // Keep it as false, otherwise it will not be included in related articles. Exclude results from Hub's search, by other means.
            'publicly_queryable'    => true, // Allows single view for redirection
            'rewrite'               => array( 'slug' => self::CPT_SLUG, 'with_front' => false ), // 添加URL重写规则
            'capability_type'       => array('chiral_data_item', 'chiral_data_items'),
            'map_meta_cap'          => true,
            'show_in_rest'          => true, // Crucial for API interactions
            'rest_base'             => self::CPT_SLUG,
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );
        register_post_type( self::CPT_SLUG, $args );
    }

    /**
     * Customize the admin menu for Chiral Porter users.
     * Completely hide the chiral_data menu for Porter users since they have a custom interface.
     *
     * @since 1.0.0
     */
    public function customize_porter_menu() {
        // Check if current user is a Chiral Porter
        $current_user = wp_get_current_user();
        if ( !in_array( Chiral_Hub_Roles::ROLE_SLUG, (array) $current_user->roles ) ) {
            return;
        }
        
        // Completely remove the chiral_data menu for Porter users
        // This prevents them from accessing the standard CPT admin pages which cause errors
        remove_menu_page( 'edit.php?post_type=' . self::CPT_SLUG );
    }

    /**
     * Helper to get the admin menu slug for CPT registration.
     *
     * @return string The admin menu slug.
     */
    private function get_admin_menu_slug() {
        // This should match the slug used in Chiral_Hub_Admin for the main menu page.
        return 'chiral-hub-core';
    }

    /**
     * Register meta fields for the Chiral Data CPT.
     *
     * @since    1.0.0
     */
    public function register_meta_fields() {
        $options = get_option( $this->plugin_name . '_options' );
        // Default to true (transfer mode enabled) if option not set.
        // This was previously defaulting to false in one of the iterations, ensure it's consistently handled.
        // The render_enable_hub_transfer_mode_field defaults to true if not set.
        // The sanitize_settings sets it to true if checked ('1'), false if unchecked (not present in $input).
        // So, if the option is saved as false, it will be false here. If never saved, it might not exist.
        // Let's make the default explicit here: if not set, assume transfer mode IS enabled.
        $enable_hub_transfer_mode = isset( $options['enable_hub_transfer_mode'] ) ? (bool) $options['enable_hub_transfer_mode'] : true; 

        $meta_fields = array(
            'chiral_source_url' => __('Source URL of the original content item at the Chiral Node.', 'chiral-hub-core'),
            'other_URLs' => __('Alternative URLs for this content item (JSON format).', 'chiral-hub-core'),
            '_chiral_node_id' => __('Unique ID of the Chiral Node that this data originated from.', 'chiral-hub-core'),
            '_chiral_data_original_post_id' => __('Original Post ID of this item on the Chiral Node.', 'chiral-hub-core'),
            '_chiral_data_original_title' => __('Original title of the item (optional, if different from Hub title).', 'chiral-hub-core'),
            '_chiral_data_original_categories' => __('Original categories (JSON serialized array).', 'chiral-hub-core'),
            '_chiral_data_original_tags' => __('Original tags (JSON serialized array).', 'chiral-hub-core'),
            '_chiral_data_original_featured_image_url' => __('URL of the original featured image. Hub does not download the image.', 'chiral-hub-core'),
            '_chiral_data_original_publish_date' => __('Original publish date of the item.', 'chiral-hub-core'),
            '_chiral_hub_cpt_id' => __('The ID of this CPT item on the Hub (used by Connector).', 'chiral-hub-core'), // This seems redundant if it's meta for itself, maybe for connector reference?
        );

        foreach ( $meta_fields as $meta_key => $description ) {
            $show_in_rest_for_current_meta = true; // Default to true for all meta fields, ensuring they can be set via API

            // 为 other_URLs 字段设置特殊的 sanitize callback，因为它存储 JSON 数据
            $sanitize_callback = 'sanitize_text_field';
            if ( 'other_URLs' === $meta_key ) {
                $sanitize_callback = array( $this, 'sanitize_other_urls_field' );
            } elseif ( 'chiral_source_url' === $meta_key ) {
                $sanitize_callback = 'esc_url_raw';
            }

            register_post_meta( self::CPT_SLUG, $meta_key, array(
                'type'              => 'string',
                'description'       => $description,
                'single'            => true,
                'sanitize_callback' => $sanitize_callback,
                'auth_callback'     => '__return_true',
                'show_in_rest'      => true, // Always true to allow saving via API in all cases
            ) );
        }
    }

    /**
     * 确保 REST API 响应中包含动态的 chiral_network_name
     * 
     * @since 1.0.0
     * @param WP_REST_Response $response The response object.
     * @param WP_Post         $post     Post object.
     * @param WP_REST_Request $request  Request object.
     * @return WP_REST_Response Modified response.
     */
    public function inject_network_name_in_rest_response( $response, $post, $request ) {
        // 只处理我们的 CPT 类型
        if ( $post->post_type !== self::CPT_SLUG ) {
            return $response;
        }

        $data = $response->get_data();
        
        // 确保 meta 字段存在
        if ( ! isset( $data['meta'] ) ) {
            $data['meta'] = array();
        }

        // 从全局设置中获取网络名称并注入到响应中
        $options = get_option( $this->plugin_name . '_options' );
        $network_name = isset( $options['network_name'] ) ? sanitize_text_field( $options['network_name'] ) : '';
        
        $data['meta']['chiral_network_name'] = $network_name;

        $response->set_data( $data );
        return $response;
    }

    /**
     * 允许在REST API中访问的公共metadata字段
     * 
     * @since 1.0.0
     * @param array  $allowed_meta_keys   Array of allowed meta keys.
     * @param string $post_type          Post type.
     * @param mixed  $object_subtype     Object subtype.
     * @return array Modified array of allowed meta keys.
     */
    public function filter_allowed_public_metadata( $allowed_meta_keys, $post_type = null, $object_subtype = null ) {
        try {
            // 确保 $allowed_meta_keys 是数组
            if ( ! is_array( $allowed_meta_keys ) ) {
                $allowed_meta_keys = array();
            }

            // 如果没有传递post_type参数（Jetpack调用方式），则应用到所有类型
            // 如果传递了post_type参数，则只应用到chiral_data类型
            $should_apply_filter = false;
            
            if ( $post_type === null && $object_subtype === null ) {
                // Jetpack调用方式，没有传递post_type参数，应用过滤器
                $should_apply_filter = true;
            } elseif ( ( self::CPT_SLUG === $post_type || self::CPT_SLUG === $object_subtype ) && $post_type !== null ) {
                // WordPress核心调用方式，且是我们的CPT类型
                $should_apply_filter = true;
            }

            if ( $should_apply_filter ) {
                $options = get_option( $this->plugin_name . '_options' );
                $enable_hub_transfer_mode = isset( $options['enable_hub_transfer_mode'] ) ? (bool) $options['enable_hub_transfer_mode'] : true;

                // chiral_network_name 应该始终可见
                if ( ! in_array( 'chiral_network_name', $allowed_meta_keys ) ) {
                    $allowed_meta_keys[] = 'chiral_network_name';
                }

                if ( ! $enable_hub_transfer_mode ) {
                    // 非转发模式：both chiral_source_url and other_URLs 应该可见
                    if ( ! in_array( 'chiral_source_url', $allowed_meta_keys ) ) {
                        $allowed_meta_keys[] = 'chiral_source_url';
                    }
                    if ( ! in_array( 'other_URLs', $allowed_meta_keys ) ) {
                        $allowed_meta_keys[] = 'other_URLs';
                    }
                } else {
                    // 转发模式：both chiral_source_url and other_URLs 不应该可见
                    $chiral_key_index = array_search( 'chiral_source_url', $allowed_meta_keys );
                    if ( $chiral_key_index !== false ) {
                        unset( $allowed_meta_keys[$chiral_key_index] );
                    }
                    
                    $other_urls_key_index = array_search( 'other_URLs', $allowed_meta_keys );
                    if ( $other_urls_key_index !== false ) {
                        unset( $allowed_meta_keys[$other_urls_key_index] );
                    }
                    
                    $allowed_meta_keys = array_values( $allowed_meta_keys );
                }

                // Debug logging (commented out for production)
                // error_log( '[Chiral Hub CPT] Public metadata filtered. Transfer mode: ' . ( $enable_hub_transfer_mode ? 'ON' : 'OFF' ) . ', Post type: ' . ( $post_type ?: 'null' ) );

            }
        } catch ( Exception $e ) {
            // Error logging kept for troubleshooting
            error_log( '[Chiral Hub CPT] Error in filter_allowed_public_metadata: ' . $e->getMessage() );
            if ( ! is_array( $allowed_meta_keys ) ) {
                $allowed_meta_keys = array();
            }
        }

        return $allowed_meta_keys;
    }

    /**
     * Sanitize the other_URLs field which contains JSON data.
     *
     * @since 1.0.0
     * @param string $value The value to sanitize.
     * @return string Sanitized JSON string or empty string on error.
     */
    public function sanitize_other_urls_field( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        // 如果已经是JSON字符串，先解码验证
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                // 验证并清理JSON数据
                $sanitized = array();
                if ( isset( $decoded['source'] ) ) {
                    $sanitized['source'] = esc_url_raw( $decoded['source'] );
                }
                return wp_json_encode( $sanitized );
            }
        }

        // 如果是数组，直接处理
        if ( is_array( $value ) ) {
            $sanitized = array();
            if ( isset( $value['source'] ) ) {
                $sanitized['source'] = esc_url_raw( $value['source'] );
            }
            return wp_json_encode( $sanitized );
        }

        // 无法处理的情况返回空字符串
        return '';
    }
}
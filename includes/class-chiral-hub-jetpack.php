<?php

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * Handles Jetpack integration for the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 */

/**
 * Handles Jetpack integration, specifically for the Related Posts feature.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_Jetpack {

    /**
     * Check if Jetpack is active and the Related Posts module is enabled.
     *
     * @since  1.0.0
     * @return bool True if active and module enabled, false otherwise.
     */
    public function is_jetpack_related_posts_active() {
        if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'related-posts' ) ) {
            // Also check if the site is connected to WordPress.com, as Related Posts requires this.
            if ( Jetpack::is_active() ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get related posts for a given post ID using Jetpack's Related Posts feature.
     *
     * @since  1.0.0
     * @param  int   $post_id The ID of the post to find related content for.
     * @param  array $args    Optional. Arguments to pass to Jetpack_RelatedPosts::get_for_post_id().
     *                        Example: array( 'size' => 5 ) to get 5 related posts.
     * @return array|WP_Error An array of related post objects on success, or WP_Error on failure or if module not active.
     */
    public function get_related_posts_for_id( $post_id, $args = array() ) {
        if ( ! $this->is_jetpack_related_posts_active() ) {
            return new WP_Error(
                'jetpack_related_posts_not_active',
                __( 'Jetpack Related Posts module is not active or Jetpack is not connected to WordPress.com.', 'chiral-hub-core' )
            );
        }

        if ( ! class_exists( 'Jetpack_RelatedPosts' ) ) {
            return new WP_Error(
                'jetpack_relatedposts_class_missing',
                __( 'Jetpack_RelatedPosts class not found. Ensure Jetpack is fully loaded.', 'chiral-hub-core' )
            );
        }

        // Ensure the CPT is supported by Jetpack Related Posts
        // This should be handled by adding 'jetpack-related-posts' to the CPT's 'supports' array during registration.
        // We can double-check here if needed, but it's better to configure the CPT correctly.
        $cpt_slug = Chiral_Hub_CPT::CPT_SLUG;
        
        // Use the correct WordPress function to check post type support
        if ( ! post_type_supports( $cpt_slug, 'jetpack-related-posts' ) ) {
            // This is a configuration issue with the CPT registration.
            // error_log("Chiral Hub: Jetpack Related Posts support not declared for CPT '{$cpt_slug}'.");
            // It might still work if Jetpack's general settings allow it for all CPTs, but explicit support is better.
        }

        $defaults = array(
            'size' => (int) get_option( 'chiral_hub_core_default_related_count', 5 ),
        );
        $args = wp_parse_args( $args, $defaults );

        // Jetpack_RelatedPosts::get_for_post_id() returns an array of WP_Post like objects
        // or an empty array if no related posts are found, or false on error.
        $related_posts = Jetpack_RelatedPosts::init()->get_for_post_id( $post_id, $args );

        if ( $related_posts === false ) {
            // This can happen if the index is not yet built or other issues.
            return new WP_Error(
                'jetpack_get_related_failed',
                __( 'Jetpack failed to retrieve related posts. The index might still be building or there could be a connection issue.', 'chiral-hub-core' )
            );
        }

        return $related_posts; // This will be an array (possibly empty)
    }

    /**
     * Adds 'jetpack-related-posts' support to our CPT if not already present.
     * This is a fallback/ensure mechanism. Ideally, it's set during CPT registration.
     *
     * @since 1.0.0
     * @param array $args       CPT registration arguments.
     * @param string $post_type Post type slug.
     * @return array Modified CPT arguments.
     */
    public static function add_cpt_jetpack_support( $args, $post_type ) {
        if ( $post_type === Chiral_Hub_CPT::CPT_SLUG ) {
            if ( isset( $args['supports'] ) && is_array( $args['supports'] ) ) {
                if ( ! in_array( 'jetpack-related-posts', $args['supports'] ) ) {
                    $args['supports'][] = 'jetpack-related-posts';
                }
            } else {
                $args['supports'] = array( 'jetpack-related-posts' );
            }
        }
        return $args;
    }

    /**
     * Filters the allowed post types for REST API access.
     * This is CRITICAL for Jetpack Related Posts to work with custom post types.
     *
     * @since 1.0.0
     * @param array $allowed_post_types Array of allowed post types.
     * @return array Modified array of allowed post types.
     */
    public function filter_rest_api_allowed_post_types( $allowed_post_types ) {
        if ( ! is_array( $allowed_post_types ) ) {
            $allowed_post_types = array( 'post', 'page' );
        }
        
        if ( ! in_array( Chiral_Hub_CPT::CPT_SLUG, $allowed_post_types ) ) {
            $allowed_post_types[] = Chiral_Hub_CPT::CPT_SLUG;
        }
        
        return $allowed_post_types;
    }

    /**
     * Filters the post types that Jetpack Related Posts considers.
     *
     * @since 1.0.0
     * @param array|string $post_types Array of post types or single post type string.
     * @param int $post_id The post ID for which we are retrieving related posts.
     * @return array Modified array of post types.
     */
    public function filter_jetpack_related_post_types( $post_types, $post_id ) {
        if ( ! is_array( $post_types ) ) {
            $post_types = array( $post_types );
        }
        
        if ( ! in_array( Chiral_Hub_CPT::CPT_SLUG, $post_types ) ) {
            $post_types[] = Chiral_Hub_CPT::CPT_SLUG;
        }
        
        return $post_types;
    }

    /**
     * Enables related posts for our CPT.
     *
     * @since 1.0.0
     * @param bool $enabled Whether related posts are enabled for the current request.
     * @return bool Modified enabled status.
     */
    public function enable_related_posts_for_cpt( $enabled ) {
        global $post;
        
        if ( $post && $post->post_type === Chiral_Hub_CPT::CPT_SLUG ) {
            return true;
        }
        
        return $enabled;
    }

    /**
     * Force register filters on init to ensure they are properly loaded.
     *
     * @since 1.0.0
     */
    public function force_register_filters() {
        // 不要移除所有现有过滤器，而是确保我们的过滤器被正确添加
        // remove_all_filters( 'rest_api_allowed_post_types' );
        // remove_all_filters( 'jetpack_relatedposts_filter_post_type' );
        
        // 检查过滤器是否已经添加，避免重复添加
        if ( ! has_filter( 'rest_api_allowed_post_types', array( $this, 'filter_rest_api_allowed_post_types' ) ) ) {
            add_filter( 'rest_api_allowed_post_types', array( $this, 'filter_rest_api_allowed_post_types' ), 999 );
        }
        
        if ( ! has_filter( 'jetpack_relatedposts_filter_post_type', array( $this, 'filter_jetpack_related_post_types' ) ) ) {
            add_filter( 'jetpack_relatedposts_filter_post_type', array( $this, 'filter_jetpack_related_post_types' ), 999, 2 );
        }
        
        // Enable related posts for our CPT
        if ( ! has_filter( 'jetpack_relatedposts_filter_enabled_for_request', array( $this, 'enable_related_posts_for_cpt' ) ) ) {
            add_filter( 'jetpack_relatedposts_filter_enabled_for_request', array( $this, 'enable_related_posts_for_cpt' ), 999 );
        }
        
        // 应急备用过滤器
        if ( ! has_filter( 'rest_api_allowed_post_types', array( $this, 'emergency_rest_api_filter' ) ) ) {
            add_filter( 'rest_api_allowed_post_types', array( $this, 'emergency_rest_api_filter' ), 9999 );
        }
        
        if ( ! has_filter( 'jetpack_relatedposts_filter_post_type', array( $this, 'emergency_jetpack_filter' ) ) ) {
            add_filter( 'jetpack_relatedposts_filter_post_type', array( $this, 'emergency_jetpack_filter' ), 9999, 2 );
        }
        
        // CRITICAL: Configure Jetpack Sync metadata whitelist
        if ( ! has_filter( 'jetpack_sync_post_meta_whitelist', array( $this, 'configure_jetpack_sync_metadata' ) ) ) {
            add_filter( 'jetpack_sync_post_meta_whitelist', array( $this, 'configure_jetpack_sync_metadata' ), 10 );
        }
    }

    /**
     * Emergency fallback filter for REST API allowed post types.
     *
     * @since 1.0.0
     * @param array $allowed_post_types Array of allowed post types.
     * @return array Modified array of allowed post types.
     */
    public function emergency_rest_api_filter( $allowed_post_types ) {
        if ( ! is_array( $allowed_post_types ) ) {
            $allowed_post_types = array( 'post', 'page' );
        }
        
        if ( ! in_array( Chiral_Hub_CPT::CPT_SLUG, $allowed_post_types ) ) {
            $allowed_post_types[] = Chiral_Hub_CPT::CPT_SLUG;
        }
        
        return $allowed_post_types;
    }

    /**
     * Emergency fallback filter for Jetpack related posts post types.
     *
     * @since 1.0.0
     * @param array|string $post_types Array of post types or single post type string.
     * @param int $post_id The post ID for which we are retrieving related posts.
     * @return array Modified array of post types.
     */
    public function emergency_jetpack_filter( $post_types, $post_id ) {
        if ( ! is_array( $post_types ) ) {
            $post_types = array( $post_types );
        }
        
        if ( ! in_array( Chiral_Hub_CPT::CPT_SLUG, $post_types ) ) {
            $post_types[] = Chiral_Hub_CPT::CPT_SLUG;
        }
        
        return $post_types;
    }

    /**
     * Configure Jetpack Sync metadata whitelist to include chiral_network_name
     * 
     * @since 1.0.0
     * @param array $meta_whitelist Current Jetpack sync metadata whitelist.
     * @return array Modified metadata whitelist.
     */
    public function configure_jetpack_sync_metadata( $meta_whitelist = array() ) {
        // Ensure $meta_whitelist is an array
        if ( ! is_array( $meta_whitelist ) ) {
            $meta_whitelist = array();
        }

        // These are meta keys that are safe to sync and might be used by the Node
        $chiral_meta_keys = array(
            'chiral_source_url',          // Original source URL from Node
            'other_URLs',                 // Other relevant URLs, potentially including the canonical one if different
            '_chiral_node_id',            // ID of the source Node
            '_chiral_data_original_post_id', // Original post ID on the Node
            '_chiral_data_original_title',
            '_chiral_data_original_categories',
            '_chiral_data_original_tags',
            '_chiral_data_original_featured_image_url',
            '_chiral_data_original_publish_date',
            '_chiral_hub_cpt_id', // The Hub CPT ID itself, for potential back-references or API use by Node
            'chiral_network_name', // Network name for batch updates
        );

        foreach ( $chiral_meta_keys as $key ) {
            if ( ! in_array( $key, $meta_whitelist ) ) {
                $meta_whitelist[] = $key;
            }
        }
        
        // Additionally, let's whitelist some standard Jetpack/WP meta if they aren't already.
        $standard_meta_keys = array(
            '_jetpack_related_posts_cache', // Jetpack specific
            '_thumbnail_id',                // Standard WordPress for featured image
        );

        foreach ( $standard_meta_keys as $key ) {
            if ( ! in_array( $key, $meta_whitelist ) ) {
                $meta_whitelist[] = $key;
            }
        }

        return $meta_whitelist;
    }

    /**
     * 为Jetpack同步提供动态的metadata值
     * 
     * @since 1.0.0
     * @param array $meta_values 要同步的metadata值
     * @param int   $post_id     文章ID
     * @return array 修改后的metadata值
     */
    public function provide_dynamic_metadata_for_jetpack_sync( $meta_values, $post_id ) {
        // Debug logging when WP_DEBUG is enabled
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Chiral Hub Jetpack Sync] provide_dynamic_metadata_for_jetpack_sync called for post ID: ' . $post_id );
        }

        $post = get_post( $post_id );
        
        // 只处理我们的CPT类型
        if ( ! $post || $post->post_type !== Chiral_Hub_CPT::CPT_SLUG ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $post ) {
                error_log( '[Chiral Hub Jetpack Sync] Skipping post ' . $post_id . ' (post_type: ' . $post->post_type . ')' );
            }
            return $meta_values;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Chiral Hub Jetpack Sync] Processing chiral_data post ' . $post_id );
            error_log( '[Chiral Hub Jetpack Sync] Original meta_values: ' . wp_json_encode( $meta_values ) );
        }

        // 确保 Chiral_Hub_CPT 类已加载
        if ( ! class_exists( 'Chiral_Hub_CPT' ) ) {
            require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-cpt.php';
        }

        // 从全局设置中获取网络名称
        $options = get_option( 'chiral-hub-core_options' );
        $network_name = isset( $options['network_name'] ) ? sanitize_text_field( $options['network_name'] ) : '';

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Chiral Hub Jetpack Sync] Network name from settings: "' . $network_name . '"' );
        }

        // 如果metadata中没有chiral_network_name或者值为空，则注入动态值
        if ( ! isset( $meta_values['chiral_network_name'] ) || empty( $meta_values['chiral_network_name'] ) ) {
            $meta_values['chiral_network_name'] = array( $network_name );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Chiral Hub Jetpack Sync] INJECTED chiral_network_name: "' . $network_name . '"' );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Chiral Hub Jetpack Sync] chiral_network_name already exists in meta_values: ' . wp_json_encode( $meta_values['chiral_network_name'] ) );
            }
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Chiral Hub Jetpack Sync] Final meta_values: ' . wp_json_encode( $meta_values ) );
        }

        return $meta_values;
    }

    /**
     * Debug Jetpack configuration (only runs when WP_DEBUG is true).
     *
     * @since 1.0.0
     */
    public function debug_jetpack_configuration() {
        error_log( '[Chiral Hub Jetpack Debug] Starting Jetpack configuration debug...' );
        
        // 检查 Jetpack 状态
        if ( class_exists( 'Jetpack' ) ) {
            if ( method_exists( 'Jetpack', 'is_module_active' ) ) {
                $related_posts_active = Jetpack::is_module_active( 'related-posts' );
                error_log( '[Chiral Hub Jetpack Debug] Related Posts module active: ' . ( $related_posts_active ? 'YES' : 'NO' ) );
            }
            
            if ( method_exists( 'Jetpack', 'is_active' ) ) {
                $jetpack_connected = Jetpack::is_active();
                error_log( '[Chiral Hub Jetpack Debug] Jetpack connected: ' . ( $jetpack_connected ? 'YES' : 'NO' ) );
            }
        } else {
            error_log( '[Chiral Hub Jetpack Debug] Jetpack class not found' );
        }

        // 检查我们的 CPT 文章
        $test_posts = get_posts( array(
            'post_type'   => 'chiral_data',
            'post_status' => array( 'publish', 'pending' ),
            'numberposts' => 3,
            'meta_key'    => 'chiral_source_url', // 确保有实际的metadata
        ) );

        if ( ! empty( $test_posts ) ) {
            $test_post_id = $test_posts[0]->ID;
            error_log( '[Chiral Hub Jetpack Debug] Testing with post ID: ' . $test_post_id );

            // 获取网络名称
            $options = get_option( 'chiral-hub-core_options' );
            $network_name = isset( $options['network_name'] ) ? sanitize_text_field( $options['network_name'] ) : '';
            
            if ( $network_name ) {
                error_log( '[Chiral Hub Jetpack Debug] Network name from settings: "' . $network_name . '"' );
            } else {
                error_log( '[Chiral Hub Jetpack Debug] No network name configured' );
            }
        } else {
            error_log( '[Chiral Hub Jetpack Debug] No chiral_data posts found for testing' );
        }

        // 检查 Jetpack 同步白名单
        $whitelist = apply_filters( 'jetpack_sync_post_meta_whitelist', array() );
        if ( is_array( $whitelist ) && in_array( 'chiral_network_name', $whitelist ) ) {
            error_log( '[Chiral Hub Jetpack Debug] SUCCESS: chiral_network_name is in Jetpack sync whitelist' );
        } else {
            error_log( '[Chiral Hub Jetpack Debug] ERROR: chiral_network_name NOT in Jetpack sync whitelist' );
        }

        error_log( '[Chiral Hub Jetpack Debug] Jetpack configuration debug completed.' );
    }
}
<?php

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * Handles redirection logic for the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 */

/**
 * Handles redirection logic for the plugin.
 *
 * This class is responsible for redirecting users from a `chiral_data` post
 * on the Hub to the original source URL, if configured to do so.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_Redirect {

    /**
     * Initialize the redirection hooks.
     *
     * @since    1.0.0
     */
    public function init() {
        // Hook into multiple points to ensure we catch the redirect
        // 'parse_request' runs very early, before WordPress determines if it's a 404
        add_action( 'parse_request', array( $this, 'early_redirect_check' ) );
        // 'template_redirect' as a backup
        add_action( 'template_redirect', array( $this, 'maybe_redirect_chiral_data_post' ) );
    }

    /**
     * Early redirect check using parse_request hook.
     * This runs before WordPress determines if it's a 404.
     *
     * @since    1.0.0
     * @param WP $wp The WordPress environment instance.
     */
    public function early_redirect_check( $wp ) {
        // Debug logging removed for production
        // if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        //     error_log( "Chiral Hub: Early redirect check triggered. Request: " . $_SERVER['REQUEST_URI'] );
        //     error_log( "Chiral Hub: Query vars: " . print_r( $wp->query_vars, true ) );
        // }

        // 检查是否是chiral_data的单个文章请求
        if ( isset( $wp->query_vars['post_type'] ) && $wp->query_vars['post_type'] === Chiral_Hub_CPT::CPT_SLUG ) {
            if ( isset( $wp->query_vars['name'] ) ) {
                // 通过post_name查找文章
                $post = get_page_by_path( $wp->query_vars['name'], OBJECT, Chiral_Hub_CPT::CPT_SLUG );
                
                if ( $post ) {
                    // if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    //     error_log( "Chiral Hub: Found chiral_data post via early check: ID {$post->ID}, slug: {$post->post_name}" );
                    // }
                    
                    $this->perform_redirect_if_needed( $post->ID );
                }
            }
        }
        
        // 也检查直接的chiral_data URL模式
        $request_uri = '';
        if ( isset( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri = wp_unslash( sanitize_text_field( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        }
        
        if ( ! empty( $request_uri ) && preg_match( '#/' . Chiral_Hub_CPT::CPT_SLUG . '/([^/]+)/?$#', $request_uri, $matches ) ) {
            $slug = isset( $matches[1] ) ? sanitize_title( $matches[1] ) : '';
            
            if ( ! empty( $slug ) ) {
                // if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                //     error_log( "Chiral Hub: Detected chiral_data URL pattern, slug: {$slug}" );
                // }
                
                $post = get_page_by_path( $slug, OBJECT, Chiral_Hub_CPT::CPT_SLUG );
                if ( $post ) {
                    // if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    //     error_log( "Chiral Hub: Found post by slug in early check: ID {$post->ID}" );
                    // }
                    
                    $this->perform_redirect_if_needed( $post->ID );
                }
            }
        }
    }

    /**
     * Perform redirect if needed for a given post ID.
     *
     * @since    1.0.0
     * @param int $post_id The post ID to check for redirect.
     */
    private function perform_redirect_if_needed( $post_id ) {
        // 添加调试日志
        // if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        //     error_log( "Chiral Hub: Hub transfer mode check is now bypassed for redirection." );
        // }
        
        // 重定向逻辑始终执行，避免 Chiral_Data 文章正文在 Hub 上对外显示。
        $source_url = get_post_meta( $post_id, 'chiral_source_url', true );
        
        // 添加调试日志
        // if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        //     error_log( "Chiral Hub: Retrieved source URL for post {$post_id}: '{$source_url}'" );
        // }

        if ( ! empty( $source_url ) && filter_var( $source_url, FILTER_VALIDATE_URL ) ) {
            // 添加调试日志
            // if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            //     error_log( "Chiral Hub: Redirecting post {$post_id} to {$source_url}" );
            // }
            
            // Perform the redirect.
            wp_redirect( esc_url_raw( $source_url ), 301 );
            exit;
        } else {
            // 启用错误日志并添加更详细的信息
            // error_log( "Chiral Hub: Attempted redirect for post ID {$post_id} but source URL '{$source_url}' is missing or invalid." );
            
            // 添加额外的调试信息
            // if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            //     $is_empty = empty( $source_url );
            //     $is_valid_url = filter_var( $source_url, FILTER_VALIDATE_URL );
            //     error_log( "Chiral Hub: Debug - URL empty: " . ( $is_empty ? 'yes' : 'no' ) . ", URL valid: " . ( $is_valid_url ? 'yes' : 'no' ) );
                
            // 检查所有元数据
            // $all_meta = get_post_meta( $post_id );
            // error_log( "Chiral Hub: All meta for post {$post_id}: " . print_r( $all_meta, true ) );
            // }
        }
    }

    /**
     * Checks if the current request is for a single `chiral_data` post and redirects if necessary.
     *
     * @since    1.0.0
     */
    public function maybe_redirect_chiral_data_post() {
        // Only proceed if viewing a single post of the 'chiral_data' CPT.
        if ( is_singular( Chiral_Hub_CPT::CPT_SLUG ) ) {
            $post_id = get_queried_object_id();
            
            // 添加调试日志
            // if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            //     error_log( "Chiral Hub: Template redirect check for chiral_data post ID {$post_id}" );
            // }
            
            // 使用共享的重定向逻辑
            $this->perform_redirect_if_needed( $post_id );
        }
    }
}
<?php

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * The node status checker class.
 *
 * This class handles checking the status of connected Chiral Nodes,
 * including their plugin version and related articles display status.
 *
 * @since      1.0.0
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_Node_Checker {

    /**
     * The plugin name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The plugin name.
     */
    private $plugin_name;

    /**
     * The plugin version.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The plugin version.
     */
    private $version;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param string $plugin_name The plugin name.
     * @param string $version The plugin version.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Check the status of a single node.
     *
     * @since 1.0.0
     * @param int $porter_id The porter user ID.
     * @return array Node status information.
     */
    public function check_node_status( $porter_id ) {
        $porter = get_user_by( 'ID', $porter_id );
        if ( ! $porter ) {
            return array(
                'status' => 'error',
                'message' => __( 'Invalid Porter ID', 'chiral-hub-core' ),
            );
        }

        $node_id = get_user_meta( $porter_id, '_chiral_node_id', true );
        if ( empty( $node_id ) ) {
            return array(
                'status' => 'error',
                'message' => __( 'Node ID not set', 'chiral-hub-core' ),
            );
        }

        // Get node URL from user meta or construct from node_id
        $node_url = $this->get_node_url_from_porter( $porter_id );
        if ( empty( $node_url ) ) {
            return array(
                'status' => 'error',
                'message' => __( 'Unable to determine node URL', 'chiral-hub-core' ),
            );
        }

        // Make API request to node
        $api_url = trailingslashit( $node_url ) . 'wp-json/chiral-connector/v1/node-status';
        
        $response = wp_remote_get( $api_url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'Chiral-Hub-Core/' . $this->version,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'status' => 'disconnected',
                // translators: %s: Error message from WordPress HTTP API
                'message' => sprintf( __( 'Connection failed: %s', 'chiral-hub-core' ), $response->get_error_message() ),
                'node_url' => $node_url,
                'last_checked' => current_time( 'timestamp' ),
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return array(
                'status' => 'disconnected',
                // translators: %d: HTTP response code (e.g., 404, 500)
                'message' => sprintf( __( 'HTTP error: %d', 'chiral-hub-core' ), $response_code ),
                'node_url' => $node_url,
                'last_checked' => current_time( 'timestamp' ),
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return array(
                'status' => 'disconnected',
                'message' => __( 'Invalid API response', 'chiral-hub-core' ),
                'node_url' => $node_url,
                'last_checked' => current_time( 'timestamp' ),
            );
        }

        // Determine status based on related articles setting
        $status = 'active';
        $message = __( 'Node is running normally, related articles feature enabled', 'chiral-hub-core' );
        
        if ( ! $data['related_articles_enabled'] ) {
            $status = 'inactive';
            $inactive_duration = '';
            
            if ( ! empty( $data['last_disabled_time'] ) ) {
                $disabled_time = intval( $data['last_disabled_time'] );
                $current_time = current_time( 'timestamp' );
                $duration = $current_time - $disabled_time;
                $inactive_duration = $this->format_duration( $duration );
            }
            
            $message = $inactive_duration 
                // translators: %s: Time duration (e.g., "2 hours", "30 minutes")
                ? sprintf( __( 'Node is in dormant state, related articles feature disabled for %s', 'chiral-hub-core' ), $inactive_duration )
                : __( 'Node is in dormant state, related articles feature disabled', 'chiral-hub-core' );
        }

        return array(
            'status' => $status,
            'message' => $message,
            'plugin_version' => isset( $data['plugin_version'] ) ? $data['plugin_version'] : __( 'Unknown', 'chiral-hub-core' ),
            'related_articles_enabled' => $data['related_articles_enabled'],
            'last_disabled_time' => isset( $data['last_disabled_time'] ) ? $data['last_disabled_time'] : null,
            'node_url' => $node_url,
            'last_checked' => current_time( 'timestamp' ),
        );
    }

    /**
     * Check the status of all nodes.
     *
     * @since 1.0.0
     * @param bool $force_check Whether to force check even if recently checked.
     * @return array Array of node statuses.
     */
    public function check_all_nodes_status( $force_check = false ) {
        if ( ! class_exists( 'Chiral_Hub_Roles' ) ) {
            require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-roles.php';
        }

        $porters = get_users( array(
            'role' => Chiral_Hub_Roles::ROLE_SLUG,
            'fields' => array( 'ID' ),
        ) );

        $results = array();
        $delay_between_requests = 2; // 2 seconds delay between requests

        foreach ( $porters as $porter ) {
            $porter_id = $porter->ID;
            
            // Check if we need to skip this check due to rate limiting
            if ( ! $force_check ) {
                $last_check = get_user_meta( $porter_id, '_chiral_node_last_status_check', true );
                if ( $last_check && ( current_time( 'timestamp' ) - $last_check ) < 3600 ) {
                    // Skip if checked within the last hour
                    $cached_status = get_user_meta( $porter_id, '_chiral_node_status_cache', true );
                    if ( $cached_status ) {
                        $results[ $porter_id ] = $cached_status;
                        continue;
                    }
                }
            }

            $status = $this->check_node_status( $porter_id );
            $results[ $porter_id ] = $status;

            // Cache the result
            update_user_meta( $porter_id, '_chiral_node_status_cache', $status );
            update_user_meta( $porter_id, '_chiral_node_last_status_check', current_time( 'timestamp' ) );

            // Add delay between requests to avoid overwhelming servers
            if ( count( $porters ) > 1 ) {
                sleep( $delay_between_requests );
            }
        }

        return $results;
    }

    /**
     * Get node URL from porter user meta.
     *
     * @since 1.0.0
     * @param int $porter_id The porter user ID.
     * @return string|false The node URL or false if not found.
     */
    private function get_node_url_from_porter( $porter_id ) {
        // Try to get URL from user meta first
        $node_url = get_user_meta( $porter_id, '_chiral_node_url', true );
        
        if ( ! empty( $node_url ) ) {
            return $node_url;
        }

        // If not found, try to get from recent synced posts
        if ( ! class_exists( 'Chiral_Hub_CPT' ) ) {
            require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-cpt.php';
        }

        $recent_posts = get_posts( array(
            'post_type' => Chiral_Hub_CPT::CPT_SLUG,
            'author' => $porter_id,
            'posts_per_page' => 1,
            'meta_key' => 'chiral_source_url',
            'orderby' => 'date',
            'order' => 'DESC',
        ) );

        if ( ! empty( $recent_posts ) ) {
            $source_url = get_post_meta( $recent_posts[0]->ID, 'chiral_source_url', true );
            if ( $source_url ) {
                $parsed_url = wp_parse_url( $source_url );
                if ( $parsed_url && isset( $parsed_url['scheme'] ) && isset( $parsed_url['host'] ) ) {
                    $node_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
                    if ( isset( $parsed_url['port'] ) ) {
                        $node_url .= ':' . $parsed_url['port'];
                    }
                    
                    // Cache the URL for future use
                    update_user_meta( $porter_id, '_chiral_node_url', $node_url );
                    
                    return $node_url;
                }
            }
        }

        return false;
    }

    /**
     * Format duration in human readable format.
     *
     * @since 1.0.0
     * @param int $seconds Duration in seconds.
     * @return string Formatted duration.
     */
    private function format_duration( $seconds ) {
        if ( $seconds < 60 ) {
            // translators: %d: Number of seconds
            return sprintf( __( '%d seconds ago', 'chiral-hub-core' ), $seconds );
        } elseif ( $seconds < 3600 ) {
            $minutes = floor( $seconds / 60 );
            // translators: %d: Number of minutes
            return sprintf( __( '%d minutes ago', 'chiral-hub-core' ), $minutes );
        } elseif ( $seconds < 86400 ) {
            $hours = floor( $seconds / 3600 );
            // translators: %d: Number of hours
            return sprintf( __( '%d hours ago', 'chiral-hub-core' ), $hours );
        } else {
            $days = floor( $seconds / 86400 );
            // translators: %d: Number of days
            return sprintf( __( '%d days ago', 'chiral-hub-core' ), $days );
        }
    }

    /**
     * Schedule daily node status checks.
     *
     * @since 1.0.0
     */
    public function schedule_daily_checks() {
        if ( ! wp_next_scheduled( 'chiral_hub_daily_node_check' ) ) {
            wp_schedule_event( time(), 'daily', 'chiral_hub_daily_node_check' );
        }
    }

    /**
     * Unschedule daily node status checks.
     *
     * @since 1.0.0
     */
    public function unschedule_daily_checks() {
        wp_clear_scheduled_hook( 'chiral_hub_daily_node_check' );
    }

    /**
     * Handle daily node status check cron job.
     *
     * @since 1.0.0
     */
    public function handle_daily_node_check() {
        // Stagger the checks throughout the day to avoid server load
        $random_delay = wp_rand( 0, 3600 ); // Random delay up to 1 hour
        wp_schedule_single_event( time() + $random_delay, 'chiral_hub_staggered_node_check' );
    }

    /**
     * Handle staggered node status check.
     *
     * @since 1.0.0
     */
    public function handle_staggered_node_check() {
        $this->check_all_nodes_status( false );
    }
} 
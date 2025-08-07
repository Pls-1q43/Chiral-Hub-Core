<?php

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * The REST API functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 */

/**
 * The REST API functionality of the plugin.
 *
 * Defines custom REST API endpoints for Chiral Hub.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_REST_API {

    /**
     * The namespace for the custom REST API.
     *
     * @since    1.0.0
     * @access   public
     * @var      string    API_NAMESPACE    The namespace for the API.
     */
    const API_NAMESPACE = 'chiral-network/v1';

    /**
     * The main plugin name, used to get options.
     *
     * @since 1.X.X
     * @access private
     * @var string
     */
    private $plugin_name;

    /**
     * Constructor - can be used to store plugin_name if passed from main class.
     *
     * @since 1.X.X
     * @param string $plugin_name The plugin name/slug.
     */
    public function __construct( $plugin_name = 'chiral-hub-core' ) {
        $this->plugin_name = $plugin_name;
        // It's common to add hooks in the constructor or a dedicated 'hooks' method called by the loader
        // add_action( 'rest_api_init', array( $this, 'register_routes' ) ); // This should be done by the loader
        // add_filter( 'rest_prepare_chiral_data', array( $this, 'control_source_url_visibility_in_response' ), 10, 3 ); // This should be done by the loader
    }

    /**
     * Register the REST API routes.
     *
     * @since    1.0.0
     */
    public function register_routes() {
        register_rest_route( self::API_NAMESPACE, '/related-data', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array( $this, 'get_related_data' ),
            'args'     => array(
                'source_url' => array(
                    'required' => true,
                    'type'     => 'string',
                    'format'   => 'uri',
                    'description' => __( 'The source URL of the content item for which to find related data.', 'chiral-hub-core' ),
                    'validate_callback' => function($param, $request, $key) {
                        return filter_var($param, FILTER_VALIDATE_URL) !== false;
                    }
                ),
                'requesting_node_id' => array(
                    'required' => true,
                    'type'     => 'string',
                    'description' => __( 'The unique ID of the Chiral Node making the request.', 'chiral-hub-core' ),
                     'sanitize_callback' => 'sanitize_text_field',
                ),
                'count' => array(
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 5,
                    'description' => __( 'Number of related items to return.', 'chiral-hub-core' ),
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param) && $param > 0 && $param <= 20;
                    }
                ),
            ),
            'permission_callback' => array( $this, 'can_access_related_data' ),
        ) );

        register_rest_route( self::API_NAMESPACE, '/ping', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array( $this, 'ping_endpoint' ),
            'permission_callback' => array( $this, 'can_access_ping' ),
        ) );

        // Lightweight endpoint for JS clients - only returns related post IDs (1.0.2+)
        register_rest_route( self::API_NAMESPACE, '/related-post-ids', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array( $this, 'get_related_post_ids' ),
            'args'     => array(
                'cpt_id' => array(
                    'required' => true,
                    'type'     => 'integer',
                    'description' => __( 'The chiral_data CPT ID to get related posts for.', 'chiral-hub-core' ),
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'count' => array(
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 5,
                    'description' => __( 'Number of related post IDs to return.', 'chiral-hub-core' ),
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param) && $param > 0 && $param <= 20;
                    }
                ),
            ),
            'permission_callback' => '__return_true', // No validation needed for public endpoint
        ) );
    }

    /**
     * Permission callback for the related-data endpoint.
     * Ensures the request is made by an authenticated Chiral Porter.
     *
     * @since  1.0.0
     * @param  WP_REST_Request $request Full details about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error object otherwise.
     */
    public function can_access_related_data( WP_REST_Request $request ) {
        // Using Application Passwords, WordPress handles authentication.
        // We just need to check if the authenticated user is a Chiral Porter.
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            return new WP_Error(
                'rest_forbidden_context',
                __( 'Sorry, you are not allowed to access this endpoint. Requires Chiral Porter role.', 'chiral-hub-core' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }
        // Further validation: Check if requesting_node_id matches the authenticated user's node_id meta
        $user_id = get_current_user_id();
        $user_node_id = get_user_meta($user_id, '_chiral_node_id', true);
        $requesting_node_id = $request->get_param('requesting_node_id');

        if (empty($user_node_id) || $user_node_id !== $requesting_node_id) {
             return new WP_Error(
                'rest_node_id_mismatch',
                __( 'The requesting_node_id does not match the authenticated user\'s registered node ID.', 'chiral-hub-core' ),
                array( 'status' => 403 ) // Forbidden
            );
        }

        return true;
    }

    /**
     * Permission callback for the ping endpoint.
     * Ensures the request is made by an authenticated Chiral Porter.
     *
     * @since  1.0.0
     * @param  WP_REST_Request $request Full details about the request.
     * @return bool|WP_Error True if the request has permission, WP_Error object otherwise.
     */
    public function can_access_ping( WP_REST_Request $request ) {
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            return new WP_Error(
                'rest_forbidden_context',
                __( 'Sorry, you are not allowed to access this endpoint. Requires Chiral Porter role.', 'chiral-hub-core' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }
        return true;
    }

    /**
     * Get related data for a given source URL.
     *
     * @since  1.0.0
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_related_data( WP_REST_Request $request ) {
        $source_url = $request->get_param( 'source_url' );
        $requesting_node_id = $request->get_param( 'requesting_node_id' );
        $count = $request->get_param( 'count' );

        // Find the Chiral Data item in the Hub that matches the source_url and requesting_node_id
        $args = array(
            'post_type' => Chiral_Hub_CPT::CPT_SLUG,
            'posts_per_page' => 1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'chiral_source_url',
                    'value' => $source_url,
                    'compare' => '=',
                ),
                array(
                    'key' => '_chiral_node_id',
                    'value' => $requesting_node_id,
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids', // We only need the ID
        );
        $hub_posts = get_posts( $args );

        if ( empty( $hub_posts ) ) {
            return new WP_Error(
                'chiral_data_not_found',
                __( 'No Chiral Data item found in the Hub for the given source URL and Node ID.', 'chiral-hub-core' ),
                array( 'status' => 404 )
            );
        }
        $hub_cpt_id = $hub_posts[0];

        // Get current site domain for WordPress.com API
        $site_url = home_url();
        $parsed_url = wp_parse_url( $site_url );
        $hub_domain = $parsed_url['host'];

        // Call WordPress.com API directly to get related posts
        $related_data_response = $this->get_related_post_ids_from_wp_api( $hub_domain, $hub_cpt_id, $count );

        if ( is_wp_error( $related_data_response ) ) {
            return $related_data_response; // Propagate API error
        }

        if ( empty( $related_data_response['hits'] ) ) {
            return new WP_REST_Response( array(), 200 ); // No related posts found, but request is valid
        }

        // Convert API response format to match the expected format
        $related_posts_raw = array();
        foreach ( $related_data_response['hits'] as $hit ) {
            if ( isset( $hit['fields']['post_id'] ) ) {
                $related_posts_raw[] = array( 'id' => $hit['fields']['post_id'] );
            }
        }

        $related_data = array();
        foreach ( $related_posts_raw as $related_post_obj ) {
            // Handle array format from WordPress.com API
            $related_post_id = null;
            if ( is_array( $related_post_obj ) && isset( $related_post_obj['id'] ) ) {
                $related_post_id = $related_post_obj['id'];
            }
            
            if ( ! $related_post_id ) {
                continue; // Skip if we can't get the post ID
            }
            
            // Allow both chiral_data and regular post types in related results
            $post_type = get_post_type($related_post_id);
            if ( $post_type !== Chiral_Hub_CPT::CPT_SLUG && $post_type !== 'post' ) {
                continue;
            }

            // Handle different post types differently
            if ( $post_type === Chiral_Hub_CPT::CPT_SLUG ) {
                // For chiral_data posts, determine URL based on Hub Transfer Mode setting
                $options = get_option( $this->plugin_name . '_options' );
                $enable_hub_transfer_mode = isset( $options['enable_hub_transfer_mode'] ) ? (bool) $options['enable_hub_transfer_mode'] : true;
                
                $source_item_url = get_post_meta( $related_post_id, 'chiral_source_url', true );
                $original_title = get_post_meta( $related_post_id, '_chiral_data_original_title', true );
                $hub_title = get_the_title( $related_post_id );
                $featured_image_url = get_post_meta( $related_post_id, '_chiral_data_original_featured_image_url', true );
                $node_id_of_related = get_post_meta( $related_post_id, '_chiral_node_id', true );
                
                // Determine the URL to use based on Hub Transfer Mode
                $url_to_use = '';
                if ( $enable_hub_transfer_mode ) {
                    // Use Hub URL (which will redirect to source)
                    $url_to_use = get_permalink( $related_post_id );
                } else {
                    // Use direct source URL if available
                    $url_to_use = $source_item_url ? $source_item_url : get_permalink( $related_post_id );
                }
                
                // Use WordPress built-in excerpt handling
                $excerpt = get_the_excerpt($related_post_id);
                
                if ( $url_to_use ) {
                    $related_data[] = array(
                        'id' => $related_post_id, // Hub CPT ID
                        'source_url' => $url_to_use,
                        'title' => !empty($original_title) ? $original_title : $hub_title,
                        'excerpt' => $excerpt,
                        'featured_image_url' => $featured_image_url ? $featured_image_url : '',
                        'node_id' => $node_id_of_related,
                        'post_type' => 'chiral_data',
                    );
                }
            } else if ( $post_type === 'post' ) {
                // For regular posts, use the post's own URL and data
                $post_url = get_permalink( $related_post_id );
                $post_title = get_the_title( $related_post_id );
                $featured_image_id = get_post_thumbnail_id( $related_post_id );
                $featured_image_url = $featured_image_id ? wp_get_attachment_image_url( $featured_image_id, 'medium' ) : '';
                
                // Get excerpt for regular posts - use WordPress built-in handling
                $post_excerpt = get_the_excerpt($related_post_id);
                
                if ( $post_url ) {
                    $related_data[] = array(
                        'id' => $related_post_id,
                        'source_url' => $post_url,
                        'title' => $post_title,
                        'excerpt' => $post_excerpt,
                        'featured_image_url' => $featured_image_url ? $featured_image_url : '',
                        'node_id' => 'hub', // Indicate this is from the hub itself
                        'post_type' => 'post',
                    );
                }
            }

            // Exclude items from the same requesting node if desired (prioritize diversity)
            // if ($node_id_of_related === $requesting_node_id) {
            //    continue;
            // }
        }

        return new WP_REST_Response( $related_data, 200 );
    }

    /**
     * Simple ping endpoint for connection testing by Chiral Connectors.
     *
     * @since  1.0.0
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response Response object on success.
     */
    public function ping_endpoint( WP_REST_Request $request ) {
        $user = wp_get_current_user();
        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Pong! Connection to Chiral Hub successful.', 'chiral-hub-core' ),
            'user_login' => $user->user_login,
            'user_roles' => $user->roles,
            'chiral_node_id' => get_user_meta($user->ID, '_chiral_node_id', true)
        ), 200 );
    }

    /**
     * Controls the visibility of 'chiral_source_url' in the REST API response for 'chiral_data' CPT.
     *
     * @since 1.X.X
     * @param WP_REST_Response $response The response object.
     * @param WP_Post          $post     The post object.
     * @param WP_REST_Request  $request  The request object.
     * @return WP_REST_Response The modified response object.
     */
    public function control_source_url_visibility_in_response( $response, $post, $request ) {
        if ( $post->post_type !== Chiral_Hub_CPT::CPT_SLUG ) {
            return $response;
        }

        $options = get_option( $this->plugin_name . '_options' );
        $enable_hub_transfer_mode = isset( $options['enable_hub_transfer_mode'] ) ? (bool) $options['enable_hub_transfer_mode'] : true; // Default to true (transfer mode enabled)

        if ( $enable_hub_transfer_mode ) {
            // If Hub Transfer Mode is ENABLED, chiral_source_url should NOT be exposed.
            // Node will use Hub URL as the link.
            $data = $response->get_data();

            // For standard WP REST API v2 (wp/v2/chiral_data)
            if ( isset( $data['meta'] ) && isset( $data['meta']['chiral_source_url'] ) ) {
                unset( $data['meta']['chiral_source_url'] );
            }

            // For WordPress.com v1.1 API (/sites/.../posts/...)
            // This API often puts meta in a 'metadata' array of objects/arrays.
            if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
                $updated_metadata = array();
                foreach ( $data['metadata'] as $meta_item ) {
                    $current_key = '';
                    if ( is_object( $meta_item ) && isset( $meta_item->key ) ) {
                        $current_key = $meta_item->key;
                    } elseif ( is_array( $meta_item ) && isset( $meta_item['key'] ) ) {
                        $current_key = $meta_item['key'];
                    }

                    if ( 'chiral_source_url' === $current_key ) {
                        continue; // Skip, do not add to updated metadata
                    }
                    $updated_metadata[] = $meta_item;
                }
                $data['metadata'] = array_values( $updated_metadata ); // Re-index
            }
            $response->set_data( $data );
        }
        // If Hub Transfer Mode is DISABLED, chiral_source_url should be exposed (which it is by default now because show_in_rest=true during registration).
        // Node will read chiral_source_url from metadata and use it as the direct link.
        return $response;
    }


    /**
     * Get related post IDs for JS clients (lightweight version).
     * This endpoint only returns the IDs, letting JS clients fetch details directly from WordPress.com API.
     * Uses WordPress.com public API directly, no Jetpack dependency required.
     *
     * @since  1.0.2
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_related_post_ids( WP_REST_Request $request ) {
        $cpt_id = $request->get_param( 'cpt_id' );
        $count = $request->get_param( 'count' );

        // Validate that the CPT exists and is of correct type
        $post = get_post( $cpt_id );
        if ( ! $post || $post->post_type !== Chiral_Hub_CPT::CPT_SLUG ) {
            return new WP_Error(
                'invalid_cpt_id',
                __( 'Invalid chiral_data CPT ID provided.', 'chiral-hub-core' ),
                array( 'status' => 404 )
            );
        }

        // Get current site domain for WordPress.com API
        $site_url = home_url();
        $parsed_url = wp_parse_url( $site_url );
        $hub_domain = $parsed_url['host'];

        // Call WordPress.com API directly to get related posts
        $related_data = $this->get_related_post_ids_from_wp_api( $hub_domain, $cpt_id, $count );

        if ( is_wp_error( $related_data ) ) {
            return $related_data; // Propagate API error
        }

        if ( empty( $related_data['hits'] ) ) {
            return new WP_REST_Response( array( 'related_post_ids' => array() ), 200 );
        }

        $related_post_ids = array();
        foreach ( $related_data['hits'] as $hit ) {
            if ( isset( $hit['fields']['post_id'] ) ) {
                $related_post_id = intval( $hit['fields']['post_id'] );
                
                // Include both chiral_data and regular posts
                $post_type = get_post_type( $related_post_id );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Chiral Hub: Found related post ID ' . $related_post_id . ' with post_type: ' . ( $post_type ?: 'NOT_FOUND' ) );
                }
                
                if ( $post_type === Chiral_Hub_CPT::CPT_SLUG || $post_type === 'post' ) {
                    $related_post_ids[] = $related_post_id;
                }
            }
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Chiral Hub: Final filtered related_post_ids count: ' . count( $related_post_ids ) );
        }

        return new WP_REST_Response( array( 'related_post_ids' => $related_post_ids ), 200 );
    }

    /**
     * Get related post IDs from WordPress.com API directly.
     * Replicates the logic from Chiral-Connector to avoid Jetpack dependency.
     *
     * @since  1.0.2
     * @param  string $site_identifier Site domain or identifier.
     * @param  int    $post_id         Post ID to get related posts for.
     * @param  int    $size            Number of related posts to fetch.
     * @return array|WP_Error          The API response or WP_Error on failure.
     */
    private function get_related_post_ids_from_wp_api( $site_identifier, $post_id, $size = 5 ) {
        $api_url = sprintf(
            'https://public-api.wordpress.com/rest/v1.1/sites/%s/posts/%d/related',
            rawurlencode( $site_identifier ),
            $post_id
        );

        $args = array(
            'method'  => 'POST', // WordPress.com API requires POST for related posts
            'timeout' => 30,
            'body'    => array(
                'size' => $size,
                'pretty' => true,
                'filter' => array(
                    'terms' => array(
                        'post_type' => array( 'post', 'chiral_data' ) // Include both regular posts and chiral_data
                    )
                ),
            ),
        );

        $response = wp_remote_post( $api_url, $args );

        // Debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Chiral Hub: WordPress.com API Request URL: ' . $api_url );
            error_log( 'Chiral Hub: WordPress.com API Request Args: ' . wp_json_encode( $args ) );
        }

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Chiral Hub: WordPress.com API Error: ' . $response->get_error_message() );
            }
            return new WP_Error(
                'wp_api_error',
                sprintf( __( 'WordPress.com API error: %s', 'chiral-hub-core' ), $response->get_error_message() ),
                array( 'status' => 500 )
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        // Debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Chiral Hub: WordPress.com API Response Code: ' . $response_code );
            error_log( 'Chiral Hub: WordPress.com API Response Body: ' . $body );
            error_log( 'Chiral Hub: WordPress.com API Parsed Data: ' . wp_json_encode( $data ) );
        }

        if ( $response_code >= 200 && $response_code < 300 ) {
            // Additional debug logging for successful but empty responses
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                if ( empty( $data ) || empty( $data['hits'] ) ) {
                    error_log( 'Chiral Hub: WordPress.com API returned successful but empty response for post ID: ' . $post_id );
                } else {
                    error_log( 'Chiral Hub: WordPress.com API returned ' . count( $data['hits'] ) . ' related posts' );
                }
            }
            
            // Return data even if it's empty, let the caller handle it
            return $data ? $data : array( 'hits' => array() );
        } else {
            // Handle specific error codes
            $error_message = '';
            switch ( $response_code ) {
                case 403:
                    $error_message = __( 'WordPress.com API access forbidden. Site may need to be connected to WordPress.com.', 'chiral-hub-core' );
                    break;
                case 404:
                    $error_message = sprintf( __( 'Post ID %d not found on WordPress.com or site not available.', 'chiral-hub-core' ), $post_id );
                    break;
                case 400:
                    $error_message = __( 'Bad request to WordPress.com API. Check post ID and site configuration.', 'chiral-hub-core' );
                    break;
                default:
                    $error_message = sprintf(
                        __( 'WordPress.com API returned error: %s (Code: %s)', 'chiral-hub-core' ),
                        wp_remote_retrieve_response_message( $response ),
                        $response_code
                    );
            }
            
            // Include more debug info in the error
            $error_data = array( 
                'status' => $response_code,
                'response_body' => $data,
                'api_url' => $api_url,
                'request_args' => $args,
                'post_id' => $post_id,
                'site_identifier' => $site_identifier
            );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Chiral Hub: WordPress.com API Error Response: ' . wp_json_encode( $error_data ) );
            }
            
            return new WP_Error(
                'wp_api_http_error',
                $error_message,
                $error_data
            );
        }
    }
}
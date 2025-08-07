<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch
/**
 * Handles data synchronization from Chiral Nodes.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 * @since      1.0.0
 */
class Chiral_Hub_Sync {

    /**
     * Get Porter Registration Policy from settings
     *
     * @since 1.0.1
     * @return string
     */
    private function get_porter_registration_policy() {
        $options = get_option( 'chiral-hub-core_options' );
        return isset( $options['new_porter_registration'] ) ? $options['new_porter_registration'] : 'default_status';
    }

    /**
     * Get enforced status based on policy
     *
     * @since 1.0.1
     * @param string $policy
     * @return string
     */
    private function get_enforced_status( $policy ) {
        return $policy === 'pending' ? 'pending' : 'publish';
    }

    /**
     * Check if current user is a Porter
     *
     * @since 1.0.1
     * @return bool
     */
    private function is_porter_user() {
        return current_user_can( Chiral_Hub_Roles::ROLE_SLUG );
    }

    /**
     * Intercepts REST API requests for chiral_data to enforce Porter Registration Policy.
     *
     * @since 1.0.1
     * @param mixed           $result  Response to replace the requested version with.
     * @param WP_REST_Server  $server  Server instance.
     * @param WP_REST_Request $request Request used to generate the response.
     * @return mixed Original result or modified result.
     */
    public function intercept_chiral_data_requests( $result, $server, $request ) {
        $route = $request->get_route();
        if ( ! $route || ! preg_match( '#^/wp/v2/chiral_data(?:/\d+)?$#', $route ) ) {
            return $result;
        }

        $method = $request->get_method();
        
        // Handle DELETE operations for Porter users
        if ( $method === 'DELETE' && $this->is_porter_user() ) {
            if ( preg_match( '#^/wp/v2/chiral_data/(\d+)$#', $route, $matches ) ) {
                $post_id = absint( $matches[1] );
                $post = get_post( $post_id );
                
                if ( $post && $post->post_type === Chiral_Hub_CPT::CPT_SLUG && current_user_can( 'delete_post', $post_id ) ) {
                    $deleted = wp_delete_post( $post_id, true );
                    
                    if ( $deleted ) {
                        error_log( '[Chiral Hub] Porter ' . get_current_user_id() . ' permanently deleted chiral_data post ' . $post_id );
                        
                        return new WP_REST_Response( array(
                            'deleted' => true,
                            'previous' => array(
                                'id' => $post_id,
                                'status' => $post->post_status,
                                'title' => array( 'rendered' => $post->post_title )
                            )
                        ), 200 );
                    } else {
                        error_log( '[Chiral Hub] Failed to permanently delete chiral_data post ' . $post_id );
                        return new WP_Error( 'rest_cannot_delete', __( 'The post cannot be deleted.', 'chiral-hub-core' ), array( 'status' => 500 ) );
                    }
                } else {
                    return new WP_Error( 'rest_cannot_delete', __( 'Sorry, you are not allowed to delete this post.', 'chiral-hub-core' ), array( 'status' => rest_authorization_required_code() ) );
                }
            }
            return $result;
        }

        // Only intercept chiral_data POST requests from Porter users
        if ( $method !== 'POST' || ! $this->is_porter_user() ) {
            return $result;
        }

        $policy = $this->get_porter_registration_policy();
        $enforced_status = $this->get_enforced_status( $policy );
        $is_update = preg_match( '#/wp/v2/chiral_data/(\d+)$#', $route );
        
        // Remove and override status parameters
        $params = $request->get_params();
        if ( isset( $params['status'] ) ) {
            error_log( '[Chiral Hub Security] Intercepted unauthorized status parameter from Porter: ' . $params['status'] . ', enforcing policy: ' . $enforced_status );
        }
        
        $params['status'] = $enforced_status;
        $request->set_param( 'status', $enforced_status );
        
        // Override JSON body status
        $body = $request->get_body();
        if ( ! empty( $body ) ) {
            $json_data = json_decode( $body, true );
            if ( is_array( $json_data ) ) {
                if ( isset( $json_data['status'] ) && $json_data['status'] !== $enforced_status ) {
                    error_log( '[Chiral Hub Security] FORCING override of JSON body status from: ' . $json_data['status'] . ' to: ' . $enforced_status );
                }
                $json_data['status'] = $enforced_status;
                $request->set_body( wp_json_encode( $json_data ) );
            }
        }
        
        error_log( '[Chiral Hub Policy] Applied Porter Registration Policy for ' . ( $is_update ? 'UPDATE' : 'CREATE' ) . ' operation: enforced status "' . $enforced_status . '"' );

        return $result;
    }

    /**
     * Additional security check to verify post status after insertion/update.
     *
     * @since 1.0.1
     * @param WP_Post         $post     Inserted or updated post object.
     * @param WP_REST_Request $request  Request object.
     * @param bool            $creating True when creating a post, false when updating.
     */
    public function verify_post_status_compliance( $post, $request, $creating ) {
        if ( $post->post_type !== Chiral_Hub_CPT::CPT_SLUG || ! $this->is_porter_user() ) {
            return;
        }

        // Skip verification for DELETE/trash operations
        if ( $post->post_status === 'trash' || $request->get_method() === 'DELETE' ) {
            return;
        }

        $policy = $this->get_porter_registration_policy();
        $enforced_status = $this->get_enforced_status( $policy );

        if ( $post->post_status !== $enforced_status ) {
            error_log( '[Chiral Hub Security] Post status mismatch detected in ' . ( $creating ? 'CREATE' : 'UPDATE' ) . ' operation. Expected: ' . $enforced_status . ', Found: ' . $post->post_status . '. Correcting...' );
            
            wp_update_post( array(
                'ID' => $post->ID,
                'post_status' => $enforced_status
            ) );
        }
    }

    /**
     * ULTIMATE SECURITY: Force correct post status at WordPress core level.
     * 
     * @since 1.0.0
     * @param array $data    An array of slashed post data.
     * @param array $postarr An array of sanitized, but otherwise unmodified post data.
     * @return array Modified post data with enforced status.
     */
    public function force_post_status_at_core_level( $data, $postarr ) {
        if ( ! isset( $postarr['post_type'] ) || $postarr['post_type'] !== Chiral_Hub_CPT::CPT_SLUG || ! $this->is_porter_user() ) {
            return $data;
        }

        // Skip enforcement for DELETE operations
        if ( isset( $data['post_status'] ) && $data['post_status'] === 'trash' ) {
            return $data;
        }

        // Check for direct delete indicators in the request
        global $wp;
        if ( isset( $wp->query_vars, $wp->query_vars['rest_route'] ) ) {
            $method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_SERVER['REQUEST_METHOD'] is a server variable that doesn't need nonce verification
            if ( $method === 'DELETE' && preg_match( '#^/wp/v2/chiral_data/\d+$#', $wp->query_vars['rest_route'] ) ) {
                return $data;
            }
        }

        $policy = $this->get_porter_registration_policy();
        $enforced_status = $this->get_enforced_status( $policy );

        $original_status = $data['post_status'] ?? 'NOT_SET';
        if ( $original_status !== $enforced_status ) {
            $operation_type = ( isset( $postarr['ID'] ) && ! empty( $postarr['ID'] ) ) ? 'UPDATE' : 'CREATE';
            error_log( '[Chiral Hub ULTIMATE SECURITY] Forcing post status at core level for ' . $operation_type . ' operation. Original: ' . $original_status . ', Enforced: ' . $enforced_status );
        }

        $data['post_status'] = $enforced_status;
        return $data;
    }

    /**
     * Generate random slug for all chiral_data posts to avoid Chinese slug issues
     *
     * @since 1.2.0
     * @param array $data Post data array
     * @param array $postarr Original post data array
     * @return array Modified post data array
     */
    public function generate_random_slug_for_chiral_data( $data, $postarr ) {
        // Only process chiral_data post type
        if ( ! isset( $postarr['post_type'] ) || $postarr['post_type'] !== Chiral_Hub_CPT::CPT_SLUG ) {
            return $data;
        }

        // Skip if this is an update operation and post_name is already set
        if ( isset( $postarr['ID'] ) && ! empty( $postarr['ID'] ) && ! empty( $data['post_name'] ) ) {
            return $data;
        }

        // Skip if post_name is explicitly provided and is already in English
        if ( ! empty( $data['post_name'] ) && ! preg_match( '/[\x{4e00}-\x{9fff}]/u', $data['post_name'] ) ) {
            return $data;
        }

        // Generate random slug for new posts or posts with Chinese characters in slug
        $source_url = '';
        if ( isset( $postarr['meta_input']['chiral_source_url'] ) ) {
            $source_url = $postarr['meta_input']['chiral_source_url'];
        }

        $random_slug = sanitize_title( substr( md5( $source_url . time() . mt_rand() ), 0, 10 ) );
        $data['post_name'] = $random_slug;

        return $data;
    }

    /**
     * The main function to process incoming data from a Chiral Node.
     *
     * @since    1.0.0
     * @param    array $data The data array from the Chiral Node.
     * @param    int   $porter_user_id The WordPress user ID of the Chiral Porter.
     * @return   WP_Post|WP_Error The created/updated WP_Post object on success, or WP_Error on failure.
     */
    public function process_incoming_data( $data, $porter_user_id ) {
        $required_fields = array( 'source_url', 'node_id', 'original_post_id', 'title' );
        foreach ( $required_fields as $field ) {
            if ( empty( $data[ $field ] ) ) {
                return new WP_Error( 'missing_data', __( 'Essential data (source_url, node_id, original_post_id, title) is missing.', 'chiral-hub-core' ) );
            }
        }

        $sanitized_data = $this->sanitize_data( $data ); 
        $existing_post_id = $this->find_existing_chiral_data( $sanitized_data['source_url'], $sanitized_data['node_id'], $sanitized_data['original_post_id'] ); 

        $policy = $this->get_porter_registration_policy();
        $post_status = $this->get_enforced_status( $policy );

        $post_args = array(
            'post_type'         => Chiral_Hub_CPT::CPT_SLUG,
            'post_title'        => $sanitized_data['title'],
            'post_content'      => $sanitized_data['content'],
            'post_excerpt'      => $sanitized_data['excerpt'],
            'post_status'       => $post_status, 
            'post_author'       => $porter_user_id,
            'post_date_gmt'     => $sanitized_data['publish_date_gmt'],
            'post_modified_gmt' => $sanitized_data['modified_date_gmt'],
            'meta_input'        => array(
                'chiral_source_url'                        => $sanitized_data['source_url'],
                'other_URLs'                               => wp_json_encode( array( 'source' => $sanitized_data['source_url'] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
                '_chiral_node_id'                          => $sanitized_data['node_id'],
                '_chiral_data_original_post_id'            => $sanitized_data['original_post_id'],
                '_chiral_data_original_title'              => $sanitized_data['original_title'],
                '_chiral_data_original_categories'         => $sanitized_data['categories'],
                '_chiral_data_original_tags'               => $sanitized_data['tags'],
                '_chiral_data_original_featured_image_url' => $sanitized_data['featured_image_url'],
            ),
        );

        if ( $existing_post_id ) {
            $post_args['ID'] = $existing_post_id;
            $result = wp_update_post( $post_args, true );
        } else {
            $result = wp_insert_post( $post_args, true );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! empty( $sanitized_data['featured_image_url'] ) ) {
            $this->set_featured_image_from_url( $result, $sanitized_data['featured_image_url'] );
        }

        return get_post( $result );
    }

    /**
     * Sanitizes the incoming data array.
     *
     * @since  1.0.0
     * @access private
     * @param  array $data Raw data from the node.
     * @return array Sanitized data.
     */
    private function sanitize_data( $data ) {
        $fields = array(
            'source_url'        => 'esc_url_raw',
            'node_id'           => 'sanitize_text_field',
            'original_post_id'  => 'sanitize_text_field',
            'title'             => 'sanitize_text_field',
            'original_title'    => 'sanitize_text_field',
            'content'           => 'wp_kses_post',
            'excerpt'           => 'wp_kses_post',
            'featured_image_url'=> 'esc_url_raw',
            'publish_date_gmt'  => 'sanitize_text_field',
            'modified_date_gmt' => 'sanitize_text_field',
        );

        $sanitized = array();
        foreach ( $fields as $field => $sanitize_func ) {
            $sanitized[ $field ] = isset( $data[ $field ] ) ? $sanitize_func( $data[ $field ] ) : '';
        }

        // Set original_title fallback
        if ( empty( $sanitized['original_title'] ) ) {
            $sanitized['original_title'] = $sanitized['title'];
        }

        // Handle arrays
        $sanitized['categories'] = isset( $data['categories'] ) && is_array( $data['categories'] ) 
                                 ? array_map( 'sanitize_text_field', $data['categories'] ) 
                                 : array();
        $sanitized['tags'] = isset( $data['tags'] ) && is_array( $data['tags'] ) 
                           ? array_map( 'sanitize_text_field', $data['tags'] ) 
                           : array();
        
        // Handle dates with fallbacks
        if ( empty( $sanitized['publish_date_gmt'] ) || ! $this->is_valid_datetime( $sanitized['publish_date_gmt'] ) ) {
            $sanitized['publish_date_gmt'] = gmdate( 'Y-m-d H:i:s' );
        }
        if ( empty( $sanitized['modified_date_gmt'] ) || ! $this->is_valid_datetime( $sanitized['modified_date_gmt'] ) ) {
            $sanitized['modified_date_gmt'] = $sanitized['publish_date_gmt'];
        }

        return $sanitized;
    }

    /**
     * Checks if a datetime string is valid.
     *
     * @since 1.0.0
     * @access private
     * @param string $datetime The datetime string (Y-m-d H:i:s).
     * @return bool True if valid, false otherwise.
     */
    private function is_valid_datetime( $datetime ) {
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        return $d && $d->format('Y-m-d H:i:s') === $datetime;
    }

    /**
     * Finds an existing chiral_data post.
     *
     * @since  1.0.0
     * @access private
     * @param  string $source_url       The source URL of the content.
     * @param  string $node_id          The ID of the Chiral Node.
     * @param  string $original_post_id The original post ID from the Chiral Node.
     * @return int|null Post ID if found, null otherwise.
     */
    private function find_existing_chiral_data( $source_url, $node_id, $original_post_id ) {
        $posts = get_posts( array(
            'post_type'      => Chiral_Hub_CPT::CPT_SLUG,
            'posts_per_page' => 1,
            'meta_query'     => array(
                'relation' => 'AND',
                array( 'key' => 'chiral_source_url', 'value' => $source_url, 'compare' => '=' ),
                array( 'key' => '_chiral_node_id', 'value' => $node_id, 'compare' => '=' ),
                array( 'key' => '_chiral_data_original_post_id', 'value' => $original_post_id, 'compare' => '=' ),
            ),
            'fields' => 'ids',
        ) );

        return ! empty( $posts ) ? $posts[0] : null;
    }

    /**
     * Set the featured image for a post from a URL.
     *
     * @since 1.0.0
     * @param int    $post_id    The post ID.
     * @param string $image_url  The image URL.
     * @return int|WP_Error The attachment ID on success, WP_Error on failure.
     */
    private function set_featured_image_from_url( $post_id, $image_url ) {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
        }

        if ( empty( $post_id ) || empty( $image_url ) || ! get_post( $post_id ) ) {
            error_log( "[Chiral Hub Sync] Invalid inputs - post_id: {$post_id}, image_url: {$image_url}" );
            return new WP_Error( 'invalid_inputs', 'Post ID or image URL is empty or post not found' );
        }

        if ( has_post_thumbnail( $post_id ) ) {
            return get_post_thumbnail_id( $post_id );
        }

        $response = wp_remote_head( $image_url );
        if ( is_wp_error( $response ) ) {
            error_log( "[Chiral Hub Sync] Cannot access image URL: " . $response->get_error_message() );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            error_log( "[Chiral Hub Sync] Image URL returned status code: {$response_code}" );
            return new WP_Error( 'image_not_accessible', "Image URL returned status code: {$response_code}" );
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        if ( strpos( $content_type, 'image/' ) !== 0 ) {
            error_log( "[Chiral Hub Sync] URL does not point to an image. Content-Type: {$content_type}" );
            return new WP_Error( 'not_an_image', "URL does not point to an image. Content-Type: {$content_type}" );
        }

        $attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );
        
        if ( is_wp_error( $attachment_id ) ) {
            error_log( "[Chiral Hub Sync] Failed to sideload image: " . $attachment_id->get_error_message() );
            return $attachment_id;
        }

        if ( set_post_thumbnail( $post_id, $attachment_id ) ) {
            return $attachment_id;
        } else {
            error_log( "[Chiral Hub Sync] Failed to set post thumbnail for post {$post_id}" );
            return new WP_Error( 'thumbnail_failed', 'Failed to set post thumbnail' );
        }
    }

    /**
     * Handles operations before a chiral_data post is inserted via the REST API.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @param array           $prepared_post The prepared post data.
     * @return WP_REST_Request|WP_Error The request object or a WP_Error if something went wrong.
     */
    public function handle_rest_pre_insert_chiral_data( $prepared_post, $request ) {
        $params = $request->get_params();
        
        // Generate unique slug
        if ( isset( $params['meta']['chiral_source_url'] ) && ! empty( $params['meta']['chiral_source_url'] ) ) {
            $prepared_post->post_name = sanitize_title( substr( md5( $params['meta']['chiral_source_url'] . time() ), 0, 10 ) );
        }

        // Apply Porter Registration Policy for Porter users
        if ( $this->is_porter_user() ) {
            $policy = $this->get_porter_registration_policy();
            $target_status = $this->get_enforced_status( $policy );
            
            $original_status = $prepared_post->post_status ?? 'not_set';
            if ( $original_status !== 'not_set' && $original_status !== $target_status ) {
                $operation_type = ( isset( $prepared_post->ID ) && ! empty( $prepared_post->ID ) ) ? 'UPDATE' : 'CREATE';
                error_log( '[Chiral Hub Security] Porter attempted to set unauthorized status "' . $original_status . '" in ' . $operation_type . ' operation, enforcing policy status "' . $target_status . '"' );
            }
            
            $prepared_post->post_status = $target_status;
        }

        $current_user_id = get_current_user_id();
        if ( $current_user_id ) {
            // Set post author
            if ( ! isset( $prepared_post->post_author ) || $prepared_post->post_author != $current_user_id ) {
                 $prepared_post->post_author = $current_user_id;
            }

            // Update user node ID if provided
            if ( isset( $params['meta']['_chiral_node_id'] ) ) {
                $incoming_node_id = sanitize_text_field( $params['meta']['_chiral_node_id'] );
                $current_user_node_id = get_user_meta( $current_user_id, '_chiral_node_id', true );

                if ( $incoming_node_id && ( empty( $current_user_node_id ) || $current_user_node_id !== $incoming_node_id ) ) {
                    update_user_meta( $current_user_id, '_chiral_node_id', $incoming_node_id );
                }
            }

            // Ensure other_URLs field is set
            if ( isset( $params['meta']['chiral_source_url'] ) && ! isset( $params['meta']['other_URLs'] ) ) {
                $source_url = esc_url_raw( $params['meta']['chiral_source_url'] );
                if ( ! empty( $source_url ) ) {
                    if ( ! isset( $prepared_post->meta_input ) ) {
                        $prepared_post->meta_input = array();
                    }
                    $prepared_post->meta_input['other_URLs'] = wp_json_encode( array( 'source' => $source_url ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                }
            }
        }

        // Format publish date to ISO 8601
        if ( isset( $params['meta']['_chiral_data_original_publish_date'] ) ) {
            $publish_date_str = $params['meta']['_chiral_data_original_publish_date'];
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $publish_date_str) ?: DateTime::createFromFormat(DateTime::ATOM, $publish_date_str);

            if ( $datetime ) {
                if ( ! isset( $prepared_post->meta_input ) ) {
                    $prepared_post->meta_input = array();
                }
                $prepared_post->meta_input['_chiral_data_original_publish_date'] = $datetime->format(DateTime::ATOM);
            } else {
                error_log('[Chiral Hub] _chiral_data_original_publish_date meta field was present but not in a recognized date/time format: ' . $publish_date_str);
            }
        }

        return $prepared_post;
    }

    /**
     * Handles operations after a chiral_data post is inserted or updated via the REST API.
     *
     * @since 1.0.1
     * @param WP_Post         $post      Inserted or updated post object.
     * @param WP_REST_Request $request   Request object.
     * @param bool            $creating  True when creating a post, false when updating.
     */
    public function handle_rest_after_insert_chiral_data( $post, $request, $creating ) {
        $this->ensure_network_name_metadata( $post->ID );

        $params = $request->get_params();
        $featured_image_url = $params['meta']['_chiral_data_original_featured_image_url'] ?? $params['_chiral_data_original_featured_image_url'] ?? '';
        
        if ( ! empty( $featured_image_url ) && filter_var( $featured_image_url, FILTER_VALIDATE_URL ) ) {
            $result = $this->set_featured_image_from_url( $post->ID, $featured_image_url );
            
            if ( is_wp_error( $result ) ) {
                error_log( "[Chiral Hub Sync] Failed to set featured image: " . $result->get_error_message() );
            }
        }
    }

    /**
     * Ensure the post has correct network name metadata
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     */
    private function ensure_network_name_metadata( $post_id ) {
        $options = get_option( 'chiral-hub-core_options' );
        $network_name = $options['network_name'] ?? '';

        if ( ! empty( $network_name ) ) {
            update_post_meta( $post_id, 'chiral_network_name', sanitize_text_field( $network_name ) );
        }
    }

    /**
     * Automatically set network name when post is saved (for manual editing cases)
     *
     * @since 1.0.0
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an update operation
     */
    public function auto_set_network_name_on_save( $post_id, $post, $update ) {
        if ( $post->post_type !== Chiral_Hub_CPT::CPT_SLUG || 
             wp_is_post_autosave( $post_id ) || 
             wp_is_post_revision( $post_id ) || 
             ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $this->ensure_network_name_metadata( $post_id );
    }
}
<?php

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * The User Roles functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 */

/**
 * The User Roles functionality of the plugin.
 *
 * Defines the Chiral Porter role and its capabilities.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_Roles {

    /**
     * The role slug for Chiral Porter.
     *
     * @since    1.0.0
     * @access   public
     * @var      string    ROLE_SLUG    The slug for Chiral Porter role.
     */
    const ROLE_SLUG = 'chiral_porter';

    /**
     * Register the Chiral Porter user role and its capabilities.
     * This is typically called on plugin activation.
     *
     * @since    1.0.0
     */
    public static function add_roles_and_caps() {
        // Only add role if it doesn't exist to avoid recreating it on every init
        if ( ! get_role( self::ROLE_SLUG ) ) {
            // Add Chiral Porter Role
            add_role(
                self::ROLE_SLUG,
                __( 'Chiral Porter', 'chiral-hub-core' ),
                array(
                    'read' => true, // Basic WordPress read capability
                    // API operations capabilities
                    'create_chiral_data_items'       => true, // Keep for API operations
                    'edit_chiral_data_items'         => true, // Keep for API operations
                    'delete_chiral_data_items'       => true, // Keep for API operations
                    'delete_published_chiral_data_items' => true, // Allow deleting published posts
                    'publish_chiral_data_items'      => true, // Keep for API operations
                    // Application Passwords
                    'manage_application_passwords'   => true, // Allow porters to create app passwords for their nodes
                    // API-specific capabilities (for Node operations)
                    'api_edit_chiral_data_items'     => true, // Special capability for API operations
                    'api_publish_chiral_data_items'  => true, // Special capability for API operations
                )
            );
        }

        // Add capabilities to administrator and editor for managing all chiral_data
        $admin_roles = array( 'administrator', 'editor' );
        foreach ( $admin_roles as $role_name ) {
            $role = get_role( $role_name );
            if ( $role ) {
                $role->add_cap( 'edit_chiral_data_item' ); // singular form for map_meta_cap
                $role->add_cap( 'read_chiral_data_item' );
                $role->add_cap( 'delete_chiral_data_item' );
                $role->add_cap( 'edit_chiral_data_items' );
                $role->add_cap( 'edit_others_chiral_data_items' );
                $role->add_cap( 'publish_chiral_data_items' );
                $role->add_cap( 'create_chiral_data_items' ); // Allow creating new chiral_data items
                $role->add_cap( 'read_private_chiral_data_items' );
                $role->add_cap( 'delete_chiral_data_items' );
                $role->add_cap( 'delete_private_chiral_data_items' );
                $role->add_cap( 'delete_published_chiral_data_items' );
                $role->add_cap( 'delete_others_chiral_data_items' );
                $role->add_cap( 'edit_published_chiral_data_items' );
            }
        }
        
        // Refresh user capabilities to ensure changes take effect immediately
        self::refresh_user_capabilities();
    }

    /**
     * Remove the Chiral Porter user role and its capabilities.
     * This is typically called on plugin deactivation/uninstall.
     *
     * @since    1.0.0
     */
    public static function remove_roles_and_caps() {
        // Remove Chiral Porter Role
        if ( get_role( self::ROLE_SLUG ) ) {
            remove_role( self::ROLE_SLUG );
        }

        // No need to remove capabilities since we're using standard post capabilities
        // that other plugins might also use
    }

    /**
     * Restrict Chiral Porter access to the WordPress admin dashboard.
     * They should only be able to edit their profile to manage application passwords
     * and access the Chiral Data list to view/delete their own data.
     *
     * @since 1.0.0
     */
    public function restrict_porter_dashboard_access() {
        $current_user = wp_get_current_user();
        if ( ! in_array( self::ROLE_SLUG, (array) $current_user->roles ) || current_user_can( 'manage_options' ) || ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return; // Not a porter, or admin, or not in admin, or an AJAX/Cron request.
        }

            global $pagenow;
            
        // Define the slug for the custom Porter admin page
        $porter_page_slug = 'porter-chiral-data'; // Make sure this matches your Chiral_Hub_Porter_Admin page slug

        // Base allowed pages
        $allowed_pagenow = array( 'profile.php', 'user-edit.php', 'admin-ajax.php' );

        // Specifically allow the custom Porter admin page
        if ( $pagenow === 'admin.php' && isset( $_GET['page'] ) && $_GET['page'] === $porter_page_slug ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for navigation
            return; // Allow access to the custom Porter page
        }
            
        // Check if the current pagenow is one of the base allowed pages
        if ( in_array( $pagenow, $allowed_pagenow, true ) ) {
            // For user-edit.php, only allow editing their own profile
            if ( $pagenow === 'user-edit.php' ) {
                $user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for navigation
                if ( $user_id !== get_current_user_id() ) {
                    wp_redirect( admin_url( 'profile.php' ) );
                    exit;
                }
            }
            return; // Allow access to profile.php, user-edit.php (own), admin-ajax.php
        }

        // If not any of the allowed pages, redirect to profile.php (or to the custom Porter page)
        // Redirecting to their custom page might be a better user experience.
        wp_redirect( admin_url( 'admin.php?page=' . $porter_page_slug ) );
        // wp_redirect( admin_url( 'profile.php' ) ); // Alternative: redirect to profile
                        exit;
    }

    /**
     * Add user meta for chiral_node_id when a chiral_porter user is created or updated.
     * This is a placeholder for where this logic could go, e.g. on user registration or profile update.
     * The actual assignment of chiral_node_id might be manual or through a separate process.
     *
     * @param int $user_id User ID.
     */
    public function assign_chiral_node_id_meta($user_id) {
        $user = get_userdata($user_id);
        if (in_array(self::ROLE_SLUG, (array) $user->roles)) {
            // Example: If a user meta field 'pending_chiral_node_id' is set during registration by an admin
            // $node_id = get_user_meta($user_id, 'pending_chiral_node_id', true);
            // if ($node_id) {
            //    update_user_meta($user_id, '_chiral_node_id', $node_id);
            //    delete_user_meta($user_id, 'pending_chiral_node_id');
            // }
            // For now, this is a placeholder. The _chiral_node_id should be set by an admin.
        }
    }

    /**
     * Map meta capabilities for chiral_data CPT.
     * This function handles the capability mapping for chiral_data posts.
     *
     * @since 1.0.0
     * @param array  $caps    The user's actual capabilities.
     * @param string $cap     Capability name.
     * @param int    $user_id The user ID.
     * @param array  $args    Adds the context to the cap. Typically the object ID.
     * @return array The mapped capabilities.
     */
    public function map_chiral_data_meta_cap( $caps, $cap, $user_id, $args ) {
        // Define all meta capabilities this function should handle for the chiral_data CPT
        $chiral_meta_caps = array(
            // Singular (from capability_type 'chiral_data_item')
            'edit_chiral_data_item',
            'read_chiral_data_item',
            'delete_chiral_data_item',
            // Plural (from CPT 'capabilities' array mapping)
            'edit_chiral_data_items',
            'edit_others_chiral_data_items',
            'publish_chiral_data_items',
            'read_private_chiral_data_items',
            'create_chiral_data_items',
            'delete_chiral_data_items',
            'delete_others_chiral_data_items',
            'delete_published_chiral_data_items',
            'delete_private_chiral_data_items',
            'edit_published_chiral_data_items'
            // Add any other meta capabilities explicitly defined or relied upon for 'chiral_data' CPT
        );

        if ( ! in_array( $cap, $chiral_meta_caps, true ) ) {
            return $caps; // Not one of our CPT's meta capabilities
        }

        // Quick exit for administrators - they can do everything
        if ( user_can( $user_id, 'manage_options' ) ) {
            return array( 'exist' ); // Grant all chiral_data permissions to admins
        }

        $post_id = 0;
        if ( !empty( $args[0] ) && is_numeric( $args[0] ) ) {
            $post_id = intval( $args[0] );
        }

        // Handle calls without a specific post ID (generic capability check)
        if ( $post_id === 0 ) {
            switch ( $cap ) {
                // Singular meta caps usually map to a broader primitive or a plural version
                case 'edit_chiral_data_item':
                    return array( 'edit_chiral_data_items' );
                case 'delete_chiral_data_item':
                    return array( 'delete_chiral_data_items' );
                case 'read_chiral_data_item':
                    // For a generic read check on a singular meta cap without ID,
                    // mapping to the basic 'read' capability is common.
                    return array( 'read' );

                // Plural meta caps, when checked without a post_id,
                // typically map to themselves as primitive capabilities.
                // This assumes the roles have these primitive capabilities granted.
                case 'edit_chiral_data_items':
                case 'edit_others_chiral_data_items':
                case 'publish_chiral_data_items':
                case 'read_private_chiral_data_items':
                case 'create_chiral_data_items':
                case 'delete_chiral_data_items':
                case 'delete_others_chiral_data_items':
                case 'delete_published_chiral_data_items':
                case 'delete_private_chiral_data_items':
                case 'edit_published_chiral_data_items':
                    return array( $cap ); // Map to the capability itself

                default:
                    // If it's a chiral_meta_cap we listed but don't have a specific generic mapping for,
                    // it's safer to deny or map to a non-existent cap to prevent unintended access.
                    // However, mapping to itself if it's also a primitive cap is often intended.
                    // For now, if it's in $chiral_meta_caps but not explicitly handled above for no-ID case,
                    // we'll map it to itself as a fallback, assuming it might be a primitive.
                    return array( $cap );
            }
        }

        // If we have a post_id, proceed with existing logic (which expects singular $cap for CPTs)
        // WordPress core typically converts plural CPT meta cap checks (e.g., 'edit_chiral_data_items' for a post)
        // to singular meta cap checks (e.g., 'edit_chiral_data_item' for that post) before this filter
        // if map_meta_cap is true and capability_type is set.
        // So, $cap passed here with a post_id should generally be the singular form.

        $post = get_post( $post_id );

        // Only process chiral_data posts
        if ( ! $post || ! class_exists('Chiral_Hub_CPT') || $post->post_type !== Chiral_Hub_CPT::CPT_SLUG ) {
            // Ensure Chiral_Hub_CPT::CPT_SLUG is accessible or use the string directly
            return $caps;
        }

        // Check if current user is a Chiral Porter
        $user_is_porter = false;
        $current_user_obj = get_userdata( $user_id );
        if ( $current_user_obj && in_array( self::ROLE_SLUG, (array) $current_user_obj->roles ) ) {
            $user_is_porter = true;
        }

        // Determine if this is an API request
        $is_api_request = defined('REST_REQUEST') && REST_REQUEST;

        // Handle capabilities based on the specific meta capability being checked
        switch ( $cap ) {
            case 'edit_chiral_data_item':
                if ( $user_is_porter && !$is_api_request ) {
                    // Porter trying to edit via admin panel - always deny
                    return array( 'do_not_allow' );
                } elseif ( $user_is_porter && $is_api_request ) {
                    // Porter editing via API - check ownership or node ID
                    if ( (int) $post->post_author === (int) $user_id ) {
                        return array( 'edit_chiral_data_items' );
                    } else {
                        // Check node ID
                        $post_node_id = get_post_meta( $post->ID, '_chiral_node_id', true );
                        $user_node_id = get_user_meta( $user_id, '_chiral_node_id', true );
                        if ( !empty($post_node_id) && !empty($user_node_id) && $post_node_id === $user_node_id ) {
                            return array( 'edit_chiral_data_items' );
                        }
                    }
                }
                break;

            case 'delete_chiral_data_item':
                if ( $user_is_porter ) {
                    // Porter can delete their own posts or posts from their node
                    if ( (int) $post->post_author === (int) $user_id ) {
                        $required_cap = in_array( $post->post_status, array( 'publish', 'future' ), true ) ? 'delete_published_chiral_data_items' : 'delete_chiral_data_items';
                        return array( $required_cap );
                    } else {
                        // Check node ID
                        $post_node_id = get_post_meta( $post->ID, '_chiral_node_id', true );
                        $user_node_id = get_user_meta( $user_id, '_chiral_node_id', true );
                        if ( !empty($post_node_id) && !empty($user_node_id) && $post_node_id === $user_node_id ) {
                            $required_cap = in_array( $post->post_status, array( 'publish', 'future' ), true ) ? 'delete_published_chiral_data_items' : 'delete_chiral_data_items';
                            return array( $required_cap );
                        }
                    }
                }
                break;

            case 'read_chiral_data_item':
                if ( $user_is_porter ) {
                    // Porter can read their own posts or posts from their node
                    if ( (int) $post->post_author === (int) $user_id ) {
                        return array( 'read' );
                    } else {
                        // Check node ID
                        $post_node_id = get_post_meta( $post->ID, '_chiral_node_id', true );
                        $user_node_id = get_user_meta( $user_id, '_chiral_node_id', true );
                        if ( !empty($post_node_id) && !empty($user_node_id) && $post_node_id === $user_node_id ) {
                            return array( 'read' );
                        }
                    }
                }
                break;
        }

        // For non-Porter users or cases not handled above, use WordPress defaults
        return $caps;
    }

    /**
     * Handle publish capabilities for Chiral Porter via user_has_cap filter.
     * This is needed because publish capabilities don't always have post IDs.
     *
     * @since 1.0.0
     * @param array $user_caps User's capabilities.
     * @param array $requested_caps Requested capabilities.
     * @param array $args Arguments (including capability name).
     * @param WP_User $user User object.
     * @return array Modified user capabilities.
     */
    public function handle_porter_publish_capabilities( $user_caps, $requested_caps, $args, $user ) {
        // Only process for Chiral Porter users
        if ( !in_array( self::ROLE_SLUG, (array) $user->roles ) ) {
            return $user_caps;
        }

        // Check if this is a publish_chiral_data_items capability request
        if ( !empty( $args[0] ) && $args[0] === 'publish_chiral_data_items' ) {
            // Check if this is an API request
            $is_api_request = defined('REST_REQUEST') && REST_REQUEST;
            
            if ( $is_api_request && isset( $user_caps['api_publish_chiral_data_items'] ) ) {
                // Allow API publishing for Porter
                $user_caps['publish_chiral_data_items'] = true;
            } elseif ( !$is_api_request ) {
                // Deny admin panel publishing for Porter
                $user_caps['publish_chiral_data_items'] = false;
            }
        }

        return $user_caps;
    }

    /**
     * Simple capability check for Chiral Porter to access admin pages.
     * This avoids complex meta capability mapping issues.
     *
     * @since 1.0.0
     * @param bool $has_cap Whether the user has the capability.
     * @param string $capability The capability being checked.
     * @param int $user_id The user ID.
     * @return bool Whether the user has the capability.
     */
    public function check_porter_admin_access( $has_cap, $capability, $user_id ) {
        $user = get_userdata( $user_id );
        
        // Only apply to Chiral Porter users
        if ( !$user || !in_array( self::ROLE_SLUG, (array) $user->roles ) ) {
            return $has_cap;
        }

        // Allow specific capabilities for Porter to access the admin interface
        $allowed_caps = array(
            'edit_chiral_data_items',
            'read',
            'read_chiral_data_item',
            'delete_chiral_data_items',
            'delete_chiral_data_item',
            'delete_published_chiral_data_items'
        );
        
        if ( in_array( $capability, $allowed_caps ) ) {
            return true;
        }

        return $has_cap;
    }

    /**
     * Force refresh user capabilities to ensure role changes take effect immediately.
     *
     * @since 1.0.0
     */
    public static function refresh_user_capabilities() {
        // This method can be called when roles are updated to force WordPress to refresh capabilities
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
        
        // Clear user meta cache
        if ( function_exists( 'clean_user_cache' ) ) {
            $users = get_users( array( 'role' => self::ROLE_SLUG ) );
            foreach ( $users as $user ) {
                clean_user_cache( $user->ID );
            }
        }
    }

    /**
     * Debug method to check current user capabilities for troubleshooting.
     *
     * @since 1.0.0
     */
    public function debug_user_capabilities() {
        // Debugging capabilities functionality removed for production
        // This method can be re-enabled for debugging purposes if needed
        return;
    }

    /**
     * Filter the query to show only the Porter's own chiral_data posts in admin.
     *
     * @since 1.0.0
     * @param WP_Query $query The WP_Query instance.
     */
    public function filter_porter_chiral_data_query( $query ) {
        global $pagenow;
        
        // Only apply to admin area edit.php page for chiral_data
        if ( !is_admin() || $pagenow !== 'edit.php' || !$query->is_main_query() ) {
            return;
        }
        
        // Check if we're viewing chiral_data post type
        $post_type = isset($_GET['post_type']) ? wp_unslash( sanitize_text_field( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only operation for display purposes
        if ( $post_type !== Chiral_Hub_CPT::CPT_SLUG ) {
            return;
        }
        
        // Check if current user is a Chiral Porter
        $current_user = wp_get_current_user();
        if ( !in_array( self::ROLE_SLUG, (array) $current_user->roles ) ) {
            return;
        }
        
        // If user is a Porter, only show their own posts
        $query->set( 'author', get_current_user_id() );
    }

    /**
     * Remove edit and quick edit actions for Chiral Porter in the posts list.
     *
     * @since 1.0.0
     * @param array   $actions An array of row action links.
     * @param WP_Post $post    The post object.
     * @return array Modified actions array.
     */
    public function remove_porter_edit_actions( $actions, $post ) {
        // Only apply to chiral_data post type
        if ( $post->post_type !== Chiral_Hub_CPT::CPT_SLUG ) {
            return $actions;
        }
        
        // Check if current user is a Chiral Porter
        $current_user = wp_get_current_user();
        if ( !in_array( self::ROLE_SLUG, (array) $current_user->roles ) ) {
            return $actions;
        }
        
        // Remove edit and inline edit actions for Porters
        unset( $actions['edit'] );
        unset( $actions['inline hide-if-no-js'] );
        
        return $actions;
    }

    /**
     * Remove bulk edit actions for Chiral Porter.
     *
     * @since 1.0.0
     * @param array $actions An array of bulk actions.
     * @return array Modified actions array.
     */
    public function remove_porter_bulk_actions( $actions ) {
        global $pagenow;
        
        // Only apply to admin area edit.php page for chiral_data
        if ( !is_admin() || $pagenow !== 'edit.php' ) {
            return $actions;
        }
        
        // Check if we're viewing chiral_data post type
        $post_type = isset($_GET['post_type']) ? wp_unslash( sanitize_text_field( $_GET['post_type'] ) ) : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Read-only operation for display purposes
        if ( $post_type !== Chiral_Hub_CPT::CPT_SLUG ) {
            return $actions;
        }
        
        // Check if current user is a Chiral Porter
        $current_user = wp_get_current_user();
        if ( !in_array( self::ROLE_SLUG, (array) $current_user->roles ) ) {
            return $actions;
        }
        
        // Remove edit action for Porters, keep trash
        unset( $actions['edit'] );
        
        return $actions;
    }

    /**
     * Customize the admin bar for Chiral Porter users.
     * Removes items that might trigger unnecessary capability checks.
     *
     * @since 1.0.0
     * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
     */
    public function customize_porter_admin_bar( $wp_admin_bar ) {
        $current_user = wp_get_current_user();
        // Check if the current user is a Chiral Porter and not an administrator (who might also have the Porter role for testing)
        if ( in_array( self::ROLE_SLUG, (array) $current_user->roles ) && ! current_user_can( 'manage_options' ) ) {
            // Remove the '+ New' (new-content) menu from the admin bar
            $wp_admin_bar->remove_node( 'new-content' );

            // Remove the 'Comments' bubble from the admin bar (if it exists)
            $wp_admin_bar->remove_node( 'comments' );
        }
    }
}
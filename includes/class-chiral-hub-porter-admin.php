<?php

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * The Porter Admin functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 */

/**
 * The Porter Admin functionality of the plugin.
 *
 * Provides a simple admin interface for Chiral Porter users.
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_Porter_Admin {

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
    }

    /**
     * Add Porter admin menu if current user is a Porter.
     *
     * @since 1.0.0
     */
    public function add_porter_admin_menu() {
        $current_user = wp_get_current_user();
        if ( !in_array( Chiral_Hub_Roles::ROLE_SLUG, (array) $current_user->roles ) ) {
            return;
        }

        // Add top-level menu for Porter
        add_menu_page(
            __( 'My Chiral Data', 'chiral-hub-core' ),
            __( 'My Chiral Data', 'chiral-hub-core' ),
            'read',
            'porter-chiral-data',
            array( $this, 'display_porter_data_page' ),
            'dashicons-database-view',
            30
        );
    }

    /**
     * Display the Porter data management page.
     *
     * @since 1.0.0
     */
    public function display_porter_data_page() {
        // Handle delete action
        // First sanitize and validate all GET parameters
        $action = '';
        $post_id = 0;
        $nonce = '';
        
        if ( isset( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
            $action = wp_unslash( sanitize_text_field( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
        }
        
        if ( isset( $_GET['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
            $post_id = absint( wp_unslash( $_GET['post_id'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        }
        
        if ( isset( $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
            $nonce = wp_unslash( sanitize_text_field( $_GET['_wpnonce'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
        }
        
        // Now check the sanitized values
        if ( $action === 'delete' && $post_id > 0 && ! empty( $nonce ) ) {
            if ( wp_verify_nonce( $nonce, 'delete_chiral_post_' . $post_id ) ) {
                $this->handle_delete_action( $post_id );
            }
        }

        $current_user_id = get_current_user_id();
        
        // Get user's node ID
        $user_node_id = get_user_meta( $current_user_id, '_chiral_node_id', true );
        
        // Query for user's chiral data posts
        $args = array(
            'post_type' => Chiral_Hub_CPT::CPT_SLUG,
            'post_status' => array( 'publish', 'draft', 'private', 'pending' ),
            'posts_per_page' => -1,
            'meta_query' => array()
        );

        // Add conditions to show only user's posts or posts from their node
        if ( !empty( $user_node_id ) ) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_chiral_node_id',
                    'value' => $user_node_id,
                    'compare' => '='
                )
            );
        }
        
        // Also include posts authored by this user
        $args['author'] = $current_user_id;

        $posts = get_posts( $args );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'My Chiral Data', 'chiral-hub-core' ); ?></h1>
            
            <?php 
            $deleted_param = '';
            if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
                $deleted_param = wp_unslash( sanitize_text_field( $_GET['deleted'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            }
            
            if ( $deleted_param === '1' ): 
            ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Post deleted successfully.', 'chiral-hub-core' ); ?></p>
                </div>
            <?php endif; ?>

            <?php 
            $error_param = '';
            if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
                $error_param = wp_unslash( sanitize_text_field( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
            }
            
            if ( ! empty( $error_param ) ): 
            ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e( 'Error: Unable to delete post.', 'chiral-hub-core' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( empty( $posts ) ): ?>
                <p><?php esc_html_e( 'No chiral data found.', 'chiral-hub-core' ); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Title', 'chiral-hub-core' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Status', 'chiral-hub-core' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Date', 'chiral-hub-core' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Source URL', 'chiral-hub-core' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Node ID', 'chiral-hub-core' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Actions', 'chiral-hub-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $posts as $post ): ?>
                            <?php
                            $source_url = get_post_meta( $post->ID, 'chiral_source_url', true );
                            $node_id = get_post_meta( $post->ID, '_chiral_node_id', true );
                            $can_delete = ( $post->post_author == $current_user_id ) || 
                                         ( !empty( $user_node_id ) && $node_id === $user_node_id );
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $post->post_title ); ?></strong>
                                </td>
                                <td>
                                    <span class="post-status-<?php echo esc_attr( $post->post_status ); ?>">
                                        <?php 
                                        if ( $post->post_status === 'pending' ) {
                                            echo '<span style="color: #d63638; font-weight: bold;">' . esc_html__( 'Pending Review', 'chiral-hub-core' ) . '</span>';
                                        } else {
                                            echo esc_html( ucfirst( $post->post_status ) );
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( get_the_date( 'Y-m-d H:i:s', $post ) ); ?></td>
                                <td>
                                    <?php if ( $source_url ): ?>
                                        <a href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener">
                                            <?php echo esc_html( $source_url ); ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $node_id ?: '—' ); ?></td>
                                <td>
                                    <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" class="button button-small">
                                        <?php esc_html_e( 'View', 'chiral-hub-core' ); ?>
                                    </a>
                                    <?php if ( $can_delete && $post->post_status !== 'pending' ): ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( 
                                            add_query_arg( array(
                                                'action' => 'delete',
                                                'post_id' => $post->ID
                                            ) ),
                                            'delete_chiral_post_' . $post->ID
                                        ) ); ?>" 
                                        class="button button-small button-link-delete" 
                                        onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this item?', 'chiral-hub-core' ); ?>');">
                                            <?php esc_html_e( 'Delete', 'chiral-hub-core' ); ?>
                                        </a>
                                    <?php elseif ( $post->post_status === 'pending' ): ?>
                                        <span class="button button-small button-disabled" style="color: #888; cursor: not-allowed;" title="<?php esc_attr_e( 'The deletion of pending review posts needs to be done on Node.', 'chiral-hub-core' ); ?>">
                                            <?php esc_html_e( 'Delete', 'chiral-hub-core' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p>
                <strong><?php esc_html_e( 'Note:', 'chiral-hub-core' ); ?></strong>
                <?php esc_html_e( 'You can only view and delete chiral data items. When you create or modify an article on your Blog, these will be automatically updated.', 'chiral-hub-core' ); ?>
            </p>
        </div>

        <style>
        .post-status-publish { color: #008a00; }
        .post-status-draft { color: #b32d2e; }
        .post-status-private { color: #8b4513; }
        .button-link-delete { color: #a00; }
        .button-link-delete:hover { color: #dc3232; }
        </style>
        <?php
    }

    /**
     * Handle delete action for chiral data posts.
     *
     * @since 1.0.0
     * @param int $post_id The post ID to delete.
     */
    private function handle_delete_action( $post_id ) {
        $current_user_id = get_current_user_id();
        $post = get_post( $post_id );

        // Verify this is a chiral_data post
        if ( !$post || $post->post_type !== Chiral_Hub_CPT::CPT_SLUG ) {
            wp_redirect( admin_url( 'admin.php?page=porter-chiral-data&error=invalid_post' ) );
            exit;
        }

        // Check if user can delete this post
        $user_node_id = get_user_meta( $current_user_id, '_chiral_node_id', true );
        $post_node_id = get_post_meta( $post_id, '_chiral_node_id', true );
        
        $can_delete = ( $post->post_author == $current_user_id ) || 
                     ( !empty( $user_node_id ) && $post_node_id === $user_node_id );

        if ( !$can_delete ) {
            wp_redirect( admin_url( 'admin.php?page=porter-chiral-data&error=permission_denied' ) );
            exit;
        }

        // Delete the post
        $deleted = wp_delete_post( $post_id, true );

        if ( $deleted ) {
            wp_redirect( admin_url( 'admin.php?page=porter-chiral-data&deleted=1' ) );
        } else {
            wp_redirect( admin_url( 'admin.php?page=porter-chiral-data&error=delete_failed' ) );
        }
        exit;
    }

    /**
     * Redirect Porter users away from standard CPT admin pages.
     *
     * @since 1.0.0
     */
    public function redirect_porter_from_cpt_admin() {
        global $pagenow;
        
        $current_user = wp_get_current_user();
        if ( !in_array( Chiral_Hub_Roles::ROLE_SLUG, (array) $current_user->roles ) ) {
            return;
        }

        // Sanitize all GET parameters first
        $post_type_param = '';
        if ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
            $post_type_param = wp_unslash( sanitize_text_field( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
        }
        
        $post_param = 0;
        if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
            $post_param = absint( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
        }

        // Redirect from standard CPT admin pages to our custom page
        if ( $pagenow === 'edit.php' && $post_type_param === Chiral_Hub_CPT::CPT_SLUG ) {
            wp_redirect( admin_url( 'admin.php?page=porter-chiral-data' ) );
            exit;
        }

        if ( $pagenow === 'post-new.php' && $post_type_param === Chiral_Hub_CPT::CPT_SLUG ) {
            wp_redirect( admin_url( 'admin.php?page=porter-chiral-data' ) );
            exit;
        }
        
        if ( $pagenow === 'post.php' && $post_param > 0 ) {
            $post = get_post( $post_param );
            if ( $post && $post->post_type === Chiral_Hub_CPT::CPT_SLUG ) {
                wp_redirect( admin_url( 'admin.php?page=porter-chiral-data' ) );
                exit;
            }
        }
    }
} 
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
     * Handle Porter admin actions that require redirects EARLY in admin_init
     * This prevents "headers already sent" errors.
     *
     * @since 1.2.0
     */
    public function handle_porter_admin_actions() {
        // Only handle if we're on the Porter admin page
        if ( !isset( $_GET['page'] ) || $_GET['page'] !== 'porter-chiral-data' ) {
            return;
        }

        // Only process for Porter users
        $current_user = wp_get_current_user();
        if ( !in_array( Chiral_Hub_Roles::ROLE_SLUG, (array) $current_user->roles ) ) {
            return;
        }

        // Handle configuration save FIRST - before any output
        if ( isset( $_POST['chiral_porter_config_nonce'] ) && wp_verify_nonce( $_POST['chiral_porter_config_nonce'], 'save_porter_config' ) ) {
            $this->handle_config_save();
            // This method calls wp_redirect() and exit, so we won't reach here
        }

        // Handle delete action EARLY - before any output
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
                // This method calls wp_redirect() and exit, so we won't reach here
            }
        }
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
        // NOTE: Redirect actions are now handled in handle_porter_admin_actions() 
        // during admin_init to prevent "headers already sent" errors.

        $current_user_id = get_current_user_id();
        
        // Get current tab
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'data';
        
        // Get user's sync mode and configuration
        $sync_mode = get_user_meta( $current_user_id, '_chiral_sync_mode', true ) ?: 'wordpress';
        $user_node_id = get_user_meta( $current_user_id, '_chiral_node_id', true );
        $rss_url = get_user_meta( $current_user_id, '_chiral_rss_url', true );
        $sitemap_url = get_user_meta( $current_user_id, '_chiral_sitemap_url', true );
        $sync_frequency = get_user_meta( $current_user_id, '_chiral_rss_sync_frequency', true ) ?: 'hourly';

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'My Chiral Data', 'chiral-hub-core' ); ?></h1>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=porter-chiral-data&tab=data' ) ); ?>" 
                   class="nav-tab <?php echo $current_tab === 'data' ? 'nav-tab-active' : ''; ?>">
                    📊 <?php esc_html_e( 'My Data', 'chiral-hub-core' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=porter-chiral-data&tab=config' ) ); ?>" 
                   class="nav-tab <?php echo $current_tab === 'config' ? 'nav-tab-active' : ''; ?>">
                    ⚙️ <?php esc_html_e( 'Configuration', 'chiral-hub-core' ); ?>
                </a>
            </nav>

            <?php 
            // Display success/error messages
            if ( isset( $_GET['updated'] ) && $_GET['updated'] === '1' ): 
            ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Configuration saved successfully.', 'chiral-hub-core' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $current_tab === 'data' ): ?>
                <!-- Data Tab Content -->
                <?php $this->render_data_tab( $current_user_id, $user_node_id ); ?>
            <?php else: ?>
                <!-- Configuration Tab Content -->
                <?php $this->render_config_tab( $current_user_id, $sync_mode, $rss_url, $sitemap_url, $sync_frequency ); ?>
            <?php endif; ?>
        </div>

        <!-- Tab Styles and JS -->
        <style>
        .post-status-publish { color: #008a00; }
        .post-status-draft { color: #b32d2e; }
        .post-status-private { color: #8b4513; }
        .button-link-delete { color: #a00; }
        .button-link-delete:hover { color: #dc3232; }
        
        .mode-selector {
            margin: 20px 0;
            display: flex;
            gap: 20px;
        }
        
        .mode-option {
            display: block;
            cursor: pointer;
        }
        
        .mode-card {
            border: 2px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            min-width: 200px;
        }
        
        .mode-card:hover {
            border-color: #007cba;
        }
        
        .mode-option input[type="radio"]:checked + .mode-card {
            border-color: #007cba;
            background-color: #f0f8ff;
        }
        
        .mode-config {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .rss-actions {
            margin-top: 20px;
        }
        
        .rss-actions .button {
            margin-right: 10px;
        }
        
        .import-progress {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #007cba;
            transition: width 0.3s ease;
        }
        
        .import-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin: 10px 0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-label {
            display: block;
            font-size: 12px;
            color: #666;
        }
        
        .stat-value {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .url-include-row,
        .url-exclude-row {
            transition: opacity 0.3s ease;
        }
        
        .url-include-row.hidden,
        .url-exclude-row.hidden {
            display: none;
        }
        </style>

        <script type="text/javascript">
        var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
        jQuery(document).ready(function($) {
            
            // Re Sync single post functionality (use document delegation for dynamic content)
            $(document).on('click', '.re-sync-post', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var sourceUrl = button.data('source-url');
                
                if (!confirm('<?php echo esc_js( __( 'Are you sure you want to re-sync this article? This will fetch the latest content from the source and may change the article status to pending if the Hub is in pending mode.', 'chiral-hub-core' ) ); ?>')) {
                    return;
                }
                
                button.prop('disabled', true).text('<?php echo esc_js( __( 'Re Syncing...', 'chiral-hub-core' ) ); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'chiral_re_sync_post',
                        nonce: '<?php echo wp_create_nonce( 'chiral_porter_ajax' ); ?>',
                        post_id: postId,
                        source_url: sourceUrl
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php echo esc_js( __( 'Article re-synced successfully!', 'chiral-hub-core' ) ); ?>');
                            location.reload(); // Refresh to show updated data
                        } else {
                            alert('<?php echo esc_js( __( 'Error: ', 'chiral-hub-core' ) ); ?>' + (response.data.message || '<?php echo esc_js( __( 'Unknown error occurred.', 'chiral-hub-core' ) ); ?>'));
                            button.prop('disabled', false).text('<?php echo esc_js( __( 'Re Sync', 'chiral-hub-core' ) ); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js( __( 'Network error occurred.', 'chiral-hub-core' ) ); ?>');
                        button.prop('disabled', false).text('<?php echo esc_js( __( 'Re Sync', 'chiral-hub-core' ) ); ?>');
                    }
                });
            });
        });
        </script>

        <?php
    }

    /**
     * Handle delete action for chiral data posts.
     *
     * @since 1.0.0
     * @param int $post_id The post ID to delete.
     */
    private function handle_delete_action( $post_id ) {
        // Clean any output buffer to prevent headers already sent error
        if ( ob_get_level() ) {
            ob_clean();
        }

        $current_user_id = get_current_user_id();
        $post = get_post( $post_id );

        // Verify this is a chiral_data post
        if ( !$post || $post->post_type !== Chiral_Hub_CPT::CPT_SLUG ) {
            wp_safe_redirect( admin_url( 'admin.php?page=porter-chiral-data&error=invalid_post' ) );
            exit;
        }

        // Check if user can delete this post
        $user_node_id = get_user_meta( $current_user_id, '_chiral_node_id', true );
        $post_node_id = get_post_meta( $post_id, '_chiral_node_id', true );
        
        $can_delete = ( $post->post_author == $current_user_id ) || 
                     ( !empty( $user_node_id ) && $post_node_id === $user_node_id );

        if ( !$can_delete ) {
            wp_safe_redirect( admin_url( 'admin.php?page=porter-chiral-data&error=permission_denied' ) );
            exit;
        }

        // Delete the post
        $deleted = wp_delete_post( $post_id, true );

        if ( $deleted ) {
            wp_safe_redirect( admin_url( 'admin.php?page=porter-chiral-data&deleted=1' ) );
        } else {
            wp_safe_redirect( admin_url( 'admin.php?page=porter-chiral-data&error=delete_failed' ) );
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

    /**
     * 渲染数据标签页内容
     *
     * @since 1.2.0
     * @param int $current_user_id 当前用户ID
     * @param string $user_node_id 用户节点ID
     */
    private function render_data_tab( $current_user_id, $user_node_id ) {
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
                            <th scope="col"><?php esc_html_e( 'Sync Method', 'chiral-hub-core' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Actions', 'chiral-hub-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $posts as $post ): ?>
                            <?php
                            $source_url = get_post_meta( $post->ID, 'chiral_source_url', true );
                            $node_id = get_post_meta( $post->ID, '_chiral_node_id', true );
                        $source_type = get_post_meta( $post->ID, '_chiral_source_type', true ) ?: 'wordpress';
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
                                <?php 
                                $sync_method = get_post_meta( $post->ID, '_chiral_sync_method', true );
                                if ( $source_type === 'rss' ): 
                                    if ( $sync_method === 'sitemap_import' ): ?>
                                        <span style="color: #007cba;" title="<?php esc_attr_e( 'Imported from Sitemap', 'chiral-hub-core' ); ?>">🗺️ Sitemap</span>
                                    <?php else: ?>
                                        <span style="color: #007cba;" title="<?php esc_attr_e( 'Synced from RSS Feed', 'chiral-hub-core' ); ?>">📄 RSS</span>
                                    <?php endif;
                                else: ?>
                                    <span style="color: #00a32a;" title="<?php esc_attr_e( 'Real-time WordPress sync', 'chiral-hub-core' ); ?>">⚡ WordPress</span>
                                <?php endif; ?>
                            </td>
                                <td>
                                    <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" class="button button-small">
                                        <?php esc_html_e( 'View', 'chiral-hub-core' ); ?>
                                    </a>
                                    
                                    <?php if ( $source_type === 'rss' && !empty( $source_url ) ): ?>
                                        <button type="button" class="button button-small button-secondary re-sync-post" 
                                                data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                                                data-source-url="<?php echo esc_attr( $source_url ); ?>"
                                                title="<?php esc_attr_e( 'Re-sync this article from the original source', 'chiral-hub-core' ); ?>">
                                            <?php esc_html_e( 'Re Sync', 'chiral-hub-core' ); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ( $can_delete && $post->post_status !== 'pending' ): ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( 
                                            add_query_arg( array(
                                                'action' => 'delete',
                                            'post_id' => $post->ID,
                                            'tab' => 'data'
                                            ) ),
                                            'delete_chiral_post_' . $post->ID
                                        ) ); ?>" 
                                        class="button button-small button-link-delete" 
                                        onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this item?', 'chiral-hub-core' ) ); ?>');">
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
                <?php esc_html_e( 'You can view, delete, and re-sync chiral data items. WordPress mode posts (⚡) are automatically updated when you create or modify articles on your Blog. RSS mode posts (📄) are synced periodically from your RSS feed and can be manually re-synced using the "Re Sync" button. Sitemap imports (🗺️) are bulk imported from your sitemap.xml file and can also be individually re-synced.', 'chiral-hub-core' ); ?>
            </p>

        <?php
    }

    /**
     * 渲染配置标签页内容
     *
     * @since 1.2.0
     * @param int $current_user_id 当前用户ID
     * @param string $sync_mode 同步模式
     * @param string $rss_url RSS URL
     * @param string $sitemap_url Sitemap URL
     * @param string $sync_frequency 同步频率
     */
    private function render_config_tab( $current_user_id, $sync_mode, $rss_url, $sitemap_url, $sync_frequency ) {
        $node_id = get_user_meta( $current_user_id, '_chiral_node_id', true );
        $last_sync = get_user_meta( $current_user_id, '_chiral_rss_last_sync', true );
        $import_status = get_user_meta( $current_user_id, '_chiral_import_status', true );

        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'save_porter_config', 'chiral_porter_config_nonce' ); ?>
            
            <div class="mode-selector">
                <h3><?php esc_html_e( 'Synchronization Mode', 'chiral-hub-core' ); ?></h3>
                <label class="mode-option">
                    <input type="radio" name="sync_mode" value="wordpress" <?php checked( $sync_mode, 'wordpress' ); ?>>
                    <div class="mode-card wordpress-mode">
                        <h4>⚡ <?php esc_html_e( 'WordPress Mode', 'chiral-hub-core' ); ?></h4>
                        <p><?php esc_html_e( 'Real-time sync using Connector plugin installed on your WordPress site.', 'chiral-hub-core' ); ?></p>
        </div>
                </label>
                
                <label class="mode-option">
                    <input type="radio" name="sync_mode" value="rss" <?php checked( $sync_mode, 'rss' ); ?>>
                    <div class="mode-card rss-mode">
                        <h4>📄 <?php esc_html_e( 'RSS Mode', 'chiral-hub-core' ); ?></h4>
                        <p><?php esc_html_e( 'Periodic sync via RSS feeds and Sitemaps for static blogs and non-WordPress sites.', 'chiral-hub-core' ); ?></p>
                    </div>
                </label>
            </div>

            <!-- WordPress模式配置 -->
            <div id="wordpress-config" class="mode-config" style="<?php echo $sync_mode === 'wordpress' ? '' : 'display: none;'; ?>">
                <h4><?php esc_html_e( 'WordPress Configuration', 'chiral-hub-core' ); ?></h4>
                <p><?php esc_html_e( 'Install the Chiral Connector plugin on your WordPress site and configure the connection using the following details:', 'chiral-hub-core' ); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Your Node ID', 'chiral-hub-core' ); ?></th>
                        <td>
                            <code><?php echo esc_html( $node_id ?: __( 'Not set', 'chiral-hub-core' ) ); ?></code>
                            <p class="description"><?php esc_html_e( 'Use this Node ID in your Connector plugin configuration.', 'chiral-hub-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Hub URL', 'chiral-hub-core' ); ?></th>
                        <td>
                            <code><?php echo esc_url( home_url() ); ?></code>
                            <p class="description"><?php esc_html_e( 'Enter this URL in your Connector plugin settings.', 'chiral-hub-core' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- RSS模式配置 -->
            <div id="rss-config" class="mode-config" style="<?php echo $sync_mode === 'rss' ? '' : 'display: none;'; ?>">
                <h4><?php esc_html_e( 'RSS Configuration', 'chiral-hub-core' ); ?></h4>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Node ID', 'chiral-hub-core' ); ?></th>
                        <td>
                            <input type="text" name="node_id" value="<?php echo esc_attr( $node_id ); ?>" class="regular-text" placeholder="my-blog" required>
                            <p class="description"><?php esc_html_e( 'Unique identifier for your blog (e.g., my-blog, myblog-com)', 'chiral-hub-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'RSS Feed URL', 'chiral-hub-core' ); ?></th>
                        <td>
                            <input type="url" name="rss_url" value="<?php echo esc_attr( $rss_url ); ?>" class="regular-text" placeholder="https://example.com/feed.xml">
                            <p class="description"><?php esc_html_e( 'Your blog\'s RSS/Atom feed URL for periodic content updates', 'chiral-hub-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sitemap_url"><?php esc_html_e( 'Sitemap URL', 'chiral-hub-core' ); ?></label>
                        </th>
                        <td>
                            <input type="url" name="sitemap_url" id="sitemap_url" value="<?php echo esc_attr( $sitemap_url ); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e( 'Optional: Enter the sitemap.xml URL for bulk import', 'chiral-hub-core' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="url_filter_mode"><?php esc_html_e( 'URL Filtering Strategy', 'chiral-hub-core' ); ?></label>
                        </th>
                        <td>
                            <?php
                            $url_filter_mode = get_user_meta( get_current_user_id(), '_chiral_url_filter_mode', true );
                            if ( empty( $url_filter_mode ) ) {
                                $url_filter_mode = 'exclude';
                            }
                            ?>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e( 'URL Filtering Strategy', 'chiral-hub-core' ); ?></legend>
                                <label style="display: block; margin-bottom: 15px;">
                                    <input type="radio" name="url_filter_mode" value="include" <?php checked( $url_filter_mode, 'include' ); ?> />
                                    <strong><?php esc_html_e( 'Include Mode (Precise)', 'chiral-hub-core' ); ?></strong>
                                    <span class="description" style="display: block; margin-left: 20px; color: #666; font-style: italic;">
                                        <?php esc_html_e( 'Only import URLs where article slugs are followed by content (e.g., /post/title, not /post/)', 'chiral-hub-core' ); ?>
                                    </span>
                                </label>
                                <label style="display: block;">
                                    <input type="radio" name="url_filter_mode" value="exclude" <?php checked( $url_filter_mode, 'exclude' ); ?> />
                                    <strong><?php esc_html_e( 'Exclude Mode (Broad)', 'chiral-hub-core' ); ?></strong>
                                    <span class="description" style="display: block; margin-left: 20px; color: #666; font-style: italic;">
                                        <?php esc_html_e( 'Import all URLs except those containing specific non-article slugs', 'chiral-hub-core' ); ?>
                                    </span>
                                </label>
                            </fieldset>
                            <p class="description" style="margin-top: 10px;">
                                <strong><?php esc_html_e( 'Recommendation:', 'chiral-hub-core' ); ?></strong>
                                <?php esc_html_e( 'Use Include Mode if your site has clear article URL patterns (like /posts/ or /blog/). Use Exclude Mode for sites without specific article URL patterns.', 'chiral-hub-core' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr class="url-include-row">
                        <th scope="row">
                            <label for="url_include_slugs"><?php esc_html_e( 'Article URL Slugs', 'chiral-hub-core' ); ?></label>
                        </th>
                        <td>
                            <?php
                            $url_include_slugs = get_user_meta( get_current_user_id(), '_chiral_url_include_slugs', true );
                            if ( empty( $url_include_slugs ) ) {
                                $url_include_slugs = 'post,posts,article,articles,blog,news';
                            }
                            ?>
                            
                            <div style="margin-bottom: 10px;">
                                <strong><?php esc_html_e( 'Quick Presets:', 'chiral-hub-core' ); ?></strong>
                                <button type="button" class="button button-small" onclick="setIncludePreset('wordpress')"><?php esc_html_e( 'WordPress', 'chiral-hub-core' ); ?></button>
                                <button type="button" class="button button-small" onclick="setIncludePreset('blog')"><?php esc_html_e( 'Blog', 'chiral-hub-core' ); ?></button>
                                <button type="button" class="button button-small" onclick="setIncludePreset('news')"><?php esc_html_e( 'News', 'chiral-hub-core' ); ?></button>
                                <button type="button" class="button button-small" onclick="setIncludePreset('clear')"><?php esc_html_e( 'Clear', 'chiral-hub-core' ); ?></button>
                            </div>
                            
                            <textarea name="url_include_slugs" id="url_include_slugs" rows="2" class="large-text"><?php echo esc_textarea( $url_include_slugs ); ?></textarea>
                            
                            <p class="description">
                                <?php esc_html_e( 'Comma-separated list of URL path segments that identify article URLs. Only URLs containing these segments will be imported.', 'chiral-hub-core' ); ?>
                                <br>
                                <?php esc_html_e( 'Example: post,article,blog - Only URLs with /post/, /article/, or /blog/ in their path will be imported.', 'chiral-hub-core' ); ?>
                                <br>
                                <strong><?php esc_html_e( 'Important: The slug must be followed by additional content (like article title or ID) to avoid importing archive pages.', 'chiral-hub-core' ); ?></strong>
                                <br>
                                <span style="color: #008a00;">✓ <?php esc_html_e( 'Will import: /blog/my-article-title, /post/123, /article/2024/news-story', 'chiral-hub-core' ); ?></span>
                                <br>
                                <span style="color: #d63638;">✗ <?php esc_html_e( 'Will skip: /blog/, /articles, /post (archive pages)', 'chiral-hub-core' ); ?></span>
                                <br>
                                <strong style="color: #d63638;"><?php esc_html_e( 'Note: This setting is only used when "Include Mode" is selected above.', 'chiral-hub-core' ); ?></strong>
                            </p>
                        </td>
                    </tr>
                    <tr class="url-exclude-row">
                        <th scope="row">
                            <label for="url_exclusions"><?php esc_html_e( 'Exclude URL Slugs', 'chiral-hub-core' ); ?></label>
                        </th>
                        <td>
                            <?php
                            $url_exclusions = get_user_meta( get_current_user_id(), '_chiral_url_exclusions', true );
                            if ( empty( $url_exclusions ) ) {
                                $url_exclusions = 'tag,tags,category,categories,archive,archives,page,pages,author,search,about,contact';
                            }
                            ?>
                            
                            <div style="margin-bottom: 10px;">
                                <strong><?php esc_html_e( 'Quick Presets:', 'chiral-hub-core' ); ?></strong>
                                <button type="button" class="button button-small" onclick="setExclusionPreset('basic')"><?php esc_html_e( 'Basic', 'chiral-hub-core' ); ?></button>
                                <button type="button" class="button button-small" onclick="setExclusionPreset('wordpress')"><?php esc_html_e( 'WordPress', 'chiral-hub-core' ); ?></button>
                                <button type="button" class="button button-small" onclick="setExclusionPreset('strict')"><?php esc_html_e( 'Strict', 'chiral-hub-core' ); ?></button>
                                <button type="button" class="button button-small" onclick="setExclusionPreset('clear')"><?php esc_html_e( 'Clear All', 'chiral-hub-core' ); ?></button>
                            </div>
                            
                            <textarea name="url_exclusions" id="url_exclusions" rows="3" class="large-text"><?php echo esc_textarea( $url_exclusions ); ?></textarea>
                            
                            <p class="description">
                                <?php esc_html_e( 'Comma-separated list of URL path segments to exclude from sitemap import. URLs containing these segments will be skipped.', 'chiral-hub-core' ); ?>
                                <br>
                                <?php esc_html_e( 'Example: tag,category,archive,page,author,search - URLs containing any of these segments will be excluded.', 'chiral-hub-core' ); ?>
                                <br>
                                <strong style="color: #d63638;"><?php esc_html_e( 'Note: This setting is only used when "Exclude Mode" is selected above.', 'chiral-hub-core' ); ?></strong>
                            </p>
                            
                            <?php
                            // 显示最后一次过滤统计
                            $filter_stats = get_user_meta( get_current_user_id(), '_chiral_last_filter_stats', true );
                            if ( !empty( $filter_stats ) ):
                            ?>
                            <div class="filter-stats" style="margin-top: 10px; padding: 10px; background: #f0f8ff; border: 1px solid #007cba; border-radius: 4px;">
                                <strong><?php esc_html_e( 'Last URL Filtering Results:', 'chiral-hub-core' ); ?></strong>
                                <?php if ( !empty( $filter_stats['filter_mode'] ) ): ?>
                                <span style="float: right; font-size: 12px; color: #666;">
                                    <?php 
                                    if ( $filter_stats['filter_mode'] === 'include' ) {
                                        esc_html_e( 'Include Mode', 'chiral-hub-core' );
                                    } else {
                                        esc_html_e( 'Exclude Mode', 'chiral-hub-core' );
                                    }
                                    ?>
                                </span>
                                <?php endif; ?>
                                <ul style="margin: 5px 0; padding-left: 20px;">
                                    <li><?php printf( esc_html__( 'Total URLs found: %d', 'chiral-hub-core' ), intval( $filter_stats['original_count'] ) ); ?></li>
                                    <li><?php printf( esc_html__( 'URLs accepted: %d', 'chiral-hub-core' ), intval( $filter_stats['filtered_count'] ) ); ?></li>
                                    <li style="color: #d63638;"><?php 
                                        if ( !empty( $filter_stats['filter_mode'] ) && $filter_stats['filter_mode'] === 'include' ) {
                                            printf( esc_html__( 'URLs rejected (no article slug): %d', 'chiral-hub-core' ), intval( $filter_stats['excluded_count'] ) );
                                        } else {
                                            printf( esc_html__( 'URLs excluded: %d', 'chiral-hub-core' ), intval( $filter_stats['excluded_count'] ) );
                                        }
                                    ?></li>
                                </ul>
                                <?php if ( !empty( $filter_stats['excluded_urls'] ) ): ?>
                                <details style="margin-top: 10px;">
                                    <summary style="cursor: pointer; color: #007cba;">
                                        <?php 
                                        if ( !empty( $filter_stats['filter_mode'] ) && $filter_stats['filter_mode'] === 'include' ) {
                                            esc_html_e( 'Show examples of rejected URLs', 'chiral-hub-core' );
                                        } else {
                                            esc_html_e( 'Show examples of excluded URLs', 'chiral-hub-core' );
                                        }
                                        ?>
                                    </summary>
                                    <ul style="margin: 5px 0; padding-left: 20px; font-size: 12px; color: #666;">
                                        <?php foreach ( array_slice( $filter_stats['excluded_urls'], 0, 10 ) as $excluded_url ): ?>
                                        <li><?php echo esc_html( $excluded_url ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Sync Frequency', 'chiral-hub-core' ); ?></th>
                        <td>
                            <select name="sync_frequency">
                                <option value="hourly" <?php selected( $sync_frequency, 'hourly' ); ?>><?php esc_html_e( 'Every Hour', 'chiral-hub-core' ); ?></option>
                                <option value="daily" <?php selected( $sync_frequency, 'daily' ); ?>><?php esc_html_e( 'Daily', 'chiral-hub-core' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'How often to check your RSS feed for updates', 'chiral-hub-core' ); ?></p>
                        </td>
                    </tr>
                    
                    <?php 
                    // 显示RSS健康状态
                    $rss_health = get_user_meta( $current_user_id, '_chiral_rss_health', true );
                    if ( !empty( $rss_url ) && is_array( $rss_health ) ): ?>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'RSS Health Status', 'chiral-hub-core' ); ?></th>
                        <td>
                            <?php if ( $rss_health['is_healthy'] ): ?>
                                <span style="color: #00a32a;">✅ <?php esc_html_e( 'Healthy', 'chiral-hub-core' ); ?></span>
                            <?php else: ?>
                                <span style="color: #d63638;">❌ <?php esc_html_e( 'Issues Detected', 'chiral-hub-core' ); ?></span>
                                <?php if ( !empty( $rss_health['error_message'] ) ): ?>
                                    <br><small style="color: #666;"><?php echo esc_html( $rss_health['error_message'] ); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ( !empty( $rss_health['last_check'] ) ): ?>
                                <p class="description">
                                    <?php 
                                    printf( 
                                        esc_html__( 'Last checked: %s', 'chiral-hub-core' ),
                                        esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rss_health['last_check'] ) )
                                    );
                                    ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <div class="rss-actions">
                    <button type="button" class="button" id="test-rss-connection">
                        <?php esc_html_e( 'Test Connection', 'chiral-hub-core' ); ?>
                    </button>
                    <?php if ( !empty( $rss_url ) ): ?>
                        <button type="button" class="button button-secondary" id="sync-rss-now">
                            <?php esc_html_e( 'Sync RSS Now', 'chiral-hub-core' ); ?>
                        </button>
                    <?php endif; ?>
                    <?php if ( !empty( $sitemap_url ) ): ?>
                        <button type="button" class="button button-primary" id="start-sitemap-import">
                            <?php esc_html_e( 'Start Sitemap Import', 'chiral-hub-core' ); ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="button button-secondary" id="reset-import-status">
                        <?php esc_html_e( 'Reset Import Status', 'chiral-hub-core' ); ?>
                    </button>
                </div>

                <?php if ( $last_sync ): ?>
                    <p>
                        <strong><?php esc_html_e( 'Last Sync:', 'chiral-hub-core' ); ?></strong> 
                        <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync ) ); ?>
                    </p>
                <?php endif; ?>

                <!-- 导入进度显示 -->
                <div class="import-progress">
                    <h3><?php esc_html_e( 'Sitemap Import Progress', 'chiral-hub-core' ); ?></h3>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 0%;"></div>
                    </div>
                    <span class="progress-text">0% (0/0)</span>
                    
                    <div class="import-stats">
                        <div class="stat-item">
                            <span class="stat-label"><?php esc_html_e( 'Total URLs', 'chiral-hub-core' ); ?></span>
                            <span class="stat-value total-items">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><?php esc_html_e( 'Processed', 'chiral-hub-core' ); ?></span>
                            <span class="stat-value processed">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><?php esc_html_e( 'Success', 'chiral-hub-core' ); ?></span>
                            <span class="stat-value success">-</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label"><?php esc_html_e( 'Failed', 'chiral-hub-core' ); ?></span>
                            <span class="stat-value failed">-</span>
                        </div>
                                                <div class="stat-item">
                            <span class="stat-label"><?php esc_html_e( 'ETA', 'chiral-hub-core' ); ?></span>
                            <span class="stat-value eta">-</span>
                        </div>
                    </div>

                    <div class="current-processing">
                        <strong><?php esc_html_e( 'Current:', 'chiral-hub-core' ); ?></strong>
                        <span class="current-url">-</span>
                    </div>
                </div>
            </div>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Configuration', 'chiral-hub-core' ); ?>">
            </p>
        </form>

        <!-- Tab Styles and JS -->
        <style>
        .post-status-publish { color: #008a00; }
        .post-status-draft { color: #b32d2e; }
        .post-status-private { color: #8b4513; }
        .button-link-delete { color: #a00; }
        .button-link-delete:hover { color: #dc3232; }
        
        .mode-selector {
            margin: 20px 0;
            display: flex;
            gap: 20px;
        }
        
        .mode-option {
            display: block;
            cursor: pointer;
        }
        
        .mode-card {
            border: 2px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            transition: all 0.3s ease;
            min-width: 200px;
        }
        
        .mode-card:hover {
            border-color: #007cba;
        }
        
        .mode-option input[type="radio"]:checked + .mode-card {
            border-color: #007cba;
            background-color: #f0f8ff;
        }
        
        .mode-config {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .rss-actions {
            margin-top: 20px;
        }
        
        .rss-actions .button {
            margin-right: 10px;
        }
        
        .import-progress {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #007cba;
            transition: width 0.3s ease;
        }
        
        .import-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin: 10px 0;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-label {
            display: block;
            font-size: 12px;
            color: #666;
        }
        
        .stat-value {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .url-include-row,
        .url-exclude-row {
            transition: opacity 0.3s ease;
        }
        
        .url-include-row.hidden,
        .url-exclude-row.hidden {
            display: none;
        }
        </style>

        <script type="text/javascript">
        var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
        console.log('ajaxurl:', ajaxurl); // Debug log
        jQuery(document).ready(function($) {
            console.log('jQuery ready, looking for .re-sync-post buttons:', $('.re-sync-post').length); // Debug log
            
            // Test button for debugging
            $('#test-javascript').click(function() {
                alert('JavaScript is working! ajaxurl: ' + ajaxurl);
                console.log('Test button clicked, .re-sync-post buttons found:', $('.re-sync-post').length);
            });
            // 排除规则预设
            window.setExclusionPreset = function(preset) {
                var presets = {
                    'basic': 'tag,tags,category,categories,archive,archives,page,pages,author,search',
                    'wordpress': 'tag,tags,category,categories,archive,archives,page,pages,author,search,about,contact,privacy,terms',
                    'strict': 'tag,tags,category,categories,cat,archive,archives,date,year,month,page,pages,static,author,authors,user,users,search,feed,rss,atom,sitemap,admin,api,about,contact,privacy,terms,disclaimer,index,home,blog',
                    'clear': ''
                };
                
                if (presets[preset] !== undefined) {
                    $('#url_exclusions').val(presets[preset]);
                }
            };

            // 包含规则预设
            window.setIncludePreset = function(preset) {
                var presets = {
                    'wordpress': 'post,posts',
                    'blog': 'blog,article,articles',
                    'news': 'news,story,stories',
                    'clear': ''
                };
                
                if (presets[preset] !== undefined) {
                    $('#url_include_slugs').val(presets[preset]);
                }
            };

            // 过滤模式切换处理
            function toggleFilterModeVisibility() {
                var mode = $('input[name="url_filter_mode"]:checked').val();
                if (mode === 'include') {
                    $('.url-include-row').show();
                    $('.url-exclude-row').hide();
                } else {
                    $('.url-include-row').hide();
                    $('.url-exclude-row').show();
                }
            }

            // 初始化显示状态
            toggleFilterModeVisibility();

            // 监听过滤模式变化
            $('input[name="url_filter_mode"]').change(function() {
                toggleFilterModeVisibility();
            });

            // Mode selection
            $('input[name="sync_mode"]').change(function() {
                $('.mode-config').hide();
                if ($(this).val() === 'rss') {
                    $('#rss-config').show();
                } else {
                    $('#wordpress-config').show();
                }
            });

            // Test RSS Connection
            $('#test-rss-connection').on('click', function() {
                var button = $(this);
                var originalText = button.text();
                button.text('<?php esc_html_e( 'Testing...', 'chiral-hub-core' ); ?>').prop('disabled', true);
                
                var rssUrl = $('#rss-config input[name="rss_url"]').val();
                var sitemapUrl = $('#rss-config input[name="sitemap_url"]').val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'chiral_test_rss_connection',
                        nonce: '<?php echo wp_create_nonce( 'chiral_porter_ajax' ); ?>',
                        rss_url: rssUrl,
                        sitemap_url: sitemapUrl
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php esc_html_e( 'Connection successful! RSS feed is accessible.', 'chiral-hub-core' ); ?>');
                        } else {
                            alert('<?php esc_html_e( 'Connection failed: ', 'chiral-hub-core' ); ?>' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e( 'Request failed. Please try again.', 'chiral-hub-core' ); ?>');
                    },
                    complete: function() {
                        button.text(originalText).prop('disabled', false);
                    }
                });
            });

            // Manual RSS Sync
            $('#sync-rss-now').on('click', function() {
                var button = $(this);
                var originalText = button.text();
                button.text('<?php esc_html_e( 'Syncing...', 'chiral-hub-core' ); ?>').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'chiral_sync_rss_now',
                        nonce: '<?php echo wp_create_nonce( 'chiral_porter_ajax' ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php esc_html_e( 'RSS sync completed successfully!', 'chiral-hub-core' ); ?>' + 
                                  (response.data.message ? '\n' + response.data.message : ''));
                            // 刷新页面显示最新数据
                            location.reload();
                        } else {
                            alert('<?php esc_html_e( 'RSS sync failed: ', 'chiral-hub-core' ); ?>' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php esc_html_e( 'Request failed. Please try again.', 'chiral-hub-core' ); ?>');
                    },
                    complete: function() {
                        button.text(originalText).prop('disabled', false);
                    }
                });
            });

            // Start sitemap import
            $('#start-sitemap-import').click(function() {
                var $button = $(this);
                var sitemapUrl = $('input[name="sitemap_url"]').val();

                if (!sitemapUrl) {
                    alert('<?php echo esc_js( __( 'Please enter Sitemap URL first.', 'chiral-hub-core' ) ); ?>');
                    return;
                }

                if (!confirm('<?php echo esc_js( __( 'This will import all URLs from your sitemap. This may take some time. Continue?', 'chiral-hub-core' ) ); ?>')) {
                    return;
                }

                $button.prop('disabled', true).text('<?php echo esc_js( __( 'Starting...', 'chiral-hub-core' ) ); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'chiral_start_sitemap_import',
                        nonce: '<?php echo wp_create_nonce( 'chiral_porter_ajax' ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.import-progress').show();
                            startProgressMonitoring();
                        } else {
                            alert('<?php echo esc_js( __( 'Failed to start import:', 'chiral-hub-core' ) ); ?> ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js( __( 'Failed to start import. Please try again.', 'chiral-hub-core' ) ); ?>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php echo esc_js( __( 'Start Sitemap Import', 'chiral-hub-core' ) ); ?>');
                    }
                });
            });

            // Reset import status
            $('#reset-import-status').click(function() {
                var $button = $(this);

                if (!confirm('<?php echo esc_js( __( 'This will reset the import status and allow you to start a new import. Continue?', 'chiral-hub-core' ) ); ?>')) {
                    return;
                }

                $button.prop('disabled', true).text('<?php echo esc_js( __( 'Resetting...', 'chiral-hub-core' ) ); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'chiral_reset_import_status',
                        nonce: '<?php echo wp_create_nonce( 'chiral_porter_ajax' ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.import-progress').hide();
                            alert('<?php echo esc_js( __( 'Import status reset successfully. You can now start a new import.', 'chiral-hub-core' ) ); ?>');
                            location.reload(); // Refresh to update the page state
                        } else {
                            alert('<?php echo esc_js( __( 'Failed to reset import status:', 'chiral-hub-core' ) ); ?> ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js( __( 'Failed to reset import status. Please try again.', 'chiral-hub-core' ) ); ?>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('<?php echo esc_js( __( 'Reset Import Status', 'chiral-hub-core' ) ); ?>');
                    }
                });
            });

            // Progress monitoring
            function startProgressMonitoring() {
                var progressInterval = setInterval(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'chiral_get_import_progress',
                            nonce: '<?php echo wp_create_nonce( 'chiral_porter_ajax' ); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var data = response.data;
                                updateProgress(data);
                                
                                // 检查是否超时（30分钟没有进展）
                                if (data.last_update && data.started_at) {
                                    var now = Math.floor(Date.now() / 1000);
                                    var lastUpdate = parseInt(data.last_update) || parseInt(data.started_at);
                                    var timeoutMinutes = 30;
                                    
                                    if (now - lastUpdate > timeoutMinutes * 60) {
                                        clearInterval(progressInterval);
                                        $('.import-progress .progress-text').html('<span style="color: #d63638;"><?php echo esc_js( __( 'Import appears to be stuck. Please reset import status.', 'chiral-hub-core' ) ); ?></span>');
                                        return;
                                    }
                                }
                                
                                if (!data.is_importing) {
                                    clearInterval(progressInterval);
                                    $('.import-progress').fadeOut();
                                    location.reload(); // Refresh to show new data
                                }
                            } else {
                                // 如果获取进度失败，可能导入已经完成或出错
                                clearInterval(progressInterval);
                                $('.import-progress .progress-text').html('<span style="color: #d63638;"><?php echo esc_js( __( 'Failed to get import progress. Import may have completed or failed.', 'chiral-hub-core' ) ); ?></span>');
                            }
                        },
                        error: function() {
                            clearInterval(progressInterval);
                            $('.import-progress .progress-text').html('<span style="color: #d63638;"><?php echo esc_js( __( 'Failed to monitor import progress.', 'chiral-hub-core' ) ); ?></span>');
                        }
                    });
                }, 2000); // Check every 2 seconds
            }

            function updateProgress(data) {
                var progress = data.progress || 0;
                $('.progress-fill').css('width', progress + '%');
                $('.progress-text').text(progress + '% (' + (data.processed_items || 0) + '/' + (data.total_items || 0) + ')');
                $('.total-items').text(data.total_items || 0);
                $('.processed').text(data.processed_items || 0);
                $('.success').text(data.success_count || 0);
                $('.failed').text(data.error_count || 0);
                $('.current-url').text(data.current_url || '');
                
                if (data.eta_minutes) {
                    $('.eta').text(data.eta_minutes + ' <?php echo esc_js( __( 'minutes', 'chiral-hub-core' ) ); ?>');
                } else {
                    $('.eta').text('—');
                }
            }

            // Re Sync single post functionality (use document delegation for dynamic content)
            $(document).on('click', '.re-sync-post', function() {
                console.log('ReSync button clicked!'); // Debug log
                var button = $(this);
                var postId = button.data('post-id');
                var sourceUrl = button.data('source-url');
                
                console.log('Post ID:', postId, 'Source URL:', sourceUrl); // Debug log
                
                if (!confirm('<?php echo esc_js( __( 'Are you sure you want to re-sync this article? This will fetch the latest content from the source and may change the article status to pending if the Hub is in pending mode.', 'chiral-hub-core' ) ); ?>')) {
                    return;
                }
                
                button.prop('disabled', true).text('<?php echo esc_js( __( 'Re Syncing...', 'chiral-hub-core' ) ); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'chiral_re_sync_post',
                        nonce: '<?php echo wp_create_nonce( 'chiral_porter_ajax' ); ?>',
                        post_id: postId,
                        source_url: sourceUrl
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('<?php echo esc_js( __( 'Article re-synced successfully!', 'chiral-hub-core' ) ); ?>');
                            location.reload(); // Refresh to show updated data
                        } else {
                            alert('<?php echo esc_js( __( 'Error: ', 'chiral-hub-core' ) ); ?>' + (response.data.message || '<?php echo esc_js( __( 'Unknown error occurred.', 'chiral-hub-core' ) ); ?>'));
                            button.prop('disabled', false).text('<?php echo esc_js( __( 'Re Sync', 'chiral-hub-core' ) ); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js( __( 'Network error occurred.', 'chiral-hub-core' ) ); ?>');
                        button.prop('disabled', false).text('<?php echo esc_js( __( 'Re Sync', 'chiral-hub-core' ) ); ?>');
                    }
                });
            });

            // 检查页面加载时是否有进行中的导入
            $(document).ready(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'chiral_get_import_progress',
                        nonce: '<?php echo wp_create_nonce( 'chiral_porter_ajax' ); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data.is_importing) {
                            $('.import-progress').show();
                            startProgressMonitoring();
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 处理配置保存
     *
     * @since 1.2.0
     */
    private function handle_config_save() {
        // Clean any output buffer to prevent headers already sent error
        if ( ob_get_level() ) {
            ob_clean();
        }
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'chiral-hub-core' ) );
        }
        
        $user_id = get_current_user_id();
        $sync_mode = sanitize_text_field( $_POST['sync_mode'] );
        
        update_user_meta( $user_id, '_chiral_sync_mode', $sync_mode );
        
        if ( $sync_mode === 'rss' ) {
            $node_id = sanitize_text_field( $_POST['node_id'] );
            $rss_url = esc_url_raw( $_POST['rss_url'] );
            $sitemap_url = esc_url_raw( $_POST['sitemap_url'] );
            $sync_frequency = sanitize_text_field( $_POST['sync_frequency'] );
            
            update_user_meta( $user_id, '_chiral_node_id', $node_id );
            update_user_meta( $user_id, '_chiral_rss_url', $rss_url );
            update_user_meta( $user_id, '_chiral_sitemap_url', $sitemap_url );
            update_user_meta( $user_id, '_chiral_rss_sync_frequency', $sync_frequency );

            // 保存URL排除规则
            if ( isset( $_POST['url_exclusions'] ) ) {
                update_user_meta( $user_id, '_chiral_url_exclusions', sanitize_textarea_field( $_POST['url_exclusions'] ) );
            }

            // 保存URL过滤模式和包含规则
            if ( isset( $_POST['url_filter_mode'] ) ) {
                update_user_meta( $user_id, '_chiral_url_filter_mode', sanitize_text_field( $_POST['url_filter_mode'] ) );
            }
            
            if ( isset( $_POST['url_include_slugs'] ) ) {
                update_user_meta( $user_id, '_chiral_url_include_slugs', sanitize_textarea_field( $_POST['url_include_slugs'] ) );
            }
        } elseif ( $sync_mode === 'wordpress' ) {
            // 为WordPress模式生成默认Node ID（如果不存在）
            $existing_node_id = get_user_meta( $user_id, '_chiral_node_id', true );
            if ( empty( $existing_node_id ) ) {
                $node_id = 'wp-node-' . $user_id;
                update_user_meta( $user_id, '_chiral_node_id', $node_id );
            }
        }
        
        // Use wp_safe_redirect which is safer and clean URL
        $redirect_url = add_query_arg( 
            array( 'updated' => '1', 'tab' => 'config' ), 
            wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=porter-chiral-data&tab=config' )
        );
        
        wp_safe_redirect( $redirect_url );
            exit;
        }

    /**
     * AJAX: 测试RSS连接
     *
     * @since 1.2.0
     */
    public function ajax_test_rss_connection() {
        check_ajax_referer( 'chiral_porter_ajax', 'nonce' );
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'chiral-hub-core' ) ) );
        }
        
        $rss_url = esc_url_raw( $_POST['rss_url'] );
        $sitemap_url = esc_url_raw( $_POST['sitemap_url'] );
        
        if ( class_exists( 'Chiral_Hub_RSS_Crawler' ) ) {
            $rss_crawler = new Chiral_Hub_RSS_Crawler( 'chiral-hub-core', CHIRAL_HUB_CORE_VERSION );
            $test_result = $rss_crawler->test_connection( $rss_url, $sitemap_url );
            
            if ( is_wp_error( $test_result ) ) {
                wp_send_json_error( array( 
                    'message' => $test_result->get_error_message()
                ) );
            }
            
            wp_send_json_success( $test_result );
        } else {
            wp_send_json_error( array( 'message' => __( 'RSS Crawler not available.', 'chiral-hub-core' ) ) );
        }
    }

    /**
     * AJAX: 开始Sitemap导入
     *
     * @since 1.2.0
     */
    public function ajax_start_sitemap_import() {
        check_ajax_referer( 'chiral_porter_ajax', 'nonce' );
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'chiral-hub-core' ) ) );
        }
        
        $user_id = get_current_user_id();
        $sitemap_url = get_user_meta( $user_id, '_chiral_sitemap_url', true );
        
        if ( empty( $sitemap_url ) ) {
            wp_send_json_error( array( 'message' => __( 'Please save your Sitemap URL first.', 'chiral-hub-core' ) ) );
        }
        
        if ( class_exists( 'Chiral_Hub_RSS_Crawler' ) ) {
            $rss_crawler = new Chiral_Hub_RSS_Crawler( 'chiral-hub-core', CHIRAL_HUB_CORE_VERSION );
            $import_result = $rss_crawler->initiate_sitemap_import( $user_id, $sitemap_url );
            
            if ( is_wp_error( $import_result ) ) {
                wp_send_json_error( array( 'message' => $import_result->get_error_message() ) );
            }
            
            wp_send_json_success( $import_result );
        } else {
            wp_send_json_error( array( 'message' => __( 'RSS Crawler not available.', 'chiral-hub-core' ) ) );
        }
    }

    /**
     * AJAX: 获取导入进度
     *
     * @since 1.2.0
     */
    public function ajax_get_import_progress() {
        check_ajax_referer( 'chiral_porter_ajax', 'nonce' );
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'chiral-hub-core' ) ) );
        }
        
        $user_id = get_current_user_id();
        $import_status = get_user_meta( $user_id, '_chiral_import_status', true );
        $is_importing = get_user_meta( $user_id, '_chiral_import_in_progress', true );
        
        if ( !$import_status ) {
            $import_status = array();
        }
        
        $import_status['is_importing'] = (bool) $is_importing;
        
        wp_send_json_success( $import_status );
    }

    /**
     * AJAX: 重置导入状态
     *
     * @since 1.2.0
     */
    public function ajax_reset_import_status() {
        check_ajax_referer( 'chiral_porter_ajax', 'nonce' );
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'chiral-hub-core' ) ) );
        }
        
        $user_id = get_current_user_id();
        
        // 清除导入状态
        delete_user_meta( $user_id, '_chiral_import_in_progress' );
        delete_user_meta( $user_id, '_chiral_import_status' );
        delete_user_meta( $user_id, '_chiral_import_urls' );
        
        // 清理相关的计划事件
        wp_clear_scheduled_hook( 'chiral_hub_process_sitemap_import', array( $user_id ) );
        
        wp_send_json_success( array( 'message' => __( 'Import status reset successfully.', 'chiral-hub-core' ) ) );
    }

    /**
     * AJAX: 手动RSS同步
     *
     * @since 1.2.0
     */
    public function ajax_sync_rss_now() {
        check_ajax_referer( 'chiral_porter_ajax', 'nonce' );
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'chiral-hub-core' ) ) );
        }
        
        $user_id = get_current_user_id();
        $sync_mode = get_user_meta( $user_id, '_chiral_sync_mode', true );
        
        if ( $sync_mode !== 'rss' ) {
            wp_send_json_error( array( 'message' => __( 'RSS sync is only available in RSS mode.', 'chiral-hub-core' ) ) );
        }
        
        $rss_url = get_user_meta( $user_id, '_chiral_rss_url', true );
        if ( empty( $rss_url ) ) {
            wp_send_json_error( array( 'message' => __( 'Please configure RSS URL first.', 'chiral-hub-core' ) ) );
        }
        
        if ( class_exists( 'Chiral_Hub_RSS_Crawler' ) ) {
            $rss_crawler = new Chiral_Hub_RSS_Crawler( 'chiral-hub-core', CHIRAL_HUB_CORE_VERSION );
            
            // 记录同步开始时间
            $sync_start = time();
            
            // 执行RSS同步
            $sync_result = $rss_crawler->process_rss_updates( $user_id );
            
            if ( is_wp_error( $sync_result ) ) {
                wp_send_json_error( array( 'message' => $sync_result->get_error_message() ) );
            }
            
            // 更新最后同步时间
            update_user_meta( $user_id, '_chiral_rss_last_sync', $sync_start );
            
            // 构建成功消息
            $message = '';
            if ( is_array( $sync_result ) ) {
                $new_items = $sync_result['new_items'] ?? 0;
                $updated_items = $sync_result['updated_items'] ?? 0;
                
                if ( $new_items > 0 || $updated_items > 0 ) {
                    $message = sprintf( 
                        __( 'Sync completed! New items: %d, Updated items: %d', 'chiral-hub-core' ),
                        $new_items,
                        $updated_items
                    );
                } else {
                    $message = __( 'Sync completed! No new or updated items found.', 'chiral-hub-core' );
                }
            }
            
            wp_send_json_success( array( 'message' => $message ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'RSS Crawler not available.', 'chiral-hub-core' ) ) );
        }
    }

    /**
     * AJAX: Re-sync single post from source URL
     *
     * @since 1.2.0
     */
    public function ajax_re_sync_post() {
        check_ajax_referer( 'chiral_porter_ajax', 'nonce' );
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'chiral-hub-core' ) ) );
        }
        
        $post_id = intval( $_POST['post_id'] );
        $source_url = esc_url_raw( $_POST['source_url'] );
        $user_id = get_current_user_id();
        
        if ( empty( $post_id ) || empty( $source_url ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post ID or source URL.', 'chiral-hub-core' ) ) );
        }
        
        // 验证用户有权限重新同步这篇文章
        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( array( 'message' => __( 'Post not found.', 'chiral-hub-core' ) ) );
        }
        
        // 检查用户权限 - 必须是作者或同一节点的用户
        $user_node_id = get_user_meta( $user_id, '_chiral_node_id', true );
        $post_node_id = get_post_meta( $post_id, '_chiral_node_id', true );
        
        if ( $post->post_author != $user_id && $post_node_id !== $user_node_id ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to re-sync this post.', 'chiral-hub-core' ) ) );
        }
        
        // 验证这是RSS模式的文章
        $source_type = get_post_meta( $post_id, '_chiral_source_type', true );
        if ( $source_type !== 'rss' ) {
            wp_send_json_error( array( 'message' => __( 'Only RSS/Sitemap sourced posts can be re-synced.', 'chiral-hub-core' ) ) );
        }
        
        if ( ! class_exists( 'Chiral_Hub_RSS_Crawler' ) ) {
            wp_send_json_error( array( 'message' => __( 'RSS Crawler not available.', 'chiral-hub-core' ) ) );
        }
        
        $rss_crawler = new Chiral_Hub_RSS_Crawler( 'chiral-hub-core', CHIRAL_HUB_CORE_VERSION );
        
        // 获取源URL的内容
        $response = wp_remote_get( $source_url, array(
            'timeout' => 30,
            'user-agent' => 'Chiral Hub RSS Crawler/' . CHIRAL_HUB_CORE_VERSION
        ) );
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => sprintf( 
                __( 'Failed to fetch content from source: %s', 'chiral-hub-core' ), 
                $response->get_error_message() 
            ) ) );
        }
        
        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            wp_send_json_error( array( 'message' => sprintf( 
                __( 'Source URL returned HTTP %d error.', 'chiral-hub-core' ), 
                $http_code 
            ) ) );
        }
        
        $html_content = wp_remote_retrieve_body( $response );
        if ( empty( $html_content ) ) {
            wp_send_json_error( array( 'message' => __( 'No content found at source URL.', 'chiral-hub-core' ) ) );
        }
        
        // 解析HTML内容
        if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html_content, $title_matches ) ) {
            $new_title = html_entity_decode( strip_tags( $title_matches[1] ), ENT_QUOTES, 'UTF-8' );
        } else {
            $new_title = $post->post_title; // 保持原标题
        }
        
        // 尝试提取主要内容
        $new_content = '';
        if ( preg_match( '/<article[^>]*>(.*?)<\/article>/is', $html_content, $article_matches ) ) {
            $new_content = $article_matches[1];
        } elseif ( preg_match( '/<main[^>]*>(.*?)<\/main>/is', $html_content, $main_matches ) ) {
            $new_content = $main_matches[1];
        } else {
            // 简单的body内容提取
            if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html_content, $body_matches ) ) {
                $new_content = $body_matches[1];
            } else {
                $new_content = $html_content;
            }
        }
        
        // 清理内容
        $new_content = wp_kses_post( $new_content );
        
        // 生成新的内容哈希
        $new_content_hash = md5( $new_content . $new_title );
        $old_content_hash = get_post_meta( $post_id, '_chiral_content_hash', true );
        
        // 检查内容是否有变化
        if ( $new_content_hash === $old_content_hash ) {
            wp_send_json_success( array( 'message' => __( 'No changes detected in the source content.', 'chiral-hub-core' ) ) );
        }
        
        // 准备更新数据
        $update_data = array(
            'ID' => $post_id,
            'post_title' => sanitize_text_field( $new_title ),
            'post_content' => $new_content,
            'post_modified' => current_time( 'mysql' ),
            'post_modified_gmt' => current_time( 'mysql', 1 )
        );
        
        // 检查Hub的Porter注册策略
        $options = get_option( 'chiral-hub-core_options' );
        $registration_policy = isset( $options['new_porter_registration'] ) ? $options['new_porter_registration'] : 'default_status';
        
        // 如果Hub设置为pending模式，重新同步的文章应该进入待审状态
        if ( $registration_policy === 'pending' ) {
            $update_data['post_status'] = 'pending';
        }
        
        // 更新文章
        $result = wp_update_post( $update_data, true );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => sprintf( 
                __( 'Failed to update post: %s', 'chiral-hub-core' ), 
                $result->get_error_message() 
            ) ) );
        }
        
        // 更新元数据
        update_post_meta( $post_id, '_chiral_content_hash', $new_content_hash );
        update_post_meta( $post_id, '_chiral_last_crawl_check', time() );
        
        // 构建成功消息
        $message = __( 'Article re-synced successfully!', 'chiral-hub-core' );
        if ( $registration_policy === 'pending' && isset( $update_data['post_status'] ) && $update_data['post_status'] === 'pending' ) {
            $message .= ' ' . __( 'The article has been set to pending review status.', 'chiral-hub-core' );
        }
        
        wp_send_json_success( array( 'message' => $message ) );
    }
} 
<?php
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * Provides the HTML for the Chiral Hub Core Node Management page.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/admin/views
 */

// Ensure this file is loaded within WordPress.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Ensure Chiral_Hub_Roles and Chiral_Hub_CPT classes are available if not already loaded
if ( ! class_exists( 'Chiral_Hub_Roles' ) ) {
    require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-roles.php';
}
if ( ! class_exists( 'Chiral_Hub_CPT' ) ) {
    require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-cpt.php';
}
if ( ! class_exists( 'Chiral_Hub_Node_Checker' ) ) {
    require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-node-checker.php';
}

// Initialize node checker
$node_checker = new Chiral_Hub_Node_Checker( 'chiral-hub-core', CHIRAL_HUB_CORE_VERSION );

// Handle AJAX requests for node status checking
if ( isset( $_POST['action'] ) && isset( $_POST['nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is handled below before processing
    $action = wp_unslash( sanitize_text_field( $_POST['action'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Missing -- Nonce verification is handled below before processing
    $nonce = wp_unslash( sanitize_text_field( $_POST['nonce'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Missing -- Nonce verification is handled below before processing
    
    if ( $action === 'check_node_status' && wp_verify_nonce( $nonce, 'chiral_hub_node_check' ) ) {
        $porter_id = isset( $_POST['porter_id'] ) ? absint( wp_unslash( $_POST['porter_id'] ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Missing -- Nonce has been verified above
        
        if ( $porter_id ) {
            $status = $node_checker->check_node_status( $porter_id );
            wp_send_json_success( $status );
        } else {
            wp_send_json_error( array( 'message' => __( 'Invalid Porter ID', 'chiral-hub-core' ) ) );
        }
    }

    if ( $action === 'check_all_nodes_status' && wp_verify_nonce( $nonce, 'chiral_hub_node_check' ) ) {
        $statuses = $node_checker->check_all_nodes_status( true );
        wp_send_json_success( $statuses );
    }
}

?>

<div class="wrap chiral-hub-node-management-wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div class="chiral-hub-node-actions" style="margin-bottom: 20px;">
        <button type="button" id="check-all-nodes" class="button button-primary">
            <?php esc_html_e( 'Check All Node Status', 'chiral-hub-core' ); ?>
        </button>
        <span id="check-all-status" style="margin-left: 10px;"></span>
    </div>

    <div id="col-container">
        <div id="col-left">
            <div class="col-wrap">
                <h2><?php esc_html_e( 'Connected Chiral Nodes (Porters)', 'chiral-hub-core' ); ?></h2>
                <p><?php esc_html_e( 'This section lists all users with the Chiral Porter role. These users represent connected Chiral Nodes. You can manage their Application Passwords by editing their profiles. Each Porter should have a unique Node ID assigned via user meta (key: <code>_chiral_node_id</code>).', 'chiral-hub-core' ); ?></p>
                <?php
                $porters_args = array(
                    'role'    => Chiral_Hub_Roles::ROLE_SLUG,
                    'orderby' => 'login',
                    'order'   => 'ASC',
                    'fields'  => array( 'ID', 'user_login', 'display_name', 'user_email' )
                );
                $porters = get_users( $porters_args );

                if ( ! empty( $porters ) ) :
                ?>
                    <table class="wp-list-table widefat fixed striped users">
                        <thead>
                            <tr>
                                <th scope="col" class="manage-column column-username column-primary"><?php esc_html_e( 'Username', 'chiral-hub-core' ); ?></th>
                                <th scope="col" class="manage-column column-name"><?php esc_html_e( 'Display Name', 'chiral-hub-core' ); ?></th>
                                <th scope="col" class="manage-column column-node-id"><?php esc_html_e( 'Node ID', 'chiral-hub-core' ); ?></th>
                                <th scope="col" class="manage-column column-plugin-version"><?php esc_html_e( 'Plugin Version', 'chiral-hub-core' ); ?></th>
                                <th scope="col" class="manage-column column-status"><?php esc_html_e( 'Node Status', 'chiral-hub-core' ); ?></th>
                                <th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'chiral-hub-core' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="the-list">
                            <?php foreach ( $porters as $porter ) : 
                                $edit_link = get_edit_user_link( $porter->ID );
                                $node_id = get_user_meta( $porter->ID, '_chiral_node_id', true );
                                $cached_status = get_user_meta( $porter->ID, '_chiral_node_status_cache', true );
                                $last_check = get_user_meta( $porter->ID, '_chiral_node_last_status_check', true );
                            ?>
                                <tr data-porter-id="<?php echo esc_attr( $porter->ID ); ?>">
                                    <td class="username column-username has-row-actions column-primary" data-colname="Username">
                                        <strong><a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $porter->user_login ); ?></a></strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit Profile & App Passwords', 'chiral-hub-core' ); ?></a>
                                            </span>
                                            | <span class="view">
                                                <a href="<?php echo esc_url( add_query_arg( array( 'page' => $this->plugin_name . '-node-management', 'action' => 'view_porter_data', 'porter_id' => $porter->ID ), admin_url( 'admin.php' ) ) ); ?>">
                                                    <?php esc_html_e( 'View Synced Data', 'chiral-hub-core' ); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="name column-name" data-colname="Name"><?php echo esc_html( $porter->display_name ); ?></td>
                                    <td class="node-id column-node-id" data-colname="Node ID">
                                        <?php if ( !empty($node_id) ) : ?>
                                            <code><?php echo esc_html( $node_id ); ?></code>
                                        <?php else : ?>
                                            <span style="color:red;"><?php esc_html_e( 'Not Set', 'chiral-hub-core' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="plugin-version column-plugin-version" data-colname="Plugin Version">
                                        <span class="plugin-version-display">
                                            <?php 
                                            if ( $cached_status && isset( $cached_status['plugin_version'] ) ) {
                                                echo esc_html( $cached_status['plugin_version'] );
                                            } else {
                                                echo '<span style="color: #666;">' . esc_html__( 'Not checked', 'chiral-hub-core' ) . '</span>';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="status column-status" data-colname="Status">
                                        <span class="status-display">
                                            <?php 
                                            if ( $cached_status ) {
                                                $status_class = '';
                                                switch ( $cached_status['status'] ) {
                                                    case 'active':
                                                        $status_class = 'status-active';
                                                        break;
                                                    case 'inactive':
                                                        $status_class = 'status-inactive';
                                                        break;
                                                    case 'disconnected':
                                                        $status_class = 'status-disconnected';
                                                        break;
                                                    default:
                                                        $status_class = 'status-error';
                                                }
                                                echo '<span class="' . esc_attr( $status_class ) . '">' . esc_html( $cached_status['message'] ) . '</span>';
                                                
                                                if ( $last_check ) {
                                                    $time_ago = human_time_diff( $last_check, current_time( 'timestamp' ) );
                                                    // translators: %s: Time duration (e.g., "2 hours", "30 minutes")
                                                    echo '<br><small style="color: #666;">' . wp_kses_post( sprintf( __( 'Last checked: %s ago', 'chiral-hub-core' ), $time_ago ) ) . '</small>';
                                                }
                                            } else {
                                                echo '<span style="color: #666;">' . esc_html__( 'Not checked', 'chiral-hub-core' ) . '</span>';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="actions column-actions" data-colname="Actions">
                                        <button type="button" class="button button-secondary check-single-node" data-porter-id="<?php echo esc_attr( $porter->ID ); ?>">
                                            <?php esc_html_e( 'Check Status', 'chiral-hub-core' ); ?>
                                        </button>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'page' => $this->plugin_name . '-node-management', 'action' => 'view_porter_data', 'porter_id' => $porter->ID ), admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary">
                                            <?php esc_html_e( 'View Synced Data', 'chiral-hub-core' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e( 'No Chiral Porters found. A Chiral Porter user is automatically created when a new Chiral Node connects for the first time, or you can create one manually and assign the Chiral Porter role and the <code>_chiral_node_id</code> user meta.', 'chiral-hub-core' ); ?></p>
                <?php endif; ?>
            </div>
        </div> <!-- /col-left -->

        <div id="col-right">
            <div class="col-wrap">
                <?php
                // Handle viewing synced data for a specific porter
                if ( isset( $_GET['action'] ) && $_GET['action'] === 'view_porter_data' && isset( $_GET['porter_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
                    $porter_id_to_view = absint( $_GET['porter_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
                    $porter_to_view = get_user_by( 'ID', $porter_id_to_view );

                    if ( $porter_to_view && in_array( Chiral_Hub_Roles::ROLE_SLUG, (array) $porter_to_view->roles ) ) {
                        // translators: %s: Porter display name
                        echo '<h2>' . wp_kses_post( sprintf( __( 'Synced Data from %s', 'chiral-hub-core' ), esc_html( $porter_to_view->display_name ) ) ) . '</h2>';
                        
                        $node_id_to_view = get_user_meta( $porter_id_to_view, '_chiral_node_id', true );

                        $data_args = array(
                            'post_type' => Chiral_Hub_CPT::CPT_SLUG,
                            'posts_per_page' => 20, // TODO: Paginate this later
                            'post_status' => array( 'publish', 'draft', 'private', 'pending' ), // Include all statuses for admin review
                            'author' => $porter_id_to_view, // Data synced by this porter
                            // Optionally filter by node_id if it's more reliable than author in some edge cases
                            // 'meta_query' => array(
                            //     array(
                            //         'key' => '_chiral_node_id',
                            //         'value' => $node_id_to_view,
                            //     )
                            // )
                        );
                        $synced_data_query = new WP_Query( $data_args );

                        if ( $synced_data_query->have_posts() ) {
                            echo '<table class="wp-list-table widefat fixed striped posts">';
                            echo '<thead><tr><th class="manage-column column-title column-primary">' . esc_html__( 'Title (Hub)', 'chiral-hub-core' ) . '</th><th class="manage-column column-status">' . esc_html__( 'Status', 'chiral-hub-core' ) . '</th><th class="manage-column column-original-source-url">' . esc_html__( 'Original Source URL', 'chiral-hub-core' ) . '</th><th class="manage-column column-date">' . esc_html__( 'Date Synced', 'chiral-hub-core' ) . '</th></tr></thead>';
                            echo '<tbody>';
                            while ( $synced_data_query->have_posts() ) {
                                $synced_data_query->the_post();
                                $source_url = get_post_meta( get_the_ID(), 'chiral_source_url', true );
                                $edit_post_link = get_edit_post_link( get_the_ID() );
                                $post_status = get_post_status( get_the_ID() );
                                $status_label = ucfirst( $post_status );
                                if ( $post_status === 'pending' ) {
                                    $status_label = '<span style="color: #d63638; font-weight: bold;">' . esc_html__( 'Pending Review', 'chiral-hub-core' ) . '</span>';
                                }
                                echo '<tr>';
                                echo '<td class="title column-title has-row-actions column-primary" data-colname="Title"><strong><a class="row-title" href="' . esc_url($edit_post_link) . '">' . esc_html( get_the_title() ) . '</a></strong>';
                                echo '<div class="row-actions"><span class="edit"><a href="'. esc_url($edit_post_link) .'">' . esc_html__('Edit', 'chiral-hub-core') . '</a></span></div></td>';
                                echo '<td class="status column-status" data-colname="Status">' . wp_kses_post( $status_label ) . '</td>';
                                echo '<td class="original-source-url column-original-source-url" data-colname="Original Source URL"><a href="' . esc_url( $source_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $source_url ) . '</a></td>';
                                echo '<td class="date column-date" data-colname="Date Synced">' . esc_html( get_the_date() ) . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                            wp_reset_postdata();
                            // TODO: Add pagination for synced data
                        } else {
                            echo '<p>' . esc_html__( 'No data synced from this Porter yet.', 'chiral-hub-core' ) . '</p>';
                        }
                    } else {
                        echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid Porter ID specified or user is not a Chiral Porter.', 'chiral-hub-core' ) . '</p></div>';
                    }
                } else {
                    echo '<p>' . esc_html__( 'Select a Porter from the list to view their synced data.', 'chiral-hub-core' ) . '</p>';
                }
                ?>
            </div>
        </div> <!-- /col-right -->
    </div> <!-- /col-container -->
</div>

<style>
.status-active {
    color: #00a32a;
    font-weight: bold;
}
.status-inactive {
    color: #dba617;
    font-weight: bold;
}
.status-disconnected {
    color: #d63638;
    font-weight: bold;
}
.status-error {
    color: #d63638;
}
.chiral-hub-node-actions {
    padding: 10px;
    background: #f1f1f1;
    border-radius: 4px;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var nonce = '<?php echo esc_js( wp_create_nonce( 'chiral_hub_node_check' ) ); ?>';
    
    // Check single node status
    $('.check-single-node').on('click', function() {
        var button = $(this);
        var porterId = button.data('porter-id');
        var row = button.closest('tr');
        
        button.prop('disabled', true).text('<?php echo esc_js( __( 'Checking...', 'chiral-hub-core' ) ); ?>');
        
        $.post(ajaxurl, {
            action: 'chiral_hub_check_node_status',
            porter_id: porterId,
            nonce: nonce
        }, function(response) {
            if (response.success) {
                updateNodeRow(row, response.data);
            } else {
                alert('<?php echo esc_js( __( 'Check failed: ', 'chiral-hub-core' ) ); ?>' + (response.data.message || '<?php echo esc_js( __( 'Unknown error', 'chiral-hub-core' ) ); ?>'));
            }
        }).always(function() {
            button.prop('disabled', false).text('<?php echo esc_js( __( 'Check Status', 'chiral-hub-core' ) ); ?>');
        });
    });
    
    // Check all nodes status
    $('#check-all-nodes').on('click', function() {
        var button = $(this);
        var statusSpan = $('#check-all-status');
        
        button.prop('disabled', true).text('<?php echo esc_js( __( 'Checking...', 'chiral-hub-core' ) ); ?>');
        statusSpan.text('<?php echo esc_js( __( 'Checking all nodes, please wait...', 'chiral-hub-core' ) ); ?>');
        
        $.post(ajaxurl, {
            action: 'chiral_hub_check_all_nodes_status',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                $.each(response.data, function(porterId, status) {
                    var row = $('tr[data-porter-id="' + porterId + '"]');
                    if (row.length) {
                        updateNodeRow(row, status);
                    }
                });
                statusSpan.text('<?php echo esc_js( __( 'Check completed', 'chiral-hub-core' ) ); ?>').css('color', 'green');
            } else {
                statusSpan.text('<?php echo esc_js( __( 'Check failed', 'chiral-hub-core' ) ); ?>').css('color', 'red');
            }
        }).always(function() {
            button.prop('disabled', false).text('<?php echo esc_js( __( 'Check All Node Status', 'chiral-hub-core' ) ); ?>');
            setTimeout(function() {
                statusSpan.text('');
            }, 3000);
        });
    });
    
    function updateNodeRow(row, status) {
        // Update plugin version
        var versionCell = row.find('.plugin-version-display');
        if (status.plugin_version) {
            versionCell.text(status.plugin_version);
        }
        
        // Update status
        var statusCell = row.find('.status-display');
        var statusClass = '';
        switch (status.status) {
            case 'active':
                statusClass = 'status-active';
                break;
            case 'inactive':
                statusClass = 'status-inactive';
                break;
            case 'disconnected':
                statusClass = 'status-disconnected';
                break;
            default:
                statusClass = 'status-error';
        }
        
        var statusHtml = '<span class="' + statusClass + '">' + status.message + '</span>';
        if (status.last_checked) {
            statusHtml += '<br><small style="color: #666;"><?php echo esc_js( __( 'Just checked', 'chiral-hub-core' ) ); ?></small>';
        }
        
        statusCell.html(statusHtml);
    }
});
</script>
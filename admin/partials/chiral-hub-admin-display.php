<?php

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/admin/partials
 */

if ( ! class_exists( 'Chiral_Hub_Roles' ) ) {
    require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-roles.php';
}

if ( ! class_exists( 'Chiral_Hub_CPT' ) ) {
    require_once CHIRAL_HUB_CORE_PLUGIN_DIR . 'includes/class-chiral-hub-cpt.php';
}

$porters = get_users( array(
    'role' => Chiral_Hub_Roles::ROLE_SLUG,
) );
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <div class="notice notice-info">
        <p>
            <?php
            /* translators: %1$s: Plugin name (Chiral Hub Core) */
            echo wp_kses_post( sprintf( esc_html__( 'Welcome to %1$s! This plugin allows your WordPress site to act as a central hub for collecting and organizing content from multiple Chiral Nodes (other WordPress sites with the Chiral Connector plugin installed).', 'chiral-hub-core' ), '<strong>Chiral Hub Core</strong>' ) );
            ?>
        </p>
    </div>

    <div class="chiral-hub-dashboard">
        <h2><?php esc_html_e( 'Network Configuration', 'chiral-hub-core' ); ?></h2>
        <p><?php esc_html_e( 'Current network settings and content statistics.', 'chiral-hub-core' ); ?></p>
        
        <div class="chiral-hub-network-settings">
            <?php
            $options = get_option( 'chiral-hub-core_options' );
            $network_name = isset( $options['network_name'] ) ? sanitize_text_field( $options['network_name'] ) : '';
            $chiral_count = wp_count_posts( 'chiral_data' );
            $total_posts = $chiral_count->publish + $chiral_count->pending + $chiral_count->draft + $chiral_count->private;
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Current Network Name', 'chiral-hub-core' ); ?></th>
                    <td>
                        <strong><?php echo $network_name ? esc_html( $network_name ) : '<em>' . esc_html__( 'Not configured', 'chiral-hub-core' ) . '</em>'; ?></strong>
                        <p class="description">
                            <?php 
                            printf( 
                                /* translators: %1$s: URL to settings page */
                                esc_html__( 'To change this, go to <a href="%1$s">Settings → Chiral Hub Settings</a>. When you update the network name, all existing Chiral Data posts will be automatically updated.', 'chiral-hub-core' ),
                                esc_url( admin_url( 'options-general.php?page=chiral-hub-core-settings' ) )
                            );
                            ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Total Chiral Data Posts', 'chiral-hub-core' ); ?></th>
                    <td>
                        <strong><?php echo esc_html( number_format( $total_posts ) ); ?></strong>
                        <p class="description"><?php esc_html_e( 'All posts automatically include the network name for Jetpack Related Posts functionality.', 'chiral-hub-core' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php if ( ! $network_name ) : ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e( 'Please configure your network name in the settings. All future posts will automatically include this network name.', 'chiral-hub-core' ); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <h2><?php esc_html_e( 'Connected Chiral Nodes (Porters)', 'chiral-hub-core' ); ?></h2>
        <p><?php esc_html_e( 'This section lists all users with the Chiral Porter role. These users represent connected Chiral Nodes. You can manage their Application Passwords by editing their profiles.', 'chiral-hub-core' ); ?></p>

        <?php if ( ! empty( $porters ) ) : ?>
            <div class="chiral-hub-porters-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-username column-primary"><?php esc_html_e( 'Username', 'chiral-hub-core' ); ?></th>
                            <th scope="col" class="manage-column column-name"><?php esc_html_e( 'Display Name', 'chiral-hub-core' ); ?></th>
                            <th scope="col" class="manage-column column-email"><?php esc_html_e( 'Email', 'chiral-hub-core' ); ?></th>
                            <th scope="col" class="manage-column column-node-id"><?php esc_html_e( 'Node ID', 'chiral-hub-core' ); ?></th>
                            <th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'chiral-hub-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $porters as $porter ) : ?>
                            <?php
                            $edit_link = get_edit_user_link( $porter->ID );
                            $posts_link = admin_url( 'edit.php?post_type=' . Chiral_Hub_CPT::CPT_SLUG . '&author=' . $porter->ID );
                            $node_id = get_user_meta( $porter->ID, '_chiral_node_id', true );
                            ?>
                            <tr>
                                <td class="username column-username has-row-actions column-primary" data-colname="Username">
                                    <strong><?php echo esc_html( $porter->user_login ); ?></strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit Profile & App Passwords', 'chiral-hub-core' ); ?></a>
                                        </span>
                                        <?php if ( ! empty( $posts_link ) ) : ?>
                                            <span class="view"> | 
                                                <a href="<?php echo esc_url( $posts_link ); ?>" aria-label="<?php
                                                    /* translators: %1$s: Porter display name */
                                                    echo esc_attr( sprintf( esc_html__( 'View posts by %1$s', 'chiral-hub-core' ), $porter->display_name ) );
                                                    ?>">
                                                    <?php esc_html_e( 'View Synced Data', 'chiral-hub-core' ); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="name column-name" data-colname="Display Name">
                                    <?php echo esc_html( $porter->display_name ); ?>
                                </td>
                                <td class="email column-email" data-colname="Email">
                                    <a href="mailto:<?php echo esc_attr( $porter->user_email ); ?>"><?php echo esc_html( $porter->user_email ); ?></a>
                                </td>
                                <td class="node-id column-node-id" data-colname="Node ID">
                                    <?php echo esc_html( $node_id ? $node_id : esc_html__( 'Not Set', 'chiral-hub-core' ) ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <p><?php esc_html_e( 'No Chiral Porters found. A Chiral Porter user is automatically created when a new Chiral Node connects for the first time, or you can create one manually and assign the Chiral Porter role.', 'chiral-hub-core' ); ?></p>
        <?php endif; ?>

        <?php
        // Handle viewing synced data for a specific porter
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'view_porter_data' && isset( $_GET['porter_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
            $porter_id_to_view = absint( $_GET['porter_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only operation for display purposes
            $porter_to_view = get_user_by( 'ID', $porter_id_to_view );

            if ( $porter_to_view && in_array( Chiral_Hub_Roles::ROLE_SLUG, $porter_to_view->roles ) ) {
                /* translators: %1$s: Porter display name */
                echo '<h3>' . wp_kses_post( sprintf( esc_html__( 'Synced Data from %1$s', 'chiral-hub-core' ), esc_html( $porter_to_view->display_name ) ) ) . '</h3>';
                
                $node_id_to_view = get_user_meta( $porter_id_to_view, '_chiral_node_id', true );

                $data_args = array(
                    'post_type' => Chiral_Hub_CPT::CPT_SLUG,
                    'posts_per_page' => 20, // Paginate this later
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
                    echo '<thead><tr><th>' . esc_html__( 'Title (Hub)', 'chiral-hub-core' ) . '</th><th>' . esc_html__( 'Status', 'chiral-hub-core' ) . '</th><th>' . esc_html__( 'Original Source URL', 'chiral-hub-core' ) . '</th><th>' . esc_html__( 'Date Synced', 'chiral-hub-core' ) . '</th></tr></thead>';
                    echo '<tbody>';
                    while ( $synced_data_query->have_posts() ) {
                        $synced_data_query->the_post();
                        $node_id = get_post_meta( get_the_ID(), '_chiral_node_id', true );
                        $source_url = get_post_meta( get_the_ID(), 'chiral_source_url', true );
                        $original_post_id = get_post_meta( get_the_ID(), '_chiral_data_original_post_id', true );
                        $post_status = get_post_status( get_the_ID() );
                        $status_label = ucfirst( $post_status );
                        if ( $post_status === 'pending' ) {
                            $status_label = '<span style="color: #d63638; font-weight: bold;">' . esc_html__( 'Pending Review', 'chiral-hub-core' ) . '</span>';
                        }
                        echo '<tr>';
                        echo '<td><a href="' . esc_url( get_edit_post_link( get_the_ID() ) ) . '">' . esc_html( get_the_title() ) . '</a></td>';
                        echo '<td>' . wp_kses_post( $status_label ) . '</td>';
                        echo '<td><a href="' . esc_url( $source_url ) . '" target="_blank">' . esc_html( $source_url ) . '</a></td>';
                        echo '<td>' . esc_html( get_the_date() ) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    wp_reset_postdata();
                } else {
                    echo '<p>' . esc_html__( 'No data synced from this Porter yet.', 'chiral-hub-core' ) . '</p>';
                }
            } else {
                echo '<p class="error">' . esc_html__( 'Invalid Porter ID specified.', 'chiral-hub-core' ) . '</p>';
            }
        }
        ?>
    </div>
</div>
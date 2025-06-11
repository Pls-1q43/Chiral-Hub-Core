(function( $ ) {
    'use strict';

    /**
     * All of the code for your admin-facing JavaScript source
     * should reside in this file.
     *
     * Note: It has been assumed you will write jQuery code here,
     * however you can use vanilla JavaScript as well.
     */

    $(document).ready(function() {
        // Example: Add a confirmation dialog before a potentially destructive action.
        // $('.delete-porter-button').on('click', function(e) {
        //     if (!confirm('Are you sure you want to delete this Porter and all their synced data? This cannot be undone.')) {
        //         e.preventDefault();
        //     }
        // });

        // Example: AJAX call for a custom admin action
        // $('#my-custom-admin-button').on('click', function() {
        //     var data = {
        //         'action': 'chiral_hub_my_custom_action', // This should match the wp_ajax_ hook in PHP
        //         'security': chiral_hub_admin_ajax.nonce, // Pass a nonce for security
        //         'param': 'some_value'
        //     };

        //     $.post(chiral_hub_admin_ajax.ajax_url, data, function(response) {
        //         if(response.success) {
        //             alert('Action successful: ' + response.data.message);
        //         } else {
        //             alert('Action failed: ' + response.data.message);
        //         }
        //     });
        // });

        // Add any interactive elements for the admin settings page here.
        // For instance, showing/hiding sections based on other selections.
        console.log('Chiral Hub Core Admin JS Loaded');

    });

})( jQuery );
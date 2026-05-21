<?php
/**
 * Uninstall handler for IdleGuard
 *
 * Removes plugin options on uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'idleguard_settings' );

// Multisite support: remove site option if present
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
    delete_site_option( 'idleguard_settings' );
}

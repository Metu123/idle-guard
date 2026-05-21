<?php
/**
 * Plugin Name: IdleGuard
 * Plugin URI:  https://example.com/idleguard
 * Description: Auto-logout inactive users after a configurable idle period with a warning modal and multi-tab sync.
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: idleguard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Constants
define( 'IDLEGUARD_VERSION', '1.0.0' );
define( 'IDLEGUARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'IDLEGUARD_URL', plugin_dir_url( __FILE__ ) );

require_once IDLEGUARD_DIR . 'includes/class-idleguard.php';

function idleguard_init_plugin() {
    $plugin = IdleGuard\Core::instance();
    $plugin->run();
}

idleguard_init_plugin();

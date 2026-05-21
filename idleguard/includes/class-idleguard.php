<?php

namespace IdleGuard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Core {
    private static $instance = null;
    public $admin = null;
    public $public = null;

    private function __construct() {
        $this->includes();
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        register_activation_hook( IDLEGUARD_DIR . 'idleguard.php', array( $this, 'activate' ) );
        register_deactivation_hook( IDLEGUARD_DIR . 'idleguard.php', array( $this, 'deactivate' ) );
    }

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function includes() {
        require_once IDLEGUARD_DIR . 'admin/class-idleguard-admin.php';
        require_once IDLEGUARD_DIR . 'public/class-idleguard-public.php';
    }

    public function run() {
        // Instantiate admin/public depending on context
        if ( is_admin() ) {
            $this->admin = new Admin\IdleGuard_Admin();
            $this->admin->init();
        }
        $this->public = new Public\IdleGuard_Public();
        $this->public->init();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'idleguard', false, dirname( plugin_basename( __FILE__ ) ) . '/../languages/' );
    }

    public function activate() {
        $defaults = array(
            'enabled' => 1,
            'default_timeout' => 900, // 15 minutes
            'admin_timeout' => 3600, // 1 hour
            'warning_duration' => 30, // 30 seconds
            'excluded_roles' => array(),
            'redirect_url' => wp_login_url(),
        );
        if ( false === get_option( 'idleguard_settings', false ) ) {
            add_option( 'idleguard_settings', $defaults );
        }
    }

    public function deactivate() {
        // Keep settings on deactivation. Cleanup would be implemented in uninstall.
    }
}

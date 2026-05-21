<?php

namespace IdleGuard\Public;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IdleGuard_Public {
    private $options;

    public function init() {
        $this->options = get_option( 'idleguard_settings', array() );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_idleguard_keepalive', array( $this, 'ajax_keepalive' ) );
        add_action( 'wp_ajax_idleguard_logout', array( $this, 'ajax_logout' ) );
        add_action( 'init', array( $this, 'maybe_inject_modal_markup' ) );
    }

    public function enqueue_assets() {
        if ( empty( $this->options['enabled'] ) ) {
            return;
        }
        wp_enqueue_style( 'idleguard-public', IDLEGUARD_URL . 'assets/css/idleguard-public.css', array(), IDLEGUARD_VERSION );
        wp_enqueue_script( 'idleguard-public', IDLEGUARD_URL . 'assets/js/idleguard-public.js', array(), IDLEGUARD_VERSION, true );

        $user = wp_get_current_user();
        $role = isset( $user->roles[0] ) ? $user->roles[0] : 'guest';

        // Determine timeout based on role
        $timeout = isset( $this->options['default_timeout'] ) ? intval( $this->options['default_timeout'] ) : 900;
        if ( in_array( $role, (array) ( $this->options['excluded_roles'] ?? array() ), true ) ) {
            // excluded role: disable script
            return;
        }
        if ( $role === 'administrator' && isset( $this->options['admin_timeout'] ) ) {
            $timeout = intval( $this->options['admin_timeout'] );
        }

        $data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'timeout' => $timeout,
            'warning_duration' => isset( $this->options['warning_duration'] ) ? intval( $this->options['warning_duration'] ) : 30,
            'nonce' => wp_create_nonce( 'idleguard_nonce' ),
            'redirect_url' => isset( $this->options['redirect_url'] ) ? esc_url( $this->options['redirect_url'] ) : wp_login_url(),
            'user_role' => $role,
        );

        wp_localize_script( 'idleguard-public', 'IdleGuardSettings', $data );
    }

    public function ajax_keepalive() {
        check_ajax_referer( 'idleguard_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Not logged in' ), 403 );
        }
        wp_send_json_success( array( 'message' => 'Alive' ) );
    }

    public function ajax_logout() {
        check_ajax_referer( 'idleguard_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Not logged in' ), 403 );
        }
        wp_logout();
        wp_send_json_success( array( 'message' => 'Logged out' ) );
    }

    public function maybe_inject_modal_markup() {
        if ( ! is_user_logged_in() ) {
            return;
        }
        // Modal markup will be injected by JS when needed; keep this hook for future server-side needs.
    }
}

<?php

namespace IdleGuard\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IdleGuard_Admin {
    private $options;

    public function init() {
        $this->options = get_option( 'idleguard_settings', array() );
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_post_idleguard_force_logout', array( $this, 'handle_force_logout' ) );
    }

    public function add_settings_page() {
        add_options_page(
            __( 'IdleGuard', 'idleguard' ),
            __( 'IdleGuard', 'idleguard' ),
            'manage_options',
            'idleguard-settings',
            array( $this, 'settings_page_html' )
        );
    }

    public function register_settings() {
        register_setting( 'idleguard_options', 'idleguard_settings', array( $this, 'sanitize' ) );

        add_settings_section( 'idleguard_main', __( 'IdleGuard Settings', 'idleguard' ), '__return_false', 'idleguard-settings' );

        add_settings_field( 'enabled', __( 'Enable IdleGuard', 'idleguard' ), array( $this, 'field_enabled' ), 'idleguard-settings', 'idleguard_main' );
        add_settings_field( 'default_timeout', __( 'Default timeout (seconds)', 'idleguard' ), array( $this, 'field_default_timeout' ), 'idleguard-settings', 'idleguard_main' );
        add_settings_field( 'admin_timeout', __( 'Admin timeout (seconds)', 'idleguard' ), array( $this, 'field_admin_timeout' ), 'idleguard-settings', 'idleguard_main' );
        add_settings_field( 'warning_duration', __( 'Warning duration (seconds)', 'idleguard' ), array( $this, 'field_warning_duration' ), 'idleguard-settings', 'idleguard_main' );
        add_settings_field( 'excluded_roles', __( 'Excluded roles', 'idleguard' ), array( $this, 'field_excluded_roles' ), 'idleguard-settings', 'idleguard_main' );
        add_settings_field( 'redirect_url', __( 'Redirect URL after logout', 'idleguard' ), array( $this, 'field_redirect_url' ), 'idleguard-settings', 'idleguard_main' );
    }

    public function enqueue_assets( $hook ) {
        if ( 'settings_page_idleguard-settings' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'idleguard-admin', IDLEGUARD_URL . 'assets/css/idleguard-admin.css', array(), IDLEGUARD_VERSION );
        wp_enqueue_script( 'idleguard-admin', IDLEGUARD_URL . 'assets/js/idleguard-admin.js', array(), IDLEGUARD_VERSION, true );
    }

    public function sanitize( $input ) {
        $output = array();
        $output['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
        $output['default_timeout'] = isset( $input['default_timeout'] ) ? absint( $input['default_timeout'] ) : 900;
        $output['admin_timeout'] = isset( $input['admin_timeout'] ) ? absint( $input['admin_timeout'] ) : 3600;
        $output['warning_duration'] = isset( $input['warning_duration'] ) ? absint( $input['warning_duration'] ) : 30;
        $output['excluded_roles'] = array();
        if ( ! empty( $input['excluded_roles'] ) && is_array( $input['excluded_roles'] ) ) {
            $roles = wp_roles()->roles;
            foreach ( $input['excluded_roles'] as $role ) {
                if ( isset( $roles[ $role ] ) ) {
                    $output['excluded_roles'][] = sanitize_text_field( $role );
                }
            }
        }
        $output['redirect_url'] = isset( $input['redirect_url'] ) ? esc_url_raw( $input['redirect_url'] ) : wp_login_url();
        return $output;
    }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $opts = get_option( 'idleguard_settings', array() );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'IdleGuard', 'idleguard' ); ?></h1>

            <?php if ( isset( $_GET['idleguard_forcelogout'] ) && intval( $_GET['idleguard_forcelogout'] ) === 1 ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'All user sessions have been terminated.', 'idleguard' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'idleguard_options' );
                do_settings_sections( 'idleguard-settings' );
                submit_button();
                ?>
            </form>

            <hr />
            <h2><?php esc_html_e( 'Maintenance', 'idleguard' ); ?></h2>
            <p><?php esc_html_e( 'Force all users to be logged out. Useful after a security incident or policy change.', 'idleguard' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'idleguard_force_logout', 'idleguard_force_logout_nonce' ); ?>
                <input type="hidden" name="action" value="idleguard_force_logout">
                <?php submit_button( __( 'Force logout all users', 'idleguard' ), 'delete', 'idleguard_force_logout', true ); ?>
            </form>

        </div>
        <?php
    }

    /**
     * Handle admin action to force logout all users by destroying their session tokens.
     */
    public function handle_force_logout() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'idleguard' ) );
        }
        check_admin_referer( 'idleguard_force_logout', 'idleguard_force_logout_nonce' );

        require_once ABSPATH . WPINC . '/class-wp-user.php';

        $users = get_users( array( 'fields' => 'ID' ) );
        if ( ! empty( $users ) ) {
            foreach ( $users as $user_id ) {
                if ( class_exists( '\WP_Session_Tokens' ) ) {
                    $tokens = \WP_Session_Tokens::get_instance( $user_id );
                    if ( $tokens ) {
                        $tokens->destroy_all();
                    }
                }
            }
        }

        $redirect = add_query_arg( 'idleguard_forcelogout', '1', admin_url( 'options-general.php?page=idleguard-settings' ) );
        wp_redirect( $redirect );
        exit;
    }

    /* Fields */
    public function field_enabled() {
        $opts = get_option( 'idleguard_settings', array() );
        $val = ! empty( $opts['enabled'] ) ? 1 : 0;
        echo '<label><input type="checkbox" name="idleguard_settings[enabled]" value="1" ' . checked( 1, $val, false ) . '> ' . esc_html__( 'Enable idle logout', 'idleguard' ) . '</label>';
    }

    public function field_default_timeout() {
        $opts = get_option( 'idleguard_settings', array() );
        $val = isset( $opts['default_timeout'] ) ? intval( $opts['default_timeout'] ) : 900;
        echo '<input type="number" min="30" name="idleguard_settings[default_timeout]" value="' . esc_attr( $val ) . '" class="small-text">';
    }

    public function field_admin_timeout() {
        $opts = get_option( 'idleguard_settings', array() );
        $val = isset( $opts['admin_timeout'] ) ? intval( $opts['admin_timeout'] ) : 3600;
        echo '<input type="number" min="60" name="idleguard_settings[admin_timeout]" value="' . esc_attr( $val ) . '" class="small-text">';
    }

    public function field_warning_duration() {
        $opts = get_option( 'idleguard_settings', array() );
        $val = isset( $opts['warning_duration'] ) ? intval( $opts['warning_duration'] ) : 30;
        echo '<input type="number" min="5" name="idleguard_settings[warning_duration]" value="' . esc_attr( $val ) . '" class="small-text">';
    }

    public function field_excluded_roles() {
        $opts = get_option( 'idleguard_settings', array() );
        $roles = wp_roles()->roles;
        $selected = isset( $opts['excluded_roles'] ) ? (array) $opts['excluded_roles'] : array();
        foreach ( $roles as $role_key => $role ) {
            echo '<label style="display:block;margin:2px 0;"><input type="checkbox" name="idleguard_settings[excluded_roles][]" value="' . esc_attr( $role_key ) . '" ' . ( in_array( $role_key, $selected, true ) ? 'checked' : '' ) . '> ' . esc_html( $role['name'] ) . '</label>';
        }
    }

    public function field_redirect_url() {
        $opts = get_option( 'idleguard_settings', array() );
        $val = isset( $opts['redirect_url'] ) ? esc_url( $opts['redirect_url'] ) : wp_login_url();
        echo '<input type="url" name="idleguard_settings[redirect_url]" value="' . esc_attr( $val ) . '" class="regular-text">';
    }
}

# IdleGuard

IdleGuard is a lightweight WordPress plugin that automatically logs out inactive users after a configurable idle period. It shows a responsive warning modal with a countdown and supports multi-tab synchronization.

Installation:

1. Upload the `idleguard` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings → IdleGuard to configure timeouts, warning duration, excluded roles and redirect URL.

Security:

- All AJAX endpoints use WordPress nonces.
- Settings are sanitized using WordPress APIs.

Developer notes:

- Files are under `includes/`, `admin/`, `public/`, and `assets/`.
- Main classes: `IdleGuard\Core`, `IdleGuard\Admin\IdleGuard_Admin`, `IdleGuard\Public\IdleGuard_Public`.


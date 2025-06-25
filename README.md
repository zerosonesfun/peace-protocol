# Peace Protocol ✌️

A decentralized way for WordPress admins to share peace, respect, and follow each other with cryptographic handshakes.

[![WordPress](https://img.shields.io/badge/WordPress-6.0+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.1-orange.svg)](https://github.com/your-username/peace-protocol)

## 🌟 Features

- **Cryptographic Handshakes**: Secure token-based authentication for peace sharing
- **Decentralized Federation**: Connect with other WordPress sites without central authority
- **Peace Feed**: View incoming peace messages from other sites
- **Automatic Button**: Floating peace hand button on your site
- **Shortcode Support**: `[peace_log_wall]` to display peace messages
- **Admin Dashboard**: Manage tokens, feeds, and settings
- **Multi-language Support**: Available in English, Spanish, French, Japanese, and Chinese
- **Dark Mode Support**: Automatic theme adaptation
- **Mobile Responsive**: Works seamlessly on all devices

## 📋 Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- Administrator privileges for setup

## 🚀 Installation

### Method 1: WordPress Admin (Recommended)

1. Download the plugin ZIP file
2. Go to **Plugins > Add New** in your WordPress admin
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

### Method 2: Manual Installation

1. Upload the `peace-protocol` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Settings > Peace Protocol** to configure

## ⚙️ Configuration

### Initial Setup

1. Navigate to **Settings > Peace Protocol** in your WordPress admin
2. Generate your first authentication token
3. Configure your site settings
4. The plugin will automatically create necessary database tables

### Token Management

- **Generate New Token**: Create additional tokens for rotation
- **Active Token**: The first token is automatically synced to your browser
- **Token Rotation**: Tokens are automatically rotated for security

### Settings

- **Hide Auto Button**: Disable the floating peace hand button (use shortcode instead)
- **Peace Feeds**: View sites you've interacted with
- **Site Identities**: Manage your site's cryptographic identity

## 🎯 Usage

### Automatic Peace Button

The plugin automatically adds a floating peace hand button (✌️) to your site. Users can click it to send peace to other WordPress sites.

### Shortcodes

#### Display Peace Log Wall

```php
[peace_log_wall]
```

#### Display Login Button (peace hand emoji - if auto insertion is disabled in settings)

```php
[peace_hand_button]
```

This shortcode displays the last 10 peace messages received from other sites.

### REST API Endpoints

The plugin provides REST API endpoints for federation:

- `GET /wp-json/peace-protocol/v1/feed` - Get peace feed
- `POST /wp-json/peace-protocol/v1/peace` - Send peace to another site

### Frontend JavaScript

The plugin includes frontend JavaScript for:
- Peace button interactions
- Modal dialogs
- Federation handshakes
- Local storage management

## 🔧 Development

### File Structure

```
peace-protocol/
├── includes/
│   ├── admin-pages.php      # Admin dashboard
│   ├── frontend-button.php  # Frontend peace button
│   ├── register-cpt.php     # Custom post types
│   ├── rest-endpoints.php   # REST API endpoints
│   └── shortcodes.php       # Shortcode implementations
├── js/
│   └── frontend.js          # Frontend JavaScript
├── languages/               # Translation files
├── peace-protocol.php       # Main plugin file
└── README.md               # This file
```

### Custom Post Types

- **peace_log**: Stores incoming peace messages
- **peace_feed**: Manages federated site feeds

### Hooks and Filters

The plugin uses WordPress standard hooks and filters for extensibility.

### Translation Ready

The plugin is fully translation-ready with:
- Text domain: `peace-protocol`
- POT file: `languages/peace-protocol.pot`
- Available languages: English, Spanish, French, Japanese, Chinese

## 🌐 Federation

Peace Protocol enables decentralized federation between WordPress sites:

1. **Handshake Process**: Sites exchange cryptographic tokens
2. **Feed Subscription**: Sites subscribe to each other's peace feeds
3. **Message Exchange**: Peace messages are shared between federated sites
4. **Authorization**: OAuth-like flow for secure site-to-site communication

## 🔒 Security

- **Token-based Authentication**: Secure cryptographic tokens
- **Nonce Verification**: CSRF protection on all forms
- **Input Sanitization**: All user inputs are properly sanitized
- **Output Escaping**: All outputs are properly escaped
- **Capability Checks**: Proper WordPress capability checks

## 📝 Changelog

### Version 1.0.1
- Initial release
- Core federation functionality
- Admin dashboard
- Frontend peace button
- Shortcode support
- Multi-language support

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Billy Wilcosky**
- Website: [https://wilcosky.com](https://wilcosky.com)
- Plugin URI: [https://wilcosky.com/peace-protocol](https://wilcosky.com/peace-protocol)

## 🙏 Acknowledgments

- WordPress community for the amazing platform
- Contributors and translators
- All the peaceful sites that make the web a better place

## 📞 Support

For support, feature requests, or bug reports:

1. Check the [WordPress.org plugin page](https://wordpress.org/plugins/peace-protocol/)
2. Visit the [GitHub repository](https://github.com/your-username/peace-protocol)
3. Contact the author at [https://wilcosky.com](https://wilcosky.com)

---

**Peace be with you! ✌️**

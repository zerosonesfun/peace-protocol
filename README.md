# Peace Protocol

**A secure, decentralized protocol for WordPress administrators to connect their sites and build a network of trust through cryptographic handshakes.**

Peace Protocol enables WordPress site administrators to authenticate as their website and send cryptographically signed "peace" messages to other WordPress sites running the same protocol. This creates a decentralized network where admins can establish trust relationships, share peace, and enable cross-site interactions.

## 🔒 **Security-First Design**

### **Admin-Only Authentication**
- **WordPress Administrators Only**: This plugin is designed exclusively for WordPress site administrators
- **Site-Level Authentication**: Admins authenticate as their website, not as individual users
- **No User Registration**: No public user accounts or registration system
- **Cryptographic Tokens**: Each site uses cryptographically secure tokens for authentication

### **Federated User System**
- **Limited Permissions**: Federated users can only comment on posts, no admin access
- **Automatic Cleanup**: Federated users are removed when the plugin is uninstalled
- **Role-Based Security**: Federated users have the `federated_peer` role with minimal capabilities
- **No Dashboard Access**: Federated users cannot access WordPress admin areas

### **Token Security**
- **Cryptographically Secure**: Tokens are generated using WordPress's secure password generator
- **Token Rotation**: Support for multiple tokens with automatic rotation
- **Secure Storage**: Tokens are stored securely in WordPress options
- **Expiring Authorization Codes**: Authorization codes expire after 5 minutes

## 🌟 **Key Features**

### **Core Functionality**
- **Send Peace**: Send cryptographically signed peace messages to other WordPress sites
- **Peace Log Wall**: Display received peace messages using the `[peace_log_wall]` shortcode
- **Automatic Feed Subscription**: Automatically subscribe to peace feeds from sites you connect with
- **Token Management**: Generate, rotate, and manage authentication tokens
- **User Banning System**: Ban problematic users with reason tracking

### **Federated Login System**
- **Cross-Site Authentication**: Users from remote sites can comment as their site identity
- **Seamless Integration**: Works with existing WordPress comment systems
- **Secure Handshake**: Only sites completing the cryptographic handshake can create federated logins
- **Automatic User Creation**: Creates federated users automatically after successful handshake

### **Admin Interface**
- **Token Management**: Generate, view, and delete authentication tokens
- **Feed Management**: View and manage subscribed peace feeds
- **Peace Log**: View all received peace messages in the admin area
- **User Banning**: Ban users with reason tracking and management
- **Settings Configuration**: Configure button position and auto-insertion

### **Frontend Features**
- **Peace Button**: Floating peace hand button (✌️) that can be positioned anywhere
- **Auto-Insertion**: Automatically insert the peace button on your site
- **Shortcode Support**: Use `[peace_hand_button]` to manually place the button
- **Responsive Design**: Works on all devices and screen sizes
- **Dark Mode Support**: Automatically adapts to user's color scheme preference

### **Technical Features**
- **REST API**: Modern REST API endpoints for all functionality
- **AJAX Fallback**: AJAX endpoints for sites with REST API disabled
- **CORS Support**: Proper CORS headers for cross-site communication
- **Translation Ready**: Full internationalization support with multiple languages
- **Custom Post Types**: Uses custom post types for peace logs

## 🚀 **How It Works**

### **For WordPress Administrators**

1. **Install & Activate**: Install the plugin and activate it on your WordPress site
2. **Generate Tokens**: Go to Settings > Peace Protocol and generate authentication tokens
3. **Send Peace**: Use the peace button to send cryptographically signed peace to other sites
4. **Build Network**: Connect with other WordPress sites and build a network of trust

### **Federated Login Process**

1. **User from Site A** visits Site B and wants to comment
2. **User clicks "Send Peace"** button on Site B
3. **Site B redirects** to Site A for authentication
4. **Site A validates** the user and generates an authorization code
5. **User is redirected** back to Site B with the authorization code
6. **Site B automatically** logs in the user as a federated user from Site A
7. **User can comment** on Site B as "Logged in as sitea.com"

### **Security Flow**

1. **Cryptographic Handshake**: Sites exchange cryptographically signed tokens
2. **Token Validation**: Each peace message is validated using secure tokens
3. **Federated User Creation**: Only after successful handshake are federated users created
4. **Limited Permissions**: Federated users have minimal permissions and no admin access
5. **Automatic Cleanup**: All federated data is removed on plugin uninstall

## 📋 **Requirements**

- **WordPress**: 6.0 or higher
- **PHP**: 7.4 or higher
- **Permissions**: Administrator access to WordPress site
- **Network**: Sites must be able to communicate via HTTP/HTTPS

## 🔧 **Installation**

1. **Upload** the plugin files to `/wp-content/plugins/peace-protocol/`
2. **Activate** the plugin through the 'Plugins' screen in WordPress
3. **Configure** by going to Settings > Peace Protocol
4. **Generate Tokens** for site authentication
5. **Customize** button position and auto-insertion settings

## 📖 **Usage**

### **Basic Setup**
```php
// The peace button is automatically inserted on your site
// Or use the shortcode: [peace_hand_button]
// Display peace log wall: [peace_log_wall]
```

### **Token Management**
- Generate at least 3 tokens for security
- Rotate tokens regularly
- Keep tokens secure and private
- Delete old tokens when no longer needed

### **Sending Peace**
1. Click the peace button (✌️) on your site
2. Enter the target site URL
3. Add an optional note (max 50 characters)
4. Click "Send Peace"

### **Managing Feeds**
- View subscribed feeds in Settings > Peace Protocol
- Unsubscribe from feeds you no longer want to follow
- Feeds are automatically added when you send peace to new sites

## 🛡️ **Security Considerations**

### **What This Plugin Does NOT Do**
- ❌ **No Public User Registration**: Only WordPress administrators can use this plugin
- ❌ **No Admin Access for Federated Users**: Federated users cannot access WordPress admin
- ❌ **No Database Access**: Federated users cannot access sensitive site data
- ❌ **No File System Access**: Federated users cannot upload or modify files
- ❌ **No Plugin/Theme Management**: Federated users cannot install or modify plugins/themes

### **What This Plugin DOES Do**
- ✅ **Site-to-Site Authentication**: WordPress admins authenticate as their website
- ✅ **Cryptographic Verification**: All peace messages are cryptographically signed
- ✅ **Limited Federated Access**: Federated users can only comment on posts
- ✅ **Automatic Cleanup**: All federated data is removed on uninstall
- ✅ **Secure Token Management**: Tokens are cryptographically secure and can be rotated

## 🌍 **Internationalization**

Peace Protocol is fully translation-ready and includes translations for:
- English (default)
- Spanish (es_ES)
- French (fr_FR)
- Japanese (ja)
- Chinese Simplified (zh_CN)

## 🔮 **Future Plans**

- **Post Liking**: Like posts across federated sites
- **Enhanced Commenting**: Rich comment interactions
- **Site Discovery**: Automatic discovery of Peace Protocol sites
- **Advanced Federation**: More sophisticated federated features

## 🤝 **Contributing**

We welcome contributions! Please see our contributing guidelines and code of conduct.

## 📄 **License**

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🆘 **Support**

For support, questions, or security concerns:
- **GitHub Issues**: [Create an issue](https://github.com/wilcosky/peace-protocol/issues)
- **Author Website**: [wilcosky.com](https://wilcosky.com)
- **Security**: For security issues, please contact the author directly

---

**Peace Protocol** - Building a decentralized network of trust, one WordPress site at a time. ✌️
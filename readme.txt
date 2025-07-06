=== Peace Protocol ===
Contributors: wilcosky
Tags: federation, peace, decentralized, security, cryptographic
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A secure, decentralized protocol for WordPress administrators to connect their sites and build a network of trust through cryptographic handshakes.

== Description ==

**Peace Protocol** enables WordPress site administrators to authenticate as their website and send cryptographically signed "peace" messages to other WordPress sites running the same protocol. This creates a decentralized network where admins can establish trust relationships, share peace, and enable cross-site interactions.

## 🔒 **Security-First Design**

### **Admin-Only Authentication**
- **WordPress Administrators Only**: This plugin is designed exclusively for WordPress site administrators
- **Site-Level Authentication**: Admins authenticate as their website, not as individual users
- **No Public Registration**: No public user registration system - only federated users created after secure handshakes
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
- **IndieAuth Support**: Alternative authentication using the IndieAuth standard with PKCE

### **Federated Login System**
- **Cross-Site Authentication**: Users from remote sites can comment as their site identity
- **Seamless Integration**: Works with existing WordPress comment systems
- **Secure Handshake**: Only sites completing the cryptographic handshake can create federated logins
- **Automatic User Creation**: Creates federated users automatically after successful handshake
- **Dual Authentication**: Support for both Peace Protocol tokens and IndieAuth standard

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
- **Choice Modal**: User-friendly modal to choose between Peace Protocol and IndieAuth authentication

### **Technical Features**
- **REST API**: Modern REST API endpoints for all functionality
- **AJAX Fallback**: AJAX endpoints for sites with REST API disabled
- **CORS Support**: Proper CORS headers for cross-site communication
- **Translation Ready**: Full internationalization support with multiple languages
- **Custom Post Types**: Uses custom post types for peace logs
- **IndieAuth Endpoints**: Full IndieAuth specification compliance with authorization and token endpoints
- **PKCE Support**: Proof Key for Code Exchange for enhanced security

## 🚀 **How It Works**

### **For WordPress Administrators**

1. **Install & Activate**: Install the plugin and activate it on your WordPress site
2. **Generate Tokens**: Go to Settings > Peace Protocol and generate authentication tokens
3. **Send Peace**: Use the peace button to send cryptographically signed peace to other sites
4. **Build Network**: Connect with other WordPress sites and build a network of trust

### **Federated Login Process**

#### **Peace Protocol Authentication**
1. **User from Site A** visits Site B and wants to comment
2. **User clicks "Send Peace"** button on Site B
3. **User chooses "Login with Peace Protocol"** from the choice modal
4. **Site B redirects** to Site A for authentication
5. **Site A validates** the user and generates an authorization code
6. **User is redirected** back to Site B with the authorization code
7. **Site B automatically** logs in the user as a federated user from Site A
8. **User can comment** on Site B as "Logged in as sitea.com"

#### **IndieAuth Authentication**
1. **User from Site A** visits Site B and wants to comment
2. **User clicks "Send Peace"** button on Site B
3. **User chooses "Login with IndieAuth"** from the choice modal
4. **Site B discovers** IndieAuth endpoints on Site A
5. **Site B redirects** to Site A's IndieAuth authorization endpoint
6. **Site A validates** the user and generates an authorization code
7. **User is redirected** back to Site B with the authorization code
8. **Site B exchanges** the code for an access token using PKCE
9. **Site B automatically** logs in the user as a federated user from Site A
10. **User can comment** on Site B as "Logged in as sitea.com"

### **Security Flow**

1. **Cryptographic Handshake**: Sites exchange cryptographically signed tokens
2. **Token Validation**: Each peace message is validated using secure tokens
3. **Federated User Creation**: Only after successful handshake are federated users created
4. **Limited Permissions**: Federated users have minimal permissions and no admin access
5. **Automatic Cleanup**: All federated data is removed on plugin uninstall

## 🛡️ **Security Considerations**

### **What This Plugin Does NOT Do**
- ❌ **No Public User Registration**: Only WordPress administrators can use this plugin (federated users are created automatically after secure handshakes)
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

== Installation ==

1. **Upload** the plugin files to `/wp-content/plugins/peace-protocol/`
2. **Activate** the plugin through the 'Plugins' screen in WordPress
3. **Configure** by going to Settings > Peace Protocol
4. **Generate Tokens** for site authentication
5. **Customize** button position and auto-insertion settings

== Frequently Asked Questions ==

= What is Peace Protocol? =
Peace Protocol is a secure, decentralized protocol that allows WordPress site administrators to authenticate as their website and send cryptographically signed "peace" messages to other WordPress sites. It creates a network of trust between WordPress sites.

= Who can use this plugin? =
This plugin is designed exclusively for WordPress site administrators. Only administrators can generate tokens and send peace messages. Regular users cannot use the core functionality.

= Is this secure? =
Yes, Peace Protocol is built with security-first design:
- Only WordPress administrators can use the core functionality
- All authentication uses cryptographically secure tokens
- Federated users have minimal permissions and cannot access admin areas
- All federated data is automatically cleaned up on plugin uninstall
- Authorization codes expire after 5 minutes

= What are federated users? =
Federated users are special WordPress users created when someone from another site completes a peace handshake. They can only comment on posts and cannot access the WordPress admin area. They are automatically removed when the plugin is uninstalled.

= What is a "token" and why do I need multiple? =
Tokens are cryptographically secure strings used to authenticate your site when sending peace to others. Generating multiple tokens allows you to rotate them for better security, and to revoke tokens if needed without losing access.

= How do I send peace to another site? =
Use the peace hand button (✌️) on your site or the `[peace_hand_button]` shortcode. Enter the target site's URL and an optional note, then send peace. The other site must also have the Peace Protocol plugin installed and activated.

= What happens when I send peace? =
Your site is subscribed to the other site's feed, allowing you to follow their updates. The handshake is logged and can be viewed in your admin area. If the other site has the plugin, they'll receive your peace message.

= Can I unsubscribe from a feed? =
Yes, you can manage your subscribed feeds from the Peace Protocol settings page in your WordPress admin.

= What shortcodes are available? =
- `[peace_hand_button]` - Display the peace button
- `[peace_log_wall]` - Display received peace messages

= Is this plugin translation-ready? =
Yes! All user-facing text is translatable, and translation files for several languages are included (Spanish, French, Japanese, Chinese Simplified).

= What features are planned for the future? =
Future updates may include post liking, enhanced commenting, site discovery, and other social features to further connect the WordPress ecosystem.

= Can I customize the peace button position? =
Yes, you can configure the button position (top-right, top-left, bottom-right, bottom-left) in the Peace Protocol settings, or disable auto-insertion and use the shortcode instead.

= What if I want to ban a user? =
The plugin includes a comprehensive user banning system. You can ban users with reason tracking, and banned users cannot send peace or use any Peace Protocol features.

== Screenshots ==

1. **Peace Protocol Settings Page**: Manage tokens, feeds, and configuration
2. **Send Peace Modal**: Send cryptographically signed peace to other sites
3. **Peace Log Wall**: Display received peace messages using shortcode
4. **Token Management**: Generate, rotate, and manage authentication tokens
5. **Feed Management**: View and manage subscribed peace feeds
6. **User Banning**: Ban users with reason tracking and management

== Changelog ==

= 1.2.0 =
* **Major Feature**: Added IndieAuth support as an alternative authentication method
* **PKCE Security**: Implemented Proof Key for Code Exchange for enhanced security
* **Dual Authentication**: Users can now choose between Peace Protocol tokens and IndieAuth
* **Choice Modal**: New user-friendly modal to select authentication method
* **IndieAuth Endpoints**: Full IndieAuth specification compliance with authorization and token endpoints
* **Enhanced UX**: Improved modal design with dark mode support and better styling
* **Discovery System**: Automatic IndieAuth endpoint discovery with fallback mechanisms
* **Cross-Site Compatibility**: Seamless integration with existing IndieAuth implementations
* **Security**: Maintained all existing security features while adding IndieAuth support

= 1.1.3 =
* **Code Quality**: Fixed PHP syntax errors and improved code structure
* **PHPCS Compliance**: Added file-level PHPCS ignore comments for cross-site REST API endpoints
* **Security**: Maintained token-based authentication security model
* **Bug Fixes**: Resolved brace matching issues in template_redirect function

= 1.1.2 =
* **Code Cleanup**: Removed all error_log and console.log statements for production readiness
* **Improved Performance**: Cleaner codebase without debug logging overhead
* **Better User Experience**: No more debug output cluttering user interfaces

= 1.1.0 =
* **Major Security Enhancement**: Added comprehensive user banning system
* **Improved Token Management**: Fixed token deletion issues with special characters
* **Enhanced Federated Login**: Better error handling and user experience
* **Code Cleanup**: Removed duplicate JavaScript functions
* **Better Documentation**: Updated security documentation and user guides

= 1.0.1 =
* **Initial Public Release**: Core Peace Protocol functionality
* **Federated Login System**: Cross-site authentication for commenting
* **Token Management**: Secure token generation and rotation
* **Feed Subscription**: Automatic feed management
* **Translation Support**: Multiple language support included
* **REST API**: Modern API endpoints with AJAX fallback
* **User Banning**: Basic user management system

== Upgrade Notice ==

= 1.1.0 =
Major security enhancement with comprehensive user banning system and improved token management. All existing functionality remains the same, with better security and user experience.

= 1.0.1 =
First public release of Peace Protocol. Connect your WordPress site to others and start building a decentralized network of trust with secure, cryptographic handshakes. 
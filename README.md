# Peace Protocol

A decentralized way for WordPress admins to share peace, respect, and follow each other with cryptographic handshakes.

## Features

- **Peace Handshakes**: Send peace messages to other WordPress sites
- **Token Management**: Secure token-based authentication with rotation
- **Federated Login**: Cross-site user authentication for seamless commenting
- **Feed Subscription**: Automatically subscribe to peace feeds from other sites
- **Admin Interface**: Easy token management and feed monitoring

## Federated Login Feature

### Overview

The Peace Protocol includes a powerful federated login system that allows users from remote sites to comment on your WordPress site as their own site identity, after completing a secure handshake.

### How It Works

1. **Federated Handshake**: When a user from a remote site completes the Peace Protocol handshake, your site receives a verification code and the remote site's domain.

2. **Federated User Creation & Login**: The plugin creates (or reuses) a special "federated peer" WordPress user for that remote site (e.g., `federated_example_com` for `example.com`). This user cannot access the dashboard or admin, only the front end.

3. **Automatic Sign-in**: The plugin logs in the remote user as the federated user, so they are "signed in" for the session.

4. **Comment Attribution**: When the federated user leaves a comment, it is attributed to the remote site (e.g., "Logged in as example.com"), enabling seamless cross-site conversation.

5. **Cleanup**: All federated users are removed if the plugin is uninstalled.

### Security Features

- **Role-Based Access**: Federated users have the `federated_peer` role with minimal permissions
- **Admin Blocking**: Federated users cannot access the WordPress admin area
- **Secure Handshake**: Only sites completing the handshake can create federated logins
- **Automatic Cleanup**: Federated users are removed on plugin uninstall

### Technical Implementation

- A new role `federated_peer` is registered with minimal capabilities
- After handshake validation (`peace_authorization_code` and `peace_federated_site` in the URL), the plugin creates/logs in the federated user
- Admin/backend access is blocked for federated users
- The process is fully automatic—no additional user action is required after handshake

### User Experience

1. User from SiteA visits SiteB and wants to comment
2. User clicks "Send Peace" button on SiteB
3. SiteB redirects to SiteA for authentication
4. SiteA validates the user and generates an authorization code
5. User is redirected back to SiteB with the authorization code
6. SiteB automatically logs in the user as a federated user from SiteA
7. User can now comment on SiteB as "Logged in as sitea.com"

This enables federated, cross-site commenting in a secure and native-feeling way for WordPress users!

## Installation

1. Upload the plugin files to `/wp-content/plugins/peace-protocol/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Settings > Peace Protocol to configure tokens and view feeds

## Usage

### Generating Tokens

1. Go to Settings > Peace Protocol
2. Click "Generate New Token" to create a new authentication token
3. Use these tokens to authenticate your site when sending peace to other sites

### Sending Peace

1. Use the `[peace_button]` shortcode or the automatically inserted button
2. Enter the target site URL
3. Write your peace message
4. Click send

### Managing Feeds

- View subscribed peace feeds in the admin interface
- Unsubscribe from feeds you no longer want to follow
- Feeds are automatically added when you send peace to new sites

## Security

- Tokens are cryptographically secure and randomly generated
- Authorization codes expire after 5 minutes
- Federated users have minimal permissions and cannot access admin areas
- All authentication is validated server-side

## Support

For support and questions, please visit the plugin's GitHub repository or contact the author.
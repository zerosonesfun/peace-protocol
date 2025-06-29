=== Peace Protocol ===
Contributors: wilcosky
Tags: federation, social, protocol, peace, decentralized
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A decentralized protocol for WordPress admins to send peace, respect, and follow each other with cryptographic handshakes. Connect your site to others and build a network of trust.

== Description ==
Peace Protocol is a new way for WordPress webmasters to connect, share peace, and build a decentralized network of trust and respect. With this plugin, WordPress admins can send each other "peace"—a cryptographically signed handshake, optionally with a note. When you send peace to another site, your site is automatically subscribed to that site's feed, allowing you to follow their updates and activity.

**Key Features:**
- Send peace to other WordPress sites, optionally including a short note.
- Each peace is a cryptographically signed handshake, ensuring authenticity.
- When you send peace, your site is subscribed to the other site's feed.
- View and manage your tokens and feeds from the admin settings page.
- Fully translation-ready and available in multiple languages.
- Future plans include support for post liking, commenting, and more—aiming to connect the 40%+ of the web that uses WordPress.

**How it works:**
1. Install and activate the plugin on your WordPress site.
2. Go to **Settings > Peace Protocol** in your WordPress admin.
3. Generate at least 3 tokens for security (tokens are used to authenticate your site when sending peace; keep them secret and rotate them regularly).
4. Use the provided button or shortcode to send peace to another WordPress site running the protocol.
5. When you send peace, your site is automatically subscribed to the other site's feed, and you can view updates from all sites you follow.

**Why Peace Protocol?**
- Decentralized: No central server or authority—connections are peer-to-peer between WordPress sites.
- Secure: Uses cryptographic tokens for authentication.
- Extensible: Designed to support more social features in the future.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/peace-protocol` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > Peace Protocol** to configure your tokens and view your feeds.
4. Generate at least 3 tokens for security. Tokens are used to authenticate your site when sending peace. Keep them secret and rotate them regularly.
5. Use the automatically inserted peace hand button on your site, or add the `[peace_log_wall]` shortcode to display the peace log wall.

== Frequently Asked Questions ==

= What is a "token" and why do I need more than one? =
Tokens are cryptographically secure strings used to authenticate your site when sending peace to others. Generating multiple tokens allows you to rotate them for better security, and to revoke tokens if needed without losing access.

= How do I send peace to another site? =
Use the peace hand button on your site or the provided shortcode. Enter the target site's URL and an optional note, then send peace. The other site must also have the Peace Protocol plugin installed and activated.

= What happens when I send peace? =
Your site is subscribed to the other site's feed, allowing you to follow their updates. The handshake is logged and can be viewed in your admin area.

= Can I unsubscribe from a feed? =
Yes, you can manage your subscribed feeds from the Peace Protocol settings page.

= Is this plugin translation-ready? =
Yes! All user-facing text is translatable, and translation files for several languages are included.

= What features are planned for the future? =
Future updates may include post liking, commenting, and other social features to further connect the WordPress ecosystem.

== Screenshots ==
1. Peace Protocol settings page: manage tokens and feeds.
2. Send peace to another site with an optional note.
3. View your peace log wall and see connections.

== Changelog ==
= 1.0.1 =
* Initial public release.
* Send and receive peace handshakes between WordPress sites.
* Token management and feed subscriptions.
* Translation-ready with multiple languages included.

== Upgrade Notice ==
= 1.0.1 =
First public release of Peace Protocol. Connect your WordPress site to others and start building a decentralized network of trust. 
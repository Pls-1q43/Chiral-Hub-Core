=== Chiral Hub Core ===
Contributors: pls1q43
Donate link: https://1q43.blog/donate/
Tags: hub, network, content aggregation, jetpack, related posts, cross-site, content discovery, api
Requires at least: 5.2
Tested up to: 6.6
Stable tag: 1.0.1
Requires PHP: 7.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Transform your WordPress site into a centralized content hub for intelligent cross-site content aggregation and discovery.

== Description ==

Chiral Hub Core transforms your WordPress site into a powerful centralized hub that manages connected WordPress sites (Chiral Nodes), aggregates content data, and provides intelligent related content recommendations through Jetpack integration with WordPress.com.

This plugin serves as the central brain of the Chiral Network ecosystem, working seamlessly with Chiral Connector plugins installed on individual WordPress sites to create a distributed content discovery network.

### Key Features

* **Centralized Content Management** - Receive, store, and manage article metadata from multiple connected WordPress sites
* **Custom Post Type Integration** - Dedicated `chiral_data` post type optimized for content aggregation and Jetpack synchronization
* **Jetpack-Powered Intelligence** - Leverage WordPress.com's advanced algorithms for superior related content recommendations
* **Secure API Endpoints** - Robust REST API for secure communication with Chiral Connector plugins
* **User Role Management** - Specialized `chiral_porter` role system for secure node authentication
* **Flexible Content Policies** - Administrative control over new node registration and content approval workflows
* **Real-time Synchronization** - Instant content updates across the entire network
* **Performance Optimized** - Built-in caching and optimized database queries for high-performance operations

### How It Works

1. **Hub Setup** - Install Chiral Hub Core and connect it with Jetpack
2. **Node Registration** - Create porter users for each site wanting to join the network
3. **Content Aggregation** - Automatically receive and store content metadata from connected nodes
4. **Jetpack Sync** - Content data is synchronized to WordPress.com for intelligent indexing
5. **Related Content API** - Provide related content recommendations to connected nodes via REST API
6. **Network Management** - Administrative dashboard for managing the entire content network

### Technical Architecture

The plugin creates a sophisticated content aggregation system that:

* **Manages Chiral Data CPT** - Stores aggregated content with custom fields for source tracking
* **Integrates with Jetpack** - Ensures content is properly indexed by WordPress.com algorithms
* **Provides REST API** - Secure endpoints for content CRUD operations and related content queries
* **Enforces Security** - Role-based access control and application password authentication
* **Optimizes Performance** - Excludes aggregated content from public search results

### Use Cases

* **Multi-site Network Operators** - Centralize content discovery across multiple WordPress installations
* **Content Publisher Networks** - Increase content exposure and reader engagement across related sites
* **Vertical Content Platforms** - Create topic-focused content discovery networks
* **Digital Magazine Groups** - Cross-promote articles between related publications
* **Blog Collectives** - Enable content discovery between member blogs

### Requirements

* **Jetpack Plugin** - Required for content synchronization with WordPress.com
* **WordPress.com Connection** - Jetpack must be connected to WordPress.com
* **Chiral Connector** - Partner plugin for node sites (available separately)

== Installation ==

### Automatic Installation

1. Navigate to "Plugins" > "Add New" in your WordPress admin panel
2. Search for "Chiral Hub Core"
3. Click "Install Now" and then "Activate"
4. Install and activate Jetpack plugin if not already installed
5. Connect Jetpack to WordPress.com

### Manual Installation

1. Download the plugin zip file
2. Go to "Plugins" > "Add New" > "Upload Plugin"
3. Select the downloaded zip file and click "Install Now"
4. Click "Activate Plugin" after installation completes
5. Ensure Jetpack is installed and connected to WordPress.com

### Initial Configuration

1. **Install Jetpack** (if not already installed)
   - Install and activate the Jetpack plugin
   - Connect your site to WordPress.com
   - Ensure Related Posts module is active

2. **Configure Hub Settings**
   - Go to "Chiral Hub" in your admin menu
   - Set your network name/description
   - Configure new porter registration policy
   - Set default related posts count

3. **Create Porter Users**
   - Create new users with `chiral_porter` role
   - Generate application passwords for each porter user
   - Provide credentials to node site administrators

4. **Test API Endpoints**
   - Use the ping endpoint to verify API functionality
   - Confirm Jetpack synchronization is working

== Frequently Asked Questions ==

= What is the difference between Chiral Hub Core and Chiral Connector? =

Chiral Hub Core is the centralized hub that aggregates content from multiple sites, while Chiral Connector is installed on individual WordPress sites to sync their content to the hub and display related posts.

= Do I need Jetpack? =

Yes, Jetpack is absolutely required. The hub relies on Jetpack to synchronize content to WordPress.com, which provides the intelligent algorithms for related content recommendations.

= How many sites can connect to one hub? =

There's no hard limit, but performance depends on your server resources and Jetpack plan. Most standard hosting can handle dozens of connected sites comfortably.

= What data is stored in the hub? =

The hub stores content metadata including titles, excerpts, featured images, and source URLs. Full content is not stored unless specifically configured.

= How secure is the connection between hub and nodes? =

Connections use WordPress Application Passwords for authentication and all data transfer occurs over secure REST API endpoints with role-based access control.

= Can I customize the porter user permissions? =

The plugin creates optimized permissions for porter users. Customization is possible but may affect functionality. Porter users can only manage their own chiral_data posts.

= What happens if a node site goes offline? =

The hub retains all synchronized content. If a node goes offline permanently, administrators can manually clean up associated data through the admin interface.

= Does this plugin affect my site's SEO? =

No negative impact. The chiral_data post type is excluded from public searches and the plugin is designed to be invisible to search engines while enhancing user experience.

== Screenshots ==

1. Hub administration dashboard - Network overview and configuration
2. Porter user management - Create and manage node site connections
3. Chiral data post type - Aggregated content from connected sites
4. API endpoint status - Monitor connection health and activity
5. Jetpack integration - Related posts powered by WordPress.com

== Changelog ==


= 1.0.1 =
Important SEO enhancement: This update adds robots meta tags to prevent search engines from indexing aggregated content pages, solving duplicate content issues while preserving all existing functionality. Recommended for all users.

= 1.0.0 =
Welcome to Chiral Hub Core! This inaugural release provides complete centralized content hub functionality for the Chiral Network ecosystem.

== Technical Specifications ==

### API Endpoints

* `/wp/v2/chiral_data` - CRUD operations for aggregated content
* `/wp-json/chiral-hub/v1/related-data` - Related content queries
* `/wp-json/chiral-hub/v1/ping` - Connection health check

### Custom Post Type

* **Post Type**: `chiral_data`
* **Supports**: title, editor, excerpt, custom-fields, jetpack-related-posts
* **Meta Fields**: node_id, source_url, source_post_id, source_title, source_content, source_excerpt, featured_image_url, network_name

### User Roles

* **chiral_porter**: Specialized role for node site authentication with restricted dashboard access

### Security Features

* Application Password authentication
* Role-based access control
* API endpoint permission validation
* Dashboard access restrictions for porter users

== Developer Information ==

### Hooks and Filters

The plugin provides numerous WordPress hooks for customization:

* `chiral_hub_before_data_sync` - Before content synchronization
* `chiral_hub_after_data_sync` - After content synchronization
* `chiral_hub_related_posts_query` - Customize related posts queries
* `chiral_hub_api_response` - Modify API responses

### Constants

* `CHIRAL_HUB_CORE_VERSION` - Current plugin version
* `CHIRAL_HUB_CORE_PLUGIN_DIR` - Plugin directory path
* `CHIRAL_HUB_CORE_PLUGIN_URL` - Plugin URL

### Requirements for Node Sites

Node sites require the Chiral Connector plugin and must use porter user credentials for authentication.

== Support and Contributing ==

### Support Channels

* **Plugin Homepage**: https://ckc.akashio.com
* **Developer Blog**: https://1q43.blog
* **Documentation**: Available in plugin installation

### Contributing

This plugin is part of the open-source Chiral Network project. Contributions welcome through official channels.

### Privacy and Data Handling

* Only content metadata is processed and stored
* No personal user data collection
* Full compliance with WordPress.com privacy policies
* Node site data can be removed upon request

== License ==

This plugin is licensed under GPL v3 or later. Full license details available at: http://www.gnu.org/licenses/gpl-3.0.txt 
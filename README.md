=== Keeper-S3 ===
Contributors: codecraftsman
Tags: s3, storage, cloud, attachments, media
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0
Author: codecraftsman
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A brief description of Keeper-S3 plugin, for managing media attachments in S3 cloud storage.

== Description ==

Keeper-S3 is a WordPress plugin that helps you manage your media attachments in Amazon S3 cloud storage. It allows you to offload your media files to S3, reducing storage load on your server and improving website performance.

Key features include:

*   Offload new and existing media uploads to S3
*   Manage media storage location (Local, S3, or both)
*   Support for single and bulk attachment storage location updates
*   Support for single and bulk media files migration to and from S3
*   Integration with WordPress media library and settings pages

== Installation ==

### Method 1: Install via WordPress Admin (Recommended)

1. Download the `keeper-s3.zip` plugin package
2. Log in to your WordPress admin dashboard
3. Go to "Plugins" > "Install Plugins"
4. Click the "Upload Plugin" button
5. Select the downloaded `keeper-s3.zip` file and upload
6. Click "Activate Plugin" after upload completion

### Method 2: Manual Installation

1. Download and extract the `keeper-s3.zip` plugin package
2. Upload the extracted `keeper-s3` folder to the `/wp-content/plugins/` directory
3. In WordPress admin, go to "Plugins" > "Installed Plugins"
4. Find the "Keeper-S3" plugin and click "Activate"

### Post-Installation Configuration

1. After activating the plugin, you'll see "Settings" > "Keeper-S3" menu in WordPress admin
2. Click to enter the settings page and configure the following information:
   - AWS Access Key ID
   - AWS Secret Access Key
   - S3 Bucket name
   - S3 Region
   - Other optional settings

### System Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Valid AWS account and S3 bucket
- PHP environment with cURL extension support

== Frequently Asked Questions ==

= How do I connect Keeper-S3 to my Amazon S3 bucket? =
You can connect Keeper-S3 to your Amazon S3 bucket by configuring the correct access key, secret key, and bucket name in the plugin settings.

= What are the available storage location options? =
You can choose between "Local", "S3", or "Both" for each media attachment. "Local" stores the file on your server, "S3" stores it in your S3 bucket, and "Both" stores the file in both locations.

= Can I migrate existing media to S3? =
Yes, Keeper-S3 includes features that help you migrate your existing media library to your S3 bucket.

== Screenshots ==

1. The screenshot of the Keeper-S3 settings page.
2. The screenshot of Keeper-S3 in action within the media library.

== Changelog ==

= 1.0.0 =
* Initial release of Keeper-S3.
* Implemented basic S3 connectivity and media management.


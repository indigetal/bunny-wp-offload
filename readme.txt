=== Tutor LMS BunnyNet Integration ===
Contributors: themeum, bmeyer
Donate link: https://www.themeum.com
Tags: tutor, lms, bunnynet, video, streaming, api
Requires at least: 5.3
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Seamlessly integrate Bunny.net’s bufferless high-speed video streaming platform with Tutor LMS, leveraging Bunny.net's robust API for enhanced video management and delivery.

== Description ==

Bunny.net offers bufferless high-speed video streaming from anywhere in the world via Bunny Stream, their premium streaming solution. With state-of-the-art CDN technology, Bunny Stream securely stores and delivers your videos to your students wherever they are.

Tutor LMS BunnyNet Integration takes video management to the next level by leveraging Bunny.net's API to streamline your course creation process. With features like automatic video uploads, user-specific collections, and real-time video management directly in the Tutor LMS dashboard, this plugin ensures that your videos are securely hosted and efficiently delivered.

= Features =

- Seamless integration with Bunny.net's API.
- Automatic creation and management of user-specific collections for instructors.
- Direct video uploads to Bunny.net from the WordPress dashboard.
- Enhanced security and video playback performance.
- Improved workflow for managing course videos.

= Prerequisites =

To use this plugin, you need:
1. Tutor LMS Free plugin (version 2.1.2 or higher).
2. Bunny.net account with Bunny Stream enabled.

= Get Started =

Step 1: After installing Tutor LMS and the Tutor LMS BunnyNet Integration plugin, navigate to "Tutor LMS > Settings > Course". From there scroll down to the preferred video source and find the BunnyNet option.

Step 2: Navigate to "Settings > Bunny.net Settings". Enter your Bunny.net Access Key and configure your Library.

Step 3: Use the API-powered video uploader available in the lesson and course editor pages. This allows you to upload videos directly to Bunny.net, automatically associating them with the correct user collection.

== Installation ==

= Minimum Requirements =

* PHP version 7.4 or greater.
* MySQL version 5.6 or greater (MySQL 5.7+ recommended).

= Automatic Installation =

To install Tutor LMS BunnyNet Integration, log in to your WordPress dashboard, navigate to the "Plugins" menu, and click "Add New." Search for "Tutor LMS BunnyNet Integration" and click "Install Now." Once installed, activate the plugin to get started.

= Manual Installation =

1. Download the plugin.
2. Upload the plugin files to the `/wp-content/plugins/tutor-bunny` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.

== Frequently Asked Questions ==

= Does this plugin have any dependencies? =
Yes, you must install the Tutor LMS plugin to use this integration.

= Will this plugin work with older versions of Tutor LMS? =
No, you need Tutor LMS version 2.1.2 or higher for this plugin to function correctly.

== Screenshots ==

1. BunnyNet settings page in Tutor LMS.
2. Video upload interface with API integration.
3. Video playback within a course.

== Changelog ==

= 2.0.0 - 28 January, 2025 =
* Complete overhaul to use Bunny.net's API for video uploads and management.
* Automatic collection creation for instructors.
* Streamlined video uploading and management in the WordPress dashboard.
* Refactored and optimized code for multisite compatibility.
* Added improved error handling and admin feedback.

= 1.0.0 - 15 November, 2022 =
* Initial release with iframe-based video URL support.

== Upgrade Notice ==

= 2.0.0 =
This version introduces major changes, including API integration with Bunny.net. Users upgrading from version 1.0.0 should reconfigure their Bunny.net Access Key and Library ID in the settings page.

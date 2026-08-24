=== Bug Animation ===
Contributors: abidkp11
Tags: animation, bugs, fly, screen effects, visual effects
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bug Animation adds animated flies and spiders that buzz across your WordPress site for a playful visual effect.

== Description ==
🐝 Add a quirky visual effect to your WordPress site! Bug Animation displays animated flies and spiders buzzing across the screen. Great for prank pages, seasonal fun, or just adding a bit of unexpected motion.

== Features ==
- 🪰 Animated flies and spiders moving across the screen
- 🎃 Great for themed websites or playful designs
- ⚙️ Lightweight and easy to install
- 🖥️ Works on all modern browsers
- 🧩 No coding required
- 🕐 Scheduling by day, time window, or custom date range
- 📄 Display on specific post types or individual posts/pages
- ♿ Respects the prefers-reduced-motion accessibility setting

== Third-Party Libraries ==
This plugin includes a modified version of Bug.js by Graham McNicoll (https://github.com/Auz/Bug), originally licensed under the MIT License. See js/bug.js for full attribution.

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/bug-animation` directory, or install via the WordPress plugin repository.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings > Bug Animation** to configure animation behavior.

== Frequently Asked Questions ==

= The bugs don't appear on my site =
Make sure the plugin is enabled from the Settings > Bug Animation page. Also check the Display Conditions and Scheduling settings to ensure the current page and time qualify.

= Can I disable the animation for users who prefer reduced motion? =
Yes. The plugin automatically respects the operating system / browser `prefers-reduced-motion` setting. Users who have enabled this accessibility preference will not see any bugs.

= Is the plugin compatible with page builders? =
Yes. The plugin injects bugs directly into `document.body` and should work with any theme or page builder.

== Screenshots ==
1. Example of flies buzzing across the screen
2. Backend setting page

== Changelog ==
= 1.1.0 =
* Added spider bug type
* Added scheduling options (by day, time window, custom date range)
* Added display conditions (entire site, front page, specific post types, specific items)
* Updated settings page UI with toggle switch and range sliders
* Fixed undefined variable bug in edge detection logic
* Added prefers-reduced-motion accessibility support
* Security enhancements: nonce verification, input sanitization improvements

= 1.0.1 =
* Fixed settings page layout
* Corrected typos in labels and descriptions

= 1.0.0 =
* Initial release with fly animations
* Settings panel added

== Upgrade Notice ==
= 1.0.0 =
First release of Bug Animation plugin. Adds animated flies to your site.

== License ==
This plugin is licensed under the GPLv2 or later. You are free to use, modify, and distribute it under the same license.

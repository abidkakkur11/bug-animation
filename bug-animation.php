<?php
/*
Plugin Name: Bug Animation
Plugin URI: https://github.com/abidkakkur11/Bug-Animation
Description: Displays animated flies buzzing across the screen for a fun visual effect.
Version: 1.0.0
Author: abidkp11
Author URI: https://profiles.wordpress.org/abidkp11/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: bug-animation
*/

// Enqueue necessary scripts and styles
function bug_animation_enqueue_scripts() {
    // Only enqueue scripts if the feature is enabled AND we're on a singular post/page (includes custom post types)
    // This prevents loading the script in archives, the homepage, or other non-singular contexts.
    if (get_option('bug_animation_enabled') && is_singular()) {
        // Use the file modification time as the script version so browsers bust cache when the file changes.
        $script_path = plugin_dir_path(__FILE__) . 'js/bug-min.js';
        $script_url  = plugin_dir_url(__FILE__) . 'js/bug-min.js';
        // Fallback to the plugin header version if the file doesn't exist for some reason.
        $fallback_version = '1.0';
        $script_version = (file_exists($script_path) ? filemtime($script_path) : $fallback_version);

        wp_enqueue_script('bug-min-js', $script_url, array('jquery'), $script_version, true);

        // Pass the plugin directory URL to the JavaScript file
        wp_localize_script('bug-min-js', 'siteData', array(
            'pluginUrl' => plugin_dir_url(__FILE__) // This will pass the plugin URL to JavaScript
        ));

        // Get user-defined options from the settings
        $minBugs = get_option('bug_min_bugs', 10);
        $maxBugs = get_option('bug_max_bugs', 30);
        $mouseOverAction = get_option('bug_mouse_over', 'die');

        // Inline script to initialize the BugController with settings
        wp_add_inline_script('bug-min-js', "
            new BugController({
                minBugs: $minBugs,
                maxBugs: $maxBugs,
                mouseOver: '$mouseOverAction'
            });
        ");
    }
}
add_action('wp_enqueue_scripts', 'bug_animation_enqueue_scripts');

// Add a settings page to the admin menu
function bug_animation_add_admin_menu() {
    add_options_page(
        'Bug Animation Settings',
        'Bug Animation',
        'manage_options',
        'bug-animation',
        'bug_animation_options_page'
    );
}
add_action('admin_menu', 'bug_animation_add_admin_menu');

// Register plugin settings
function bug_animation_settings_init() {
    // Register settings with sanitization callbacks to ensure stored values are safe.
    register_setting(
        'bug_animation_options',
        'bug_animation_enabled',
        array( 'sanitize_callback' => 'bug_animation_sanitize_enabled' )
    );

    register_setting(
        'bug_animation_options',
        'bug_min_bugs',
        array( 'sanitize_callback' => 'bug_animation_sanitize_positive_int' )
    );

    register_setting(
        'bug_animation_options',
        'bug_max_bugs',
        array( 'sanitize_callback' => 'bug_animation_sanitize_positive_int' )
    );

    register_setting(
        'bug_animation_options',
        'bug_mouse_over',
        array( 'sanitize_callback' => 'bug_animation_sanitize_mouse_over' )
    );

    add_settings_section(
        'bug_animation_section',
        'Bug Animation Settings',
        '__return_false',
        'bug_animation'
    );

    add_settings_field(
        'bug_animation_enabled',
        'Enable Bug Animation',
        'bug_animation_enabled_render',
        'bug_animation',
        'bug_animation_section'
    );

    add_settings_field(
        'bug_min_bugs',
        'Minimum Bugs',
        'bug_min_bugs_render',
        'bug_animation',
        'bug_animation_section'
    );

    add_settings_field(
        'bug_max_bugs',
        'Maximum Bugs',
        'bug_max_bugs_render',
        'bug_animation',
        'bug_animation_section'
    );

    add_settings_field(
        'bug_mouse_over',
        'Mouse Over Action',
        'bug_mouse_over_render',
        'bug_animation',
        'bug_animation_section'
    );
}
add_action('admin_init', 'bug_animation_settings_init');

/**
 * Sanitization callbacks for plugin settings
 */
function bug_animation_sanitize_enabled($value) {
    // Expect a truthy value (checkbox). Store as 1 or 0.
    return ($value) ? 1 : 0;
}

function bug_animation_sanitize_positive_int($value) {
    $val = intval($value);
    if ($val < 0) {
        $val = 0;
    }
    return $val;
}

function bug_animation_sanitize_mouse_over($value) {
    $allowed = array('random', 'fly', 'flyoff', 'nothing', 'die');
    $value = sanitize_text_field($value);
    if (in_array($value, $allowed, true)) {
        return $value;
    }
    // default
    return 'random';
}

// Render the toggle option for enabling the bug animation
function bug_animation_enabled_render() {
    $enabled = get_option('bug_animation_enabled', false);
    ?>
    <input type="checkbox" name="bug_animation_enabled" value="1" <?php checked(1, $enabled, true); ?> />
    <?php
}

// Render the input field for minimum bugs
function bug_min_bugs_render() {
    $minBugs = get_option('bug_min_bugs', 10);
    ?>
    <input type="number" name="bug_min_bugs" value="<?php echo esc_attr($minBugs); ?>" /><span> Minumum number of bugs to show. (default: 10)</span>
    <?php
}

// Render the input field for maximum bugs
function bug_max_bugs_render() {
    $maxBugs = get_option('bug_max_bugs', 20);
    ?>
    <input type="number" name="bug_max_bugs" value="<?php echo esc_attr($maxBugs); ?>" /><span> Maximum number of bugs to show. (default: 20)</span>
    <?php
}

// Render the input field for mouse over action
function bug_mouse_over_render() {
    $mouseOver = get_option('bug_mouse_over', 'random');
    // map of value => human-friendly label
    $allowed = array(
        'random'  => 'Random (varied behavior)',
        'fly'     => 'Fly (bug moves away)',
        'flyoff'  => 'Fly Off (bug exits the screen)',
        'nothing' => 'Nothing (no reaction)',
        'die'     => 'Die (bug falls)'
    );
    ?>
    <select name="bug_mouse_over">
        <?php foreach ($allowed as $value => $label) : ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($mouseOver, $value); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
    </select>
    <p class="description">When a user moves their mouse over a bug, choose how the bug should react. Use "Random" for varied behaviors.</p>
    <ul>
        <li><strong>Random</strong> — The plugin chooses an action per interaction.</li>
        <li><strong>Fly</strong> — The bug quickly moves away from the cursor.</li>
        <li><strong>Fly Off</strong> — The bug flies off the screen.</li>
        <li><strong>Nothing</strong> — No reaction on mouse over.</li>
        <li><strong>Die</strong> — The bug falls.</li>
    </ul>
    <?php
}

// Display the plugin's settings page
function bug_animation_options_page() {
    ?>
    <form action="options.php" method="post">
        <?php
        settings_fields('bug_animation_options');
        do_settings_sections('bug_animation');
        submit_button();
        ?>
    </form>
    <p>If you found this plugin is usefull please do <a href="https://www.paypal.com/paypalme/ABIDKP211">Support</a></p>
    <?php
}

// ...existing code...
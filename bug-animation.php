<?php
/*
Plugin Name: Bug Animation
Plugin URI: https://github.com/abidkakkur11/Bug-Animation
Description: Displays animated bugs (flies, spiders, bees, etc.) buzzing across the screen for a fun visual effect.
Version: 1.1.0
Author: abidkp11
Author URI: https://profiles.wordpress.org/abidkp11/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: bug-animation
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add settings link on plugin page.
 *
 * @param array $links Array of plugin action links.
 * @return array
 */
function buganimation_plugin_action_links($links) {
    $settings_link = '<a href="options-general.php?page=buganimation-settings">' . esc_html__('Settings', 'bug-animation') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'buganimation_plugin_action_links');

/**
 * Enqueue necessary scripts and styles.
 *
 * @return void
 */
function buganimation_enqueue_scripts() {
    // Only enqueue scripts if the feature is enabled AND we're on a singular post/page (includes custom post types)
    // This prevents loading the script in archives, the homepage, or other non-singular contexts.
    if (!get_option('buganimation_enabled', false)) {
        return;
    }

    $condition = get_option('buganimation_display_condition', 'all');
    if ($condition === 'front_page' && !is_front_page()) {
        return;
    }
    if ($condition === 'specific_post_types') {
        $selected_types = get_option('buganimation_selected_post_types', array());
        if (!is_array($selected_types)) $selected_types = array();
        if (!is_singular($selected_types)) {
            return;
        }
    }
    if ($condition === 'specific_items') {
        $selected_ids = get_option('buganimation_selected_posts', array());
        if (!is_array($selected_ids)) $selected_ids = array();
        if (!is_singular() || !in_array((string)get_the_ID(), $selected_ids, true)) {
            return;
        }
    }

    // Evaluate Scheduling
    $tz = wp_timezone();
    $current_time = new DateTime('now', $tz);
    
    $days = get_option('buganimation_schedule_days', 'always');
    if ($days === 'weekdays') {
        if ($current_time->format('N') > 5) {
            return;
        }
    } elseif ($days === 'weekends') {
        if ($current_time->format('N') < 6) {
            return;
        }
    } elseif ($days === 'date_range') {
        $start_date = get_option('buganimation_schedule_start_date', '');
        $end_date = get_option('buganimation_schedule_end_date', '');
        $current_date_str = $current_time->format('Y-m-d');
        if (!empty($start_date) && $current_date_str < $start_date) return;
        if (!empty($end_date) && $current_date_str > $end_date) return;
    }

    if ($days !== 'always') {
        $start_time = get_option('buganimation_schedule_start_time', '');
        $end_time = get_option('buganimation_schedule_end_time', '');
        $current_time_str = $current_time->format('H:i');
        
        if (!empty($start_time) && $current_time_str < $start_time) return;
        if (!empty($end_time) && $current_time_str > $end_time) return;
    }

    // Use the file modification time as the script version so browsers bust cache when the file changes.
        $script_path = plugin_dir_path(__FILE__) . 'js/bug-min.js';
        $script_url  = plugin_dir_url(__FILE__) . 'js/bug-min.js';
        // Fallback to the plugin header version if the file doesn't exist for some reason.
        $fallback_version = '1.0';
        $script_version = (file_exists($script_path) ? filemtime($script_path) : $fallback_version);

        wp_enqueue_script('buganimation-js', $script_url, array('jquery'), $script_version, true);

        // Pass the plugin directory URL to the JavaScript file
        wp_localize_script('buganimation-js', 'bugAnimationData', array(
            'pluginUrl' => plugin_dir_url(__FILE__) // This will pass the plugin URL to JavaScript
        ));

        // Get user-defined options from the settings
        $minBugs = get_option('buganimation_min_bugs', 10);
        $maxBugs = get_option('buganimation_max_bugs', 30);
        $mouseOverAction = get_option('buganimation_mouse_over', 'die');
        $bugTypes = get_option('buganimation_bug_types', array('fly'));

        // Inline script to initialize the BugController with settings
        wp_add_inline_script('buganimation-js', sprintf("
            new BugController({
                minBugs: %d,
                maxBugs: %d,
                mouseOver: %s,
                bugTypes: %s
            });
        ", 
            absint($minBugs),
            absint($maxBugs),
            wp_json_encode($mouseOverAction),
            wp_json_encode($bugTypes)
        ));
}
add_action('wp_enqueue_scripts', 'buganimation_enqueue_scripts');

/**
 * Add a settings page to the admin menu.
 *
 * @return void
 */
function buganimation_add_admin_menu() {
    add_options_page(
        esc_html__( 'Bug Animation Settings', 'bug-animation' ),
        esc_html__( 'Bug Animation', 'bug-animation' ),
        'manage_options',
        'buganimation-settings',
        'buganimation_options_page'
    );
}
add_action('admin_menu', 'buganimation_add_admin_menu');

/**
 * Register plugin settings.
 *
 * @return void
 */
function buganimation_settings_init() {
    // Register settings with sanitization callbacks to ensure stored values are safe.
    register_setting(
        'buganimation_options',
        'buganimation_enabled',
        array( 'sanitize_callback' => 'buganimation_sanitize_enabled' )
    );

    register_setting(
        'buganimation_options',
        'buganimation_min_bugs',
        array( 'sanitize_callback' => 'buganimation_sanitize_positive_int' )
    );

    register_setting(
        'buganimation_options',
        'buganimation_max_bugs',
        array( 'sanitize_callback' => 'buganimation_sanitize_positive_int' )
    );

    register_setting(
        'buganimation_options',
        'buganimation_mouse_over',
        array( 'sanitize_callback' => 'buganimation_sanitize_mouse_over' )
    );

    register_setting(
        'buganimation_options',
        'buganimation_bug_types',
        array( 'sanitize_callback' => 'buganimation_sanitize_bug_types' )
    );

    register_setting( 'buganimation_options', 'buganimation_display_condition', array( 'sanitize_callback' => 'buganimation_sanitize_display_condition' ) );
    register_setting( 'buganimation_options', 'buganimation_selected_post_types', array( 'sanitize_callback' => 'buganimation_sanitize_selected_post_types' ) );
    register_setting( 'buganimation_options', 'buganimation_selected_posts', array( 'sanitize_callback' => 'buganimation_sanitize_array_of_strings' ) );
    register_setting( 'buganimation_options', 'buganimation_schedule_days', array( 'sanitize_callback' => 'buganimation_sanitize_schedule_days' ) );
    register_setting( 'buganimation_options', 'buganimation_schedule_start_date', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'buganimation_options', 'buganimation_schedule_end_date', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'buganimation_options', 'buganimation_schedule_start_time', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    register_setting( 'buganimation_options', 'buganimation_schedule_end_time', array( 'sanitize_callback' => 'sanitize_text_field' ) );

    add_settings_section(
        'buganimation_section',
        esc_html__( 'General Options', 'bug-animation' ),
        '__return_false',
        'buganimation-settings'
    );

    add_settings_field(
        'buganimation_enabled',
        esc_html__( 'Enable Bug Animation', 'bug-animation' ),
        'buganimation_enabled_render',
        'buganimation-settings',
        'buganimation_section',
        array( 'label_for' => 'buganimation_enabled' )
    );

    add_settings_field(
        'buganimation_min_bugs',
        esc_html__( 'Minimum Bugs', 'bug-animation' ),
        'buganimation_min_bugs_render',
        'buganimation-settings',
        'buganimation_section',
        array( 'label_for' => 'min_bugs_slider' )
    );

    add_settings_field(
        'buganimation_max_bugs',
        esc_html__( 'Maximum Bugs', 'bug-animation' ),
        'buganimation_max_bugs_render',
        'buganimation-settings',
        'buganimation_section',
        array( 'label_for' => 'max_bugs_slider' )
    );

    add_settings_field(
        'buganimation_mouse_over',
        esc_html__( 'Mouse Over Action', 'bug-animation' ),
        'buganimation_mouse_over_render',
        'buganimation-settings',
        'buganimation_section',
        array( 'label_for' => 'buganimation_mouse_over' )
    );

    add_settings_section(
        'buganimation_types_section',
        esc_html__( 'Bug Types', 'bug-animation' ),
        '__return_false',
        'buganimation-settings'
    );

    add_settings_field(
        'buganimation_bug_types',
        esc_html__( 'Enabled Bug Types', 'bug-animation' ),
        'buganimation_bug_types_render',
        'buganimation-settings',
        'buganimation_types_section'
    );

    add_settings_section( 'buganimation_conditions_section', esc_html__( 'Display Conditions', 'bug-animation' ), '__return_false', 'buganimation-settings' );
    add_settings_field( 'buganimation_display_condition', esc_html__( 'Display On', 'bug-animation' ), 'buganimation_display_condition_render', 'buganimation-settings', 'buganimation_conditions_section', array( 'label_for' => 'buganimation_display_condition' ), array('label_for' => 'buganimation_display_condition') );
    add_settings_field( 'buganimation_selected_post_types', esc_html__( 'Selected Post Types', 'bug-animation' ), 'buganimation_selected_post_types_render', 'buganimation-settings', 'buganimation_conditions_section', array( 'label_for' => 'buganimation_selected_post_types' ), array('label_for' => 'buganimation_display_condition') );
    add_settings_field( 'buganimation_selected_posts', esc_html__( 'Selected Items', 'bug-animation' ), 'buganimation_selected_posts_render', 'buganimation-settings', 'buganimation_conditions_section', array( 'label_for' => 'buganimation_selected_posts' ), array('label_for' => 'buganimation_display_condition') );

    add_settings_section( 'buganimation_scheduling_section', esc_html__( 'Scheduling Options', 'bug-animation' ), '__return_false', 'buganimation-settings' );
    add_settings_field( 'buganimation_schedule_days', esc_html__( 'Active Days', 'bug-animation' ), 'buganimation_schedule_days_render', 'buganimation-settings', 'buganimation_scheduling_section', array( 'label_for' => 'buganimation_schedule_days' ) );
    add_settings_field( 'buganimation_schedule_start_date', esc_html__( 'Start Date', 'bug-animation' ), 'buganimation_schedule_start_date_render', 'buganimation-settings', 'buganimation_scheduling_section', array( 'label_for' => 'buganimation_schedule_start_date' ) );
    add_settings_field( 'buganimation_schedule_end_date', esc_html__( 'End Date', 'bug-animation' ), 'buganimation_schedule_end_date_render', 'buganimation-settings', 'buganimation_scheduling_section', array( 'label_for' => 'buganimation_schedule_end_date' ) );
    add_settings_field( 'buganimation_schedule_start_time', esc_html__( 'Start Time', 'bug-animation' ), 'buganimation_schedule_start_time_render', 'buganimation-settings', 'buganimation_scheduling_section', array( 'label_for' => 'buganimation_schedule_start_time' ) );
    add_settings_field( 'buganimation_schedule_end_time', esc_html__( 'End Time', 'bug-animation' ), 'buganimation_schedule_end_time_render', 'buganimation-settings', 'buganimation_scheduling_section', array( 'label_for' => 'buganimation_schedule_end_time' ) );
}
add_action('admin_init', 'buganimation_settings_init');

/**
 * Sanitization callback for enabled setting.
 *
 * @param mixed $value The setting value.
 * @return int 1 if enabled, 0 otherwise.
 */
function buganimation_sanitize_enabled($value) {
    // Expect a truthy value (checkbox). Store as 1 or 0.
    return ($value) ? 1 : 0;
}

/**
 * Sanitization callback for positive integer settings.
 *
 * @param mixed $value The setting value.
 * @return int The sanitized positive integer.
 */
function buganimation_sanitize_positive_int($value) {
    $val = intval($value);
    if ($val < 1) {
        $val = 1;
    }
    return $val;
}

/**
 * Sanitization callback for mouse over action setting.
 *
 * @param mixed $value The setting value.
 * @return string The sanitized action.
 */
function buganimation_sanitize_mouse_over($value) {
    $allowed = array('random', 'fly', 'flyoff', 'nothing', 'die');
    $value = sanitize_text_field($value);
    if (in_array($value, $allowed, true)) {
        return $value;
    }
    // default
    return 'random';
}

/**
 * Sanitization callback for bug types setting.
 *
 * @param mixed $value The setting value.
 * @return array The sanitized array of bugs.
 */
function buganimation_sanitize_bug_types($value) {
    $allowed = array('fly', 'spider');
    if (!is_array($value)) {
        return array('fly');
    }
    $sanitized = array();
    foreach ($value as $bug) {
        if (in_array($bug, $allowed, true)) {
            $sanitized[] = $bug;
        }
    }
    return !empty($sanitized) ? $sanitized : array('fly');
}

/**
 * Sanitization callback for display condition setting.
 *
 * @param mixed $value The setting value.
 * @return string The sanitized condition.
 */
function buganimation_sanitize_display_condition($value) {
    $allowed = array( 'all', 'front_page', 'specific_post_types', 'specific_items' );
    $value = sanitize_text_field( $value );
    if ( in_array( $value, $allowed, true ) ) {
        return $value;
    }
    return 'all';
}

/**
 * Sanitization callback for schedule days setting.
 *
 * @param mixed $value The setting value.
 * @return string The sanitized schedule day option.
 */
function buganimation_sanitize_schedule_days($value) {
    $allowed = array( 'always', 'weekdays', 'weekends', 'date_range' );
    $value = sanitize_text_field( $value );
    if ( in_array( $value, $allowed, true ) ) {
        return $value;
    }
    return 'always';
}

/**
 * Sanitization callback for the selected post types setting.
 * Enforces that at least one post type is chosen when the display condition requires it.
 *
 * @param mixed $value The submitted value.
 * @return array Sanitized array, or the previous stored value on validation failure.
 */
function buganimation_sanitize_selected_post_types( $value ) {
    $sanitized = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
    $sanitized = array_filter( $sanitized ); // Remove any empty entries.

    // If the user chose "Specific Post Types", at least one must be selected.
    $condition = isset( $_POST['buganimation_display_condition'] )
        ? sanitize_text_field( wp_unslash( $_POST['buganimation_display_condition'] ) )
        : '';

    if ( 'specific_post_types' === $condition && empty( $sanitized ) ) {
        add_settings_error(
            'buganimation_selected_post_types',
            'buganimation_empty_post_types',
            esc_html__( 'Please select at least one post type when "Specific Post Types" is chosen as the display condition.', 'bug-animation' ),
            'error'
        );
        // Return the existing stored value so nothing is wiped.
        return get_option( 'buganimation_selected_post_types', array() );
    }

    return array_values( $sanitized );
}

/**
 * Sanitization callback for arrays of strings/IDs.
 */
function buganimation_sanitize_array_of_strings($value) {
    if (!is_array($value)) {
        return array();
    }
    return array_map('sanitize_text_field', $value);
}

/**
 * Render the toggle option for enabling the bug animation.
 *
 * @return void
 */
function buganimation_enabled_render() {
    $enabled = get_option('buganimation_enabled', false);
    ?>
    <label class="bug-switch">
        <input type="checkbox" name="buganimation_enabled" id="buganimation_enabled" value="1" <?php checked(1, $enabled, true); ?> />
        <span class="bug-slider"></span>
    </label>
    <p class="description"><?php esc_html_e('Toggle to enable or disable the bug animation on the frontend.', 'bug-animation'); ?></p>
    <?php
}

/**
 * Render the input field for minimum bugs.
 *
 * @return void
 */
function buganimation_min_bugs_render() {
    $minBugs = get_option('buganimation_min_bugs', 10);
    ?>
    <input type="range" name="buganimation_min_bugs" id="min_bugs_slider" min="1" max="100" value="<?php echo esc_attr($minBugs); ?>" oninput="document.getElementById('min_bugs_val').textContent = this.value" style="vertical-align: middle;" />
    <span id="min_bugs_val" style="display:inline-block; width:30px; text-align:right; font-weight:bold;"><?php echo esc_html($minBugs); ?></span>
    <p class="description"><?php esc_html_e('Minimum number of bugs to show. (default: 10)', 'bug-animation'); ?></p>
    <?php
}

/**
 * Render the input field for maximum bugs.
 *
 * @return void
 */
function buganimation_max_bugs_render() {
    $maxBugs = get_option('buganimation_max_bugs', 20);
    ?>
    <input type="range" name="buganimation_max_bugs" id="max_bugs_slider" min="1" max="100" value="<?php echo esc_attr($maxBugs); ?>" oninput="document.getElementById('max_bugs_val').textContent = this.value" style="vertical-align: middle;" />
    <span id="max_bugs_val" style="display:inline-block; width:30px; text-align:right; font-weight:bold;"><?php echo esc_html($maxBugs); ?></span>
    <p class="description"><?php esc_html_e('Maximum number of bugs to show. (default: 20)', 'bug-animation'); ?></p>
    <?php
}

/**
 * Render the input field for mouse over action.
 *
 * @return void
 */
function buganimation_mouse_over_render() {
    $mouseOver = get_option('buganimation_mouse_over', 'random');
    // map of value => human-friendly label
    $allowed = array(
        'random'  => esc_html__('Random (varied behavior)', 'bug-animation'),
        'fly'     => esc_html__('Fly (bug moves away)', 'bug-animation'),
        'flyoff'  => esc_html__('Fly Off (bug exits the screen)', 'bug-animation'),
        'nothing' => esc_html__('Nothing (no reaction)', 'bug-animation'),
        'die'     => esc_html__('Die (bug falls)', 'bug-animation')
    );
    ?>
    <select name="buganimation_mouse_over" id="buganimation_mouse_over">
        <?php foreach ($allowed as $value => $label) : ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($mouseOver, $value); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
    </select>
    <p class="description"><?php esc_html_e('When a user moves their mouse over a bug, choose how the bug should react. Use "Random" for varied behaviors.', 'bug-animation'); ?></p>
    <ul>
        <li><strong><?php esc_html_e('Random', 'bug-animation'); ?></strong> &mdash; <?php esc_html_e('The plugin chooses an action per interaction.', 'bug-animation'); ?></li>
        <li><strong><?php esc_html_e('Fly', 'bug-animation'); ?></strong> &mdash; <?php esc_html_e('The bug quickly moves away from the cursor.', 'bug-animation'); ?></li>
        <li><strong><?php esc_html_e('Fly Off', 'bug-animation'); ?></strong> &mdash; <?php esc_html_e('The bug flies off the screen.', 'bug-animation'); ?></li>
        <li><strong><?php esc_html_e('Nothing', 'bug-animation'); ?></strong> &mdash; <?php esc_html_e('No reaction on mouse over.', 'bug-animation'); ?></li>
        <li><strong><?php esc_html_e('Die', 'bug-animation'); ?></strong> &mdash; <?php esc_html_e('The bug falls.', 'bug-animation'); ?></li>
    </ul>
    <?php
}

/**
 * Render the bug types layout.
 *
 * @return void
 */
function buganimation_bug_types_render() {
    $selected = get_option('buganimation_bug_types', array('fly'));
    $bugs = array(
        'fly' => esc_html__('Fly', 'bug-animation'),
        'spider' => esc_html__('Spider', 'bug-animation')
    );
    
    echo '<div style="display:flex; flex-wrap:wrap; gap:15px; margin-bottom: 10px;">';
    foreach ($bugs as $id => $label) {
        $img_src = plugin_dir_url(__FILE__) . 'images/' . $id . '-preview.png';
        if ($id === 'fly') {
            $img_src = plugin_dir_url(__FILE__) . 'images/fly-sprite.png'; 
        }
        ?>
        <label style="display:flex; flex-direction:column; align-items:center; cursor:pointer; background:#fff; border:1px solid #ccd0d4; padding:15px; border-radius:6px; min-width:80px; box-shadow:0 1px 1px rgba(0,0,0,.04);">
            <?php if ($id === 'fly') : ?>
               <div style="width:13px; height:14px; background:url('<?php echo esc_url($img_src); ?>') no-repeat; transform:scale(2.5); margin: 15px 0 25px 0;"></div>
            <?php else : ?>
               <img src="<?php echo esc_url($img_src); ?>" alt="<?php echo esc_attr($label); ?>" style="width:48px; height:48px; object-fit:contain; margin-bottom:10px;" />
            <?php endif; ?>
            <span style="font-weight: 600;">
                <input type="checkbox" name="buganimation_bug_types[]" value="<?php echo esc_attr($id); ?>" <?php checked( in_array( $id, $selected, true ), true ); ?> />
                <?php echo esc_html($label); ?>
            </span>
        </label>
        <?php
    }
    echo '</div>';
    echo '<p class="description">' . esc_html__('Select which bugs you want to see. If multiple are selected, they will spawn randomly.', 'bug-animation') . '</p>';
}

/**
 * Render the display condition setting.
 */
function buganimation_display_condition_render() {
    $condition = get_option('buganimation_display_condition', 'all');
    ?>
    <select name="buganimation_display_condition" id="buganimation_display_condition">
        <option value="all" <?php selected($condition, 'all'); ?>><?php esc_html_e('Entire Site', 'bug-animation'); ?></option>
        <option value="front_page" <?php selected($condition, 'front_page'); ?>><?php esc_html_e('Front Page Only', 'bug-animation'); ?></option>
        <option value="specific_post_types" <?php selected($condition, 'specific_post_types'); ?>><?php esc_html_e('Specific Post Types', 'bug-animation'); ?></option>
        <option value="specific_items" <?php selected($condition, 'specific_items'); ?>><?php esc_html_e('Specific Items (Search)', 'bug-animation'); ?></option>
    </select>
    <p class="description"><?php esc_html_e('Choose where the bugs should appear.', 'bug-animation'); ?></p>
    <?php
}

/**
 * Render the selected post types multi-select.
 */
function buganimation_selected_post_types_render() {
    $selected_types = get_option('buganimation_selected_post_types', array());
    if (!is_array($selected_types)) $selected_types = array();
    
    // Get all public post types
    $post_types = get_post_types(array('public' => true), 'objects');
    
    echo '<select name="buganimation_selected_post_types[]" id="buganimation_selected_post_types" class="buganimation-select2-basic" multiple="multiple" style="width: 350px;">';
    foreach ($post_types as $pt) {
        if ($pt->name === 'attachment') continue;
        echo '<option value="' . esc_attr( $pt->name ) . '" ' . selected( in_array( $pt->name, $selected_types, true ), true, false ) . '>' . esc_html( $pt->labels->name ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('Select which post types the bugs should appear on.', 'bug-animation') . '</p>';
}

/**
 * Render the selected posts Select2 field.
 */
function buganimation_selected_posts_render() {
    $posts = get_option('buganimation_selected_posts', array());
    if (!is_array($posts)) $posts = array();
    
    echo '<select name="buganimation_selected_posts[]" id="buganimation_selected_posts" class="buganimation-select2" multiple="multiple" style="width: 350px;">';
    // Pre-populate existing selections so they show up
    foreach ($posts as $post_id) {
        $title = get_the_title($post_id);
        if ($title) {
            echo '<option value="' . esc_attr($post_id) . '" selected="selected">' . esc_html($title) . '</option>';
        }
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('Search for specific posts, pages, or custom post types by title.', 'bug-animation') . '</p>';
}

/**
 * Render schedule days.
 */
function buganimation_schedule_days_render() {
    $days = get_option('buganimation_schedule_days', 'always');
    ?>
    <select name="buganimation_schedule_days" id="buganimation_schedule_days">
        <option value="always" <?php selected($days, 'always'); ?>><?php esc_html_e('Always Active', 'bug-animation'); ?></option>
        <option value="weekdays" <?php selected($days, 'weekdays'); ?>><?php esc_html_e('Weekdays Only (Mon-Fri)', 'bug-animation'); ?></option>
        <option value="weekends" <?php selected($days, 'weekends'); ?>><?php esc_html_e('Weekends Only (Sat-Sun)', 'bug-animation'); ?></option>
        <option value="date_range" <?php selected($days, 'date_range'); ?>><?php esc_html_e('Custom Date Range', 'bug-animation'); ?></option>
    </select>
    <p class="description"><?php esc_html_e('Choose which days the bugs are active.', 'bug-animation'); ?></p>
    <?php
}

/**
 * Render start date.
 */
function buganimation_schedule_start_date_render() {
    $date = get_option('buganimation_schedule_start_date', '');
    ?>
    <input type="date" name="buganimation_schedule_start_date" id="buganimation_schedule_start_date" value="<?php echo esc_attr($date); ?>" min="<?php echo esc_attr( wp_date('Y-m-d') ); ?>" />
    <p class="description"><?php esc_html_e('Start date (only applies if "Custom Date Range" is chosen above).', 'bug-animation'); ?></p>
    <?php
}

/**
 * Render end date.
 */
function buganimation_schedule_end_date_render() {
    $date = get_option('buganimation_schedule_end_date', '');
    ?>
    <input type="date" name="buganimation_schedule_end_date" id="buganimation_schedule_end_date" value="<?php echo esc_attr($date); ?>" min="<?php echo esc_attr( wp_date('Y-m-d') ); ?>" />
    <p class="description"><?php esc_html_e('End date (only applies if "Custom Date Range" is chosen above).', 'bug-animation'); ?></p>
    <?php
}

/**
 * Render start time.
 */
function buganimation_schedule_start_time_render() {
    $time = get_option('buganimation_schedule_start_time', '');
    ?>
    <input type="time" name="buganimation_schedule_start_time" id="buganimation_schedule_start_time" value="<?php echo esc_attr($time); ?>" />
    <p class="description"><?php esc_html_e('Optional start time for the bugs to be active each day (e.g. 09:00).', 'bug-animation'); ?></p>
    <?php
}

/**
 * Render end time.
 */
function buganimation_schedule_end_time_render() {
    $time = get_option('buganimation_schedule_end_time', '');
    ?>
    <input type="time" name="buganimation_schedule_end_time" id="buganimation_schedule_end_time" value="<?php echo esc_attr($time); ?>" />
    <p class="description"><?php esc_html_e('Optional end time for the bugs to stop each day (e.g. 17:00).', 'bug-animation'); ?></p>
    <?php
}

/**
 * Display the plugin's settings page.
 *
 * @return void
 */
function buganimation_options_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'bug-animation' ) );
    }
    ?>
    <style>
        .bug-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .bug-switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }
        .bug-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        .bug-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .bug-slider {
            background-color: #2271b1;
        }
        input:checked + .bug-slider:before {
            transform: translateX(20px);
        }
        /* Improve settings form spacing */
        .form-table th { padding-top: 25px; }
        .form-table td { padding-top: 20px; }
    </style>
    <div class="wrap">
        <h1><?php esc_html_e( 'Bug Animation Settings', 'bug-animation' ); ?></h1>
    <form action="options.php" method="post">
        <?php
        settings_fields('buganimation_options');
        do_settings_sections('buganimation-settings');
        submit_button();
        ?>
    </form>
    <p>
        <?php
        printf(
            wp_kses(
                /* translators: %s: PayPal donation link */
                __( 'If you found this plugin useful, please do <a href="%s">Support</a>', 'bug-animation' ),
                array(
                    'a' => array(
                        'href' => array(),
                    ),
                )
            ),
            esc_url( 'https://www.paypal.com/paypalme/ABIDKP211' )
        );
        ?>
    </p>
    </div>
    <?php
}

/**
 * Enqueue admin scripts for the settings page.
 */
function buganimation_admin_enqueue_scripts($hook) {
    if ($hook !== 'settings_page_buganimation-settings') {
        return;
    }
    
    $select2_css_path = plugin_dir_path( __FILE__ ) . 'css/select2.min.css';
    $select2_js_path  = plugin_dir_path( __FILE__ ) . 'js/select2.min.js';
    $select2_css_ver  = file_exists( $select2_css_path ) ? filemtime( $select2_css_path ) : '4.0.13';
    $select2_js_ver   = file_exists( $select2_js_path ) ? filemtime( $select2_js_path ) : '4.0.13';
    wp_enqueue_style( 'select2-css', plugin_dir_url( __FILE__ ) . 'css/select2.min.css', array(), $select2_css_ver );
    wp_enqueue_script( 'select2-js', plugin_dir_url( __FILE__ ) . 'js/select2.min.js', array( 'jquery' ), $select2_js_ver, true );
    
    // Inject the inline logic
    wp_localize_script( 'select2-js', 'bugAnimationAdmin', array(
        'nonce'          => wp_create_nonce( 'buganimation_search' ),
        'i18n'           => array(
            'emptyPostTypes' => esc_html__( 'Please select at least one post type before saving.', 'bug-animation' ),
            'emptyItems'     => esc_html__( 'Please select at least one item before saving.', 'bug-animation' ),
        ),
    ) );
    wp_add_inline_script('select2-js', "
        jQuery(document).ready(function($) {
            // Initialize Basic Select2 for Post Types
            $('#buganimation_selected_post_types').select2();

            // Initialize Select2 with AJAX for Specific Items
            $('#buganimation_selected_posts').select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term,
                            action: 'buganimation_search_items',
                            nonce: bugAnimationAdmin.nonce
                        };
                    },
                    processResults: function (data) {
                        return { results: data };
                    },
                    cache: true
                },
                minimumInputLength: 1
            });
            
            // Toggle Logic
            function toggleFields() {
                var displayCond = $('#buganimation_display_condition').val();
                var scheduleCond = $('select[name=\"buganimation_schedule_days\"]').val();
                
                // Display Condition toggles
                if (displayCond === 'specific_post_types') {
                    $('#buganimation_selected_post_types').closest('tr').show();
                    $('#buganimation_selected_posts').closest('tr').hide();
                } else if (displayCond === 'specific_items') {
                    $('#buganimation_selected_post_types').closest('tr').hide();
                    $('#buganimation_selected_posts').closest('tr').show();
                } else {
                    $('#buganimation_selected_post_types').closest('tr').hide();
                    $('#buganimation_selected_posts').closest('tr').hide();
                }
                
                // Schedule Date toggles
                if (scheduleCond === 'date_range') {
                    $('input[name=\"buganimation_schedule_start_date\"]').closest('tr').show();
                    $('input[name=\"buganimation_schedule_end_date\"]').closest('tr').show();
                } else {
                    $('input[name=\"buganimation_schedule_start_date\"]').closest('tr').hide();
                    $('input[name=\"buganimation_schedule_end_date\"]').closest('tr').hide();
                }
                
                // Time toggles
                if (scheduleCond === 'always') {
                    $('input[name=\"buganimation_schedule_start_time\"]').closest('tr').hide();
                    $('input[name=\"buganimation_schedule_end_time\"]').closest('tr').hide();
                } else {
                    $('input[name=\"buganimation_schedule_start_time\"]').closest('tr').show();
                    $('input[name=\"buganimation_schedule_end_time\"]').closest('tr').show();
                }
            }
            
            $('#buganimation_display_condition, select[name=\"buganimation_schedule_days\"]').on('change', toggleFields);
            toggleFields(); // run on load

            // ── Form validation ──────────────────────────────────────────────
            function showInlineError($field, message) {
                // Remove any previous inline error for this field.
                $field.closest('tr').find('.buganimation-field-error').remove();
                var $err = $('<p class="buganimation-field-error" style="color:#d63638; font-weight:600; margin-top:6px;"></p>').text(message);
                $field.closest('td').append($err);
                // Highlight the select2 container.
                $field.next('.select2-container').find('.select2-selection').css('border-color', '#d63638');
                $('html, body').animate({ scrollTop: $err.offset().top - 120 }, 300);
            }

            function clearInlineError($field) {
                $field.closest('tr').find('.buganimation-field-error').remove();
                $field.next('.select2-container').find('.select2-selection').css('border-color', '');
            }

            $('form[action=\"options.php\"]').on('submit', function(e) {
                var displayCond = $('#buganimation_display_condition').val();
                var valid = true;

                // Clear previous errors.
                clearInlineError($('#buganimation_selected_post_types'));
                clearInlineError($('#buganimation_selected_posts'));

                if (displayCond === 'specific_post_types') {
                    var postTypes = $('#buganimation_selected_post_types').val();
                    if (!postTypes || postTypes.length === 0) {
                        showInlineError($('#buganimation_selected_post_types'), bugAnimationAdmin.i18n.emptyPostTypes);
                        valid = false;
                    }
                }

                if (displayCond === 'specific_items') {
                    var items = $('#buganimation_selected_posts').val();
                    if (!items || items.length === 0) {
                        showInlineError($('#buganimation_selected_posts'), bugAnimationAdmin.i18n.emptyItems);
                        valid = false;
                    }
                }

                if (!valid) {
                    e.preventDefault();
                    return false;
                }
            });

            // Clear inline error when the user makes a selection.
            $('#buganimation_selected_post_types').on('change', function() {
                clearInlineError($(this));
            });
            $('#buganimation_selected_posts').on('change', function() {
                clearInlineError($(this));
            });
        });
    ");
}
add_action('admin_enqueue_scripts', 'buganimation_admin_enqueue_scripts');

/**
 * AJAX handler for Select2 post search.
 */
function buganimation_ajax_search_items() {
    $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
    if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, 'buganimation_search' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    if (empty($term)) {
        wp_send_json(array());
    }
    
    // Get all public post types except attachment
    $post_types = get_post_types(array('public' => true), 'names');
    if (isset($post_types['attachment'])) {
        unset($post_types['attachment']);
    }
    
    $query = new WP_Query(array(
        's' => $term,
        'post_type' => array_values($post_types),
        'post_status' => 'publish',
        'posts_per_page' => 50,
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false
    ));
    
    $results = array();
    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            $results[] = array(
                'id' => $post->ID,
                'text' => $post->post_title . ' (' . get_post_type_object($post->post_type)->labels->singular_name . ')'
            );
        }
    }
    
    wp_send_json($results);
}
add_action('wp_ajax_buganimation_search_items', 'buganimation_ajax_search_items');

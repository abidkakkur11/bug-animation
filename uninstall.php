<?php
// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$buganimation_options = array(
    'buganimation_enabled',
    'buganimation_min_bugs',
    'buganimation_max_bugs',
    'buganimation_mouse_over',
    'buganimation_bug_types',
    'buganimation_display_condition',
    'buganimation_selected_post_types',
    'buganimation_selected_posts',
    'buganimation_schedule_days',
    'buganimation_schedule_start_date',
    'buganimation_schedule_end_date',
    'buganimation_schedule_start_time',
    'buganimation_schedule_end_time'
);

foreach ( $buganimation_options as $buganimation_option ) {
    delete_option( $buganimation_option );
}

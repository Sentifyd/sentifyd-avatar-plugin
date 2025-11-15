<?php
// Uninstall cleanup for Sentifyd Avatar plugin
// Deletes plugin options when the plugin is uninstalled.

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// In single site, remove the stored settings option.
if (!function_exists('is_multisite') || !is_multisite()) {
    delete_option('sentifyd_settings');
    return;
}

// In multisite, delete the option for each site using core APIs (no direct DB queries).
$sentifyd_site_ids = get_sites(['fields' => 'ids']);
if (!empty($sentifyd_site_ids) && is_array($sentifyd_site_ids)) {
    $sentifyd_current_blog_id = get_current_blog_id();
    foreach ($sentifyd_site_ids as $sentifyd_site_id) {
        switch_to_blog((int) $sentifyd_site_id);
        delete_option('sentifyd_settings');
    }
    switch_to_blog((int) $sentifyd_current_blog_id);
}

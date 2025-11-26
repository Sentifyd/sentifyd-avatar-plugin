<?php
/*
 * Plugin Name:       Sentifyd Avatar
 * Plugin URI:        https://sentifyd.io
 * Description:       Easily deploy the Sentifyd avatar web component on your WordPress site.
 * Version:           1.1.0
 * Requires at least: 6.3
 * Author:            Sentifyd.io
 * Author URI:        https://sentifyd.io/about-us
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sentifyd-avatar
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin version for cache-busting enqueued assets (derived from plugin header when possible)
if (!defined('SENTIFYD_AVATAR_VERSION')) {
    if (function_exists('get_file_data')) {
        $sentifyd_plugin_data = get_file_data(__FILE__, ['Version' => 'Version'], 'plugin');
        $sentifyd_ver = isset($sentifyd_plugin_data['Version']) && $sentifyd_plugin_data['Version'] ? $sentifyd_plugin_data['Version'] : '1.0.0';
        define('SENTIFYD_AVATAR_VERSION', $sentifyd_ver);
    } else {
        define('SENTIFYD_AVATAR_VERSION', '1.0.0');
    }
}


/**
 * Add the Sentifyd admin menu item.
 */
function sentifyd_add_admin_menu() {
    add_menu_page(
        __('Sentifyd Avatar', 'sentifyd-avatar'),
        __('Sentifyd Avatar', 'sentifyd-avatar'),
        'manage_options',
        'sentifyd_avatar',
        'sentifyd_options_page_html',
        'dashicons-format-chat'
    );
}
add_action('admin_menu', 'sentifyd_add_admin_menu');

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_sentifyd_avatar') return;

    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    wp_add_inline_script(
        'wp-color-picker',
        '(function($){$(function(){ $(".sentifyd-color-field").wpColorPicker(); });})(jQuery);'
    );
});

/**
 * Add a Settings link in the Plugins list row for this plugin.
 */
function sentifyd_plugin_action_links($links) {
    $settings_url  = esc_url( admin_url('admin.php?page=sentifyd_avatar') );
    $settings_link = '<a href="' . $settings_url . '">' . esc_html__('Settings', 'sentifyd-avatar') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'sentifyd_plugin_action_links');

/**
 * Sanitize plugin settings prior to saving.
 */
function sentifyd_default_settings() {
    return [
        // text/url fields default to ''
        'sentifyd_api_key'           => '',
        'sentifyd_avatar_id'         => '',
        'sentifyd_token_endpoint'    => '',
        'sentifyd_terms_href'        => '',
        'sentifyd_privacy_href'      => '',
        'sentifyd_brand_name'        => '',
        'sentifyd_brand_logo'        => '',
        'sentifyd_avatar_background' => '',
        'sentifyd_radius_corner'     => '',
        // color variable fields
        'sentifyd_color_primary'                => '',
        'sentifyd_color_secondary'              => '',
        'sentifyd_color_text_primary_bg'        => '',
        'sentifyd_color_text_secondary_bg'      => '',
        // booleans
        'sentifyd_toggler'           => 'on',
        'sentifyd_compact'           => 'off',
        'sentifyd_enable_captions'   => 'on',
        'sentifyd_require_auth'      => 'off',
    ];
}

function sentifyd_sanitize_settings($input) {
    $input = is_array($input) ? $input : [];

    // Start from previous + defaults
    $prev       = (array) get_option('sentifyd_settings', []);
    $sanitized  = wp_parse_args($prev, sentifyd_default_settings());

    // Text / URL fields
    $sanitized['sentifyd_api_key']           = isset($input['sentifyd_api_key']) ? sanitize_text_field($input['sentifyd_api_key']) : $sanitized['sentifyd_api_key'];
    $sanitized['sentifyd_avatar_id']         = isset($input['sentifyd_avatar_id']) ? sanitize_text_field($input['sentifyd_avatar_id']) : $sanitized['sentifyd_avatar_id'];
    $sanitized['sentifyd_token_endpoint']    = isset($input['sentifyd_token_endpoint']) ? esc_url_raw($input['sentifyd_token_endpoint']) : $sanitized['sentifyd_token_endpoint'];
    $sanitized['sentifyd_terms_href']        = isset($input['sentifyd_terms_href']) ? esc_url_raw($input['sentifyd_terms_href']) : $sanitized['sentifyd_terms_href'];
    $sanitized['sentifyd_privacy_href']      = isset($input['sentifyd_privacy_href']) ? esc_url_raw($input['sentifyd_privacy_href']) : $sanitized['sentifyd_privacy_href'];
    $sanitized['sentifyd_brand_name']        = isset($input['sentifyd_brand_name']) ? sanitize_text_field($input['sentifyd_brand_name']) : $sanitized['sentifyd_brand_name'];
    $sanitized['sentifyd_brand_logo']        = isset($input['sentifyd_brand_logo']) ? esc_url_raw($input['sentifyd_brand_logo']) : $sanitized['sentifyd_brand_logo'];
    $sanitized['sentifyd_avatar_background'] = isset($input['sentifyd_avatar_background']) ? sanitize_text_field($input['sentifyd_avatar_background']) : $sanitized['sentifyd_avatar_background'];
    $sanitized['sentifyd_radius_corner']     = isset($input['sentifyd_radius_corner']) ? sanitize_text_field($input['sentifyd_radius_corner']) : $sanitized['sentifyd_radius_corner'];
    
    // Color fields
    $color_keys = [
        'sentifyd_color_primary',
        'sentifyd_color_secondary',
        'sentifyd_color_text_primary_bg',
        'sentifyd_color_text_secondary_bg'
    ];

    foreach ($color_keys as $ck) {
        if (array_key_exists($ck, $input)) {
            $raw = is_string($input[$ck]) ? wp_unslash($input[$ck]) : '';
            $raw = trim($raw);

            if ($raw === '') {
                // empty means fallback to built-in defaults on the front end
                $sanitized[$ck] = '';
                continue;
            }

            $hex = sanitize_hex_color($raw);
            if ($hex !== null) {
                $sanitized[$ck] = $hex;
            } else {
                // If invalid, keep previous saved value (no change)
                // (prevents an attacker from clobbering it with junk)
                $sanitized[$ck] = isset($sanitized[$ck]) ? $sanitized[$ck] : '';
            }
        }
    }

    // Checkboxes: explicit on/off
    foreach (['sentifyd_toggler','sentifyd_compact','sentifyd_enable_captions', 'sentifyd_require_auth'] as $checkbox) {
        $sanitized[$checkbox] = (isset($input[$checkbox]) && $input[$checkbox] === 'on') ? 'on' : 'off';
    }

    return $sanitized;
}

/**
 * Register the plugin settings.
 */
function sentifyd_settings_init() {
    register_setting(
        'sentifyd_options_group',
        'sentifyd_settings',
        [
            'sanitize_callback' => 'sentifyd_sanitize_settings',
            'default'           => sentifyd_default_settings(),
        ]
    );

    add_settings_section(
        'sentifyd_general_section',
        __('Avatar Settings', 'sentifyd-avatar'),
        null,
        'sentifyd_options_group'
    );

    add_settings_field(
        'sentifyd_api_key',
        __('Enter your avatar API Key', 'sentifyd-avatar'),
        'sentifyd_api_key_render',
        'sentifyd_options_group',
        'sentifyd_general_section'
    );

    add_settings_field(
        'sentifyd_avatar_id',
        __('Avatar ID', 'sentifyd-avatar'),
        'sentifyd_avatar_id_render',
        'sentifyd_options_group',
        'sentifyd_general_section'
    );

    add_settings_field(
        'sentifyd_token_endpoint',
        __('Secure Token Endpoint', 'sentifyd-avatar'),
        'sentifyd_token_endpoint_render',
        'sentifyd_options_group',
        'sentifyd_general_section'
    );

    add_settings_field(
        'sentifyd_require_auth',
        __('Require Authentication', 'sentifyd-avatar'),
        'sentifyd_require_auth_render',
        'sentifyd_options_group',
        'sentifyd_general_section'
    );

    add_settings_section(
        'sentifyd_attributes_section',
        __('Other Avatar Attributes', 'sentifyd-avatar'),
        null,
        'sentifyd_options_group'
    );

    add_settings_field(
        'sentifyd_toggler',
        __('Enable Toggler', 'sentifyd-avatar'),
        'sentifyd_toggler_render',
        'sentifyd_options_group',
        'sentifyd_attributes_section'
    );

    add_settings_field(
        'sentifyd_compact',
        __('Compact Mode', 'sentifyd-avatar'),
        'sentifyd_compact_render',
        'sentifyd_options_group',
        'sentifyd_attributes_section'
    );

    add_settings_field(
        'sentifyd_enable_captions',
        __('Enable Captions', 'sentifyd-avatar'),
        'sentifyd_enable_captions_render',
        'sentifyd_options_group',
        'sentifyd_attributes_section'
    );

    add_settings_section(
        'sentifyd_branding_section',
        __('Branding Attributes', 'sentifyd-avatar'),
        null,
        'sentifyd_options_group'
    );

    add_settings_field(
        'sentifyd_brand_name',
        __('Brand Name', 'sentifyd-avatar'),
        'sentifyd_brand_name_render',
        'sentifyd_options_group',
        'sentifyd_branding_section'
    );

    add_settings_field(
        'sentifyd_brand_logo',
        __('Brand Logo URL', 'sentifyd-avatar'),
        'sentifyd_brand_logo_render',
        'sentifyd_options_group',
        'sentifyd_branding_section'
    );

    add_settings_field(
        'sentifyd_terms_href',
        __('Terms Link', 'sentifyd-avatar'),
        'sentifyd_terms_href_render',
        'sentifyd_options_group',
        'sentifyd_branding_section'
    );

    add_settings_field(
        'sentifyd_privacy_href',
        __('Privacy Link', 'sentifyd-avatar'),
        'sentifyd_privacy_href_render',
        'sentifyd_options_group',
        'sentifyd_branding_section'
    );

    add_settings_section(
        'sentifyd_theme_section',
        __('Avatar Widget Theme', 'sentifyd-avatar'),
        function(){
            echo '<p>' . esc_html__('Customize the avatar widget theme. Leave blank to use built-in defaults.', 'sentifyd-avatar') . '</p>';
        },
        'sentifyd_options_group'
    );

    add_settings_field(
        'sentifyd_avatar_background',
        __('Avatar Background', 'sentifyd-avatar'),
        'sentifyd_avatar_background_render',
        'sentifyd_options_group',
        'sentifyd_theme_section'
    );

    add_settings_field(
        'sentifyd_radius_corner',
        __('Curved Corner Radius', 'sentifyd-avatar'),
        'sentifyd_radius_corner_render',
        'sentifyd_options_group',
        'sentifyd_theme_section'
    );

    add_settings_field(
        'sentifyd_color_primary',
        __('Primary color', 'sentifyd-avatar'),
        'sentifyd_color_primary_render',
        'sentifyd_options_group',
        'sentifyd_theme_section'
    );
    add_settings_field(
        'sentifyd_color_secondary',
        __('Secondary color', 'sentifyd-avatar'),
        'sentifyd_color_secondary_render',
        'sentifyd_options_group',
        'sentifyd_theme_section'
    );
    add_settings_field(
        'sentifyd_color_text_primary_bg',
        __('Text color primary on background', 'sentifyd-avatar'),
        'sentifyd_color_text_primary_bg_render',
        'sentifyd_options_group',
        'sentifyd_theme_section'
    );
    add_settings_field(
        'sentifyd_color_text_secondary_bg',
        __('Text color on secondary background', 'sentifyd-avatar'),
        'sentifyd_color_text_secondary_bg_render',
        'sentifyd_options_group',
        'sentifyd_theme_section'
    );
}
add_action('admin_init', 'sentifyd_settings_init');

add_action('admin_init', function () {
    if ( ! function_exists('wp_add_privacy_policy_content') ) return;

    $content = wp_kses_post(
        __(
            'This plugin embeds the Sentifyd avatar web component which stores session-scoped data in the browser (sessionStorage) to maintain UI state and conversation context. Keys may include avatar open/closed state, authentication data such as short-lived tokens, and conversation transcript. Data clears when the tab/window closes. No data is stored in cookies or local storage by the widget.',
            'sentifyd-avatar'
        )
    );

    wp_add_privacy_policy_content(__('Sentifyd Avatar', 'sentifyd-avatar'), $content);
});

/**
 * Retrieve an option value with optional default.
 *
 * @param string $key     Option key to retrieve.
 * @param mixed  $default Optional default value when the key is not set.
 *
 * @return mixed
 */
function sentifyd_get_option($key, $default = '') {
    $options = (array) get_option('sentifyd_settings', sentifyd_default_settings());
    return array_key_exists($key, $options) ? $options[$key] : $default;
}

function sentifyd_api_key_render() {
    ?>
    <div style="max-width:520px; display:flex; align-items:center; gap:8px;">
        <input type="password" id="sentifyd_api_key" name="sentifyd_settings[sentifyd_api_key]" value="<?php echo esc_attr( sentifyd_get_option('sentifyd_api_key') ); ?>" class="regular-text" style="flex:1;">
    <button type="button" class="button" aria-label="<?php echo esc_attr__('Show API Key', 'sentifyd-avatar'); ?>" onclick="(function(btn){var i=document.getElementById('sentifyd_api_key');if(!i)return;var pass=i.getAttribute('type')==='password';i.setAttribute('type',pass?'text':'password');var ic=btn.querySelector('.dashicons');if(ic){ic.classList.toggle('dashicons-visibility');ic.classList.toggle('dashicons-hidden');}btn.setAttribute('aria-label',pass?'<?php echo esc_js(__('Hide API Key', 'sentifyd-avatar')); ?>':'<?php echo esc_js(__('Show API Key', 'sentifyd-avatar')); ?>');})(this)">
            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
        </button>
    </div>
    <p class="description"><?php echo esc_html__("Stored server-side only and used to mint short-lived tokens; never exposed in the browser DOM. Required unless you provide a custom token endpoint.", 'sentifyd-avatar'); ?></p>
    <?php
}

function sentifyd_avatar_id_render() {
    ?>
    <input type="text" name="sentifyd_settings[sentifyd_avatar_id]" value="<?php echo esc_attr(sentifyd_get_option('sentifyd_avatar_id')); ?>" class="regular-text">
    <p class="description"><strong><?php echo esc_html__('Required', 'sentifyd-avatar'); ?></strong>. <?php echo esc_html__('Find your Avatar ID in the avatar page in Sentifyd platform.', 'sentifyd-avatar'); ?></p>
    <?php
}

function sentifyd_token_endpoint_render() {
    ?>
    <input type="url" name="sentifyd_settings[sentifyd_token_endpoint]" value="<?php echo esc_attr(sentifyd_get_option('sentifyd_token_endpoint')); ?>" class="regular-text">
    <p class="description"><?php echo esc_html__("Optional. If provided, the avatar will call your secure token endpoint to obtain short-lived session tokens. If omitted, this plugin uses its built-in WordPress REST endpoint to mint tokens using your stored API key (which is never exposed to the browser).", 'sentifyd-avatar'); ?></p>
    <?php
}

function sentifyd_require_auth_render() {
    ?>
    <input type="hidden" name="sentifyd_settings[sentifyd_require_auth]" value="off">
    <input id="sentifyd_require_auth" type="checkbox" name="sentifyd_settings[sentifyd_require_auth]" value="on" <?php checked(sentifyd_get_option('sentifyd_require_auth', 'off'), 'on'); ?> >
    <label for="sentifyd_require_auth"><?php echo esc_html__('Only display the avatar to logged-in users.', 'sentifyd-avatar'); ?></label>
    <p class="description"><?php echo esc_html__('If enabled, guest visitors will not see the avatar and the API endpoint will reject unauthenticated requests.', 'sentifyd-avatar'); ?></p>
    <?php
}

function sentifyd_toggler_render() {
    ?>
    <input type="hidden" name="sentifyd_settings[sentifyd_toggler]" value="off">
    <input id="sentifyd_toggler" type="checkbox" name="sentifyd_settings[sentifyd_toggler]" value="on" <?php checked(sentifyd_get_option('sentifyd_toggler', 'on'), 'on'); ?> >
    <label for="sentifyd_toggler"><?php echo esc_html__('Display the avatar as a minimizable toggler (bottom-right style).', 'sentifyd-avatar'); ?></label>
    <p class="description">
        <?php echo esc_html__('When enabled (default), the plugin auto-injects the toggler on each page. When disabled, the avatar will not auto-inject;', 'sentifyd-avatar'); ?>
        <?php
        $shortcode_sentence = sprintf(
            /* translators: %s: shortcode to insert the avatar, e.g. <code>[sentifyd_avatar]</code> */
            __('place it where you want using the shortcode %s.', 'sentifyd-avatar'),
            '<code>[sentifyd_avatar]</code>'
        );
        echo wp_kses($shortcode_sentence, array('code' => array()));
        ?>
    </p>
    <?php
}

function sentifyd_compact_render() {
    ?>
    <input type="hidden" name="sentifyd_settings[sentifyd_compact]" value="off">
    <input id="sentifyd_compact" type="checkbox" name="sentifyd_settings[sentifyd_compact]" value="on" <?php checked(sentifyd_get_option('sentifyd_compact', 'off'), 'on'); ?> >
    <label for="sentifyd_compact"><?php echo esc_html__('Displays the avatar without the header or footer.', 'sentifyd-avatar'); ?></label>
    <?php
}

function sentifyd_enable_captions_render() {
    ?>
    <input type="hidden" name="sentifyd_settings[sentifyd_enable_captions]" value="off">
    <input id="sentifyd_enable_captions" type="checkbox" name="sentifyd_settings[sentifyd_enable_captions]" value="on" <?php checked(sentifyd_get_option('sentifyd_enable_captions', 'on'), 'on'); ?> >
    <label for="sentifyd_enable_captions"><?php echo esc_html__('Show captions on the avatar by default.', 'sentifyd-avatar'); ?></label>
    <?php
}

function sentifyd_terms_href_render() {
    ?>
    <input type="url" name="sentifyd_settings[sentifyd_terms_href]" value="<?php echo esc_attr(sentifyd_get_option('sentifyd_terms_href')); ?>" class="regular-text">
    <p class="description"><?php echo esc_html__('Full URL to your Terms of Service.', 'sentifyd-avatar'); ?></p>
    <?php
}

function sentifyd_privacy_href_render() {
    ?>
    <input type="url" name="sentifyd_settings[sentifyd_privacy_href]" value="<?php echo esc_attr(sentifyd_get_option('sentifyd_privacy_href')); ?>" class="regular-text">
    <p class="description"><?php echo esc_html__('Full URL to your Privacy Policy.', 'sentifyd-avatar'); ?></p>
    <?php
}

function sentifyd_brand_name_render() {
    ?>
    <input type="text" name="sentifyd_settings[sentifyd_brand_name]" value="<?php echo esc_attr(sentifyd_get_option('sentifyd_brand_name')); ?>" class="regular-text">
    <p class="description"><?php echo esc_html__('The name of your brand or institution, used in the exported transcripts.', 'sentifyd-avatar'); ?></p>
    <?php
}

function sentifyd_brand_logo_render() {
    ?>
    <input type="url" name="sentifyd_settings[sentifyd_brand_logo]" value="<?php echo esc_attr(sentifyd_get_option('sentifyd_brand_logo')); ?>" class="regular-text">
    <p class="description"><?php echo esc_html__("The URL of the logo image displayed in the avatar's header.", 'sentifyd-avatar'); ?></p>
    <?php
}

function sentifyd_avatar_background_render() {
    ?>
    <input type="text" name="sentifyd_settings[sentifyd_avatar_background]" value="<?php echo esc_attr(sentifyd_get_option('sentifyd_avatar_background')); ?>" class="regular-text" placeholder="<?php echo esc_attr__('#ffffff or transparent', 'sentifyd-avatar'); ?>">
    <p class="description"><?php echo esc_html__('Background behind the avatar. Accepts a CSS color value (e.g., #ffffff, rgb(0 0 0 / 0.5), or linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%)).', 'sentifyd-avatar'); ?></p>
    <?php
}

function sentifyd_radius_corner_render() {
    ?>
    <input type="text" name="sentifyd_settings[sentifyd_radius_corner]" value="<?php echo esc_attr(sentifyd_get_option('sentifyd_radius_corner')); ?>" class="regular-text" placeholder="<?php echo esc_attr__('12px, 1rem, 50%', 'sentifyd-avatar'); ?>">
    <p class="description"><?php echo esc_html__('Curved corner radius for the avatar widget. Accepts CSS length values (e.g., 12px, 1rem, 50%). Set to 0 for square corners.', 'sentifyd-avatar'); ?></p>
    <?php
}

function sentifyd_color_primary_render() {
    $val = sentifyd_get_option('sentifyd_color_primary');
    ?>
    <input type="text"
           name="sentifyd_settings[sentifyd_color_primary]"
           value="<?php echo esc_attr( $val ); ?>"
           class="regular-text sentifyd-color-field"
           data-default-color="#00bfdb">
    <?php
}

function sentifyd_color_secondary_render() {
    $val = sentifyd_get_option('sentifyd_color_secondary');
    ?>
    <input type="text"
           name="sentifyd_settings[sentifyd_color_secondary]"
           value="<?php echo esc_attr( $val ); ?>"
           class="regular-text sentifyd-color-field"
           data-default-color="#801bea">
    <?php
}

function sentifyd_color_text_primary_bg_render() {
    $val = sentifyd_get_option('sentifyd_color_text_primary_bg');
    ?>
    <input type="text"
           name="sentifyd_settings[sentifyd_color_text_primary_bg]"
           value="<?php echo esc_attr( $val ); ?>"
           class="regular-text sentifyd-color-field"
           data-default-color="#011f28">
    <?php
}

function sentifyd_color_text_secondary_bg_render() {
    $val = sentifyd_get_option('sentifyd_color_text_secondary_bg');
    ?>
    <input type="text"
           name="sentifyd_settings[sentifyd_color_text_secondary_bg]"
           value="<?php echo esc_attr( $val ); ?>"
           class="regular-text sentifyd-color-field"
           data-default-color="#ffffff">
    <?php
}


/**
 * Render the plugin options page.
 */
function sentifyd_options_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('sentifyd_options_group');
            do_settings_sections('sentifyd_options_group');
            submit_button(esc_html__('Save Settings', 'sentifyd-avatar'));
            ?>
        </form>

        <hr>

        <h2><?php echo esc_html__('Sentifyd Account Management', 'sentifyd-avatar'); ?></h2>
        <p><?php echo esc_html__('Manage your Sentifyd account and conversation credits.', 'sentifyd-avatar'); ?></p>
        <p>
            <a href="https://sentifyd.io" target="_blank" rel="noopener noreferrer" class="button button-primary">
                <?php echo esc_html__('Visit Sentifyd.io', 'sentifyd-avatar'); ?>
            </a>
        </p>
    </div>
    <?php
}

/**
 * Build the <sentifyd-bot> tag HTML based on current settings.
 *
 * @return string The HTML tag or empty string when required settings are missing.
 */
function sentifyd_build_bot_tag() {
    $settings = (array) get_option('sentifyd_settings', sentifyd_default_settings());

    $api_key        = isset($settings['sentifyd_api_key']) ? trim($settings['sentifyd_api_key']) : '';
    $token_endpoint = isset($settings['sentifyd_token_endpoint']) ? trim($settings['sentifyd_token_endpoint']) : '';
    $avatar_id      = isset($settings['sentifyd_avatar_id']) ? trim($settings['sentifyd_avatar_id']) : '';

    $has_api_key    = !empty($api_key);
    $has_custom_bff = !empty($token_endpoint);

    // Require avatar id and either a custom token endpoint OR a stored API key (to use built-in endpoint)
    if (empty($avatar_id) || (!$has_api_key && !$has_custom_bff)) {
        return '';
    }

    // Check if authentication is required
    $require_auth = (isset($settings['sentifyd_require_auth']) && $settings['sentifyd_require_auth'] === 'on');
    if ($require_auth && !is_user_logged_in()) {
        return '';
    }

    $attributes = [];

    if ($has_custom_bff) {
        // Use customer-provided endpoint as-is
        $attributes['token-endpoint'] = $token_endpoint;
        $attributes['avatar-id']      = $avatar_id;
    } else {
        // Use plugin's REST endpoint and include avatar_id in query string
        $query_args = ['avatar_id' => $avatar_id];
        // Include nonce for logged-in users to authorize token requests
        if (is_user_logged_in()) {
            $query_args['_wpnonce'] = wp_create_nonce('wp_rest');
        }

        $attributes['token-endpoint'] = add_query_arg(
            $query_args,
            rest_url('sentifyd/v1/request_tokens')
        );
        $attributes['avatar-id']      = $avatar_id;
    }

    // Boolean attributes with defaults:
    // - toggler: defaults to true if not set
    // - enable-captions: defaults to true if not set
    // - compact: defaults to false
    $bool_option_to_attr = [
        'sentifyd_toggler'           => 'toggler',
        'sentifyd_compact'           => 'compact',
        'sentifyd_enable_captions'   => 'enable-captions',
    ];

    foreach ($bool_option_to_attr as $optKey => $attrName) {
        // Establish default 'on' or 'off' for each attribute
        $default_value = ($attrName === 'toggler' || $attrName === 'enable-captions') ? 'on' : 'off';
        
        // Get the saved value, falling back to the established default if not set
        $value = isset($settings[$optKey]) ? $settings[$optKey] : $default_value;

        // Convert 'on'/'off' to 'true'/'false' for the HTML attribute
        $attributes[$attrName] = ($value === 'on') ? 'true' : 'false';
    }

    // Text attributes mapping from options to kebab-case attributes
    $text_option_to_attr = [
        'sentifyd_terms_href'         => 'terms-href',
        'sentifyd_privacy_href'       => 'privacy-href',
        'sentifyd_brand_name'         => 'brand-name',
        'sentifyd_brand_logo'         => 'brand-logo',
        'sentifyd_avatar_background'  => 'avatar-background',
        'sentifyd_radius_corner'      => 'radius-corner',
    ];

    foreach ($text_option_to_attr as $optKey => $attrName) {
        $val = sentifyd_get_option($optKey);
        if (!empty($val)) {
            $attributes[$attrName] = $val;
        }
    }

    // Determine UI language from the WordPress site language (e.g. en-US, fr-FR)
    $supported_ui_languages = ['en','fr','de','es','ar','zh'];
    $page_lang_raw = get_bloginfo('language'); // Returns something like en-US or fr-FR
    if (is_string($page_lang_raw) && $page_lang_raw !== '') {
        $page_lang_prefix = strtolower(substr($page_lang_raw, 0, 2));
        if (in_array($page_lang_prefix, $supported_ui_languages, true)) {
            $attributes['ui-language'] = $page_lang_prefix;
        } else {
            // Fallback: explicitly set to English
            $attributes['ui-language'] = 'en';
        }
    } else {
        // If we cannot detect language, default to English.
        $attributes['ui-language'] = 'en';
    }

    $html_attributes = '';
    foreach ($attributes as $key => $val) {
        $html_attributes .= sprintf('%s="%s" ', esc_attr($key), esc_attr($val));
    }

    return sprintf('<sentifyd-bot %s></sentifyd-bot>', $html_attributes);
}

/**
 * Shortcode to place the avatar inline in content.
 * Usage: [sentifyd_avatar]
 */
function sentifyd_avatar_shortcode($atts) {
    $tag = sentifyd_build_bot_tag();
    if (empty($tag)) {
        return '';
    }

    // Ensure the script is present; element can be inline without auto-injection
    wp_enqueue_script(
        'sentifyd-main',
        'https://frontend.sentifyd.io/sentifyd-bot/main.js',
        [],
        SENTIFYD_AVATAR_VERSION,
        [
            'in_footer' => true,
            'strategy'  => 'async',
        ]
    );

    return $tag;
}
add_shortcode('sentifyd_avatar', 'sentifyd_avatar_shortcode');

/**
 * Enqueue the Sentifyd JS library and add the avatar element to the front-end.
 */
function sentifyd_deploy_bot() {
    // Auto-inject only when toggler is enabled (default). If disabled, rely on shortcode placement.
    $settings = (array) get_option('sentifyd_settings', sentifyd_default_settings());

    $toggler_on = (!isset($settings['sentifyd_toggler']) || $settings['sentifyd_toggler'] === 'on');
    if (!$toggler_on) {
        return;
    }

    $bot_tag = sentifyd_build_bot_tag();
    if (empty($bot_tag)) {
        return;
    }

    wp_enqueue_script(
        'sentifyd-main',
        'https://frontend.sentifyd.io/sentifyd-bot/main.js',
        [],
        SENTIFYD_AVATAR_VERSION,
        [
            'in_footer' => true,
            'strategy'  => 'async',
        ]
    );

    $inline_script = "document.addEventListener('DOMContentLoaded', function() {\n        if (!document.querySelector('sentifyd-bot')) {\n            document.body.insertAdjacentHTML('beforeend', " . wp_json_encode($bot_tag) . ");\n        }\n    });";

    wp_add_inline_script('sentifyd-main', $inline_script);
}

if (!is_admin()) {
    add_action('wp_enqueue_scripts', 'sentifyd_deploy_bot');
}

/**
 * Ensure the Sentifyd script is loaded as a module for the web component.
 *
 * @param string $tag    The HTML script tag.
 * @param string $handle The script handle.
 * @param string $src    The script source URL.
 *
 * @return string Filtered script tag output.
 */
function sentifyd_script_loader_tag($tag, $handle, $src) {
    if ('sentifyd-main' === $handle) {
        $tag = str_replace('<script ', '<script type="module" ', $tag);
    }

    return $tag;
}
add_filter('script_loader_tag', 'sentifyd_script_loader_tag', 10, 3);

// Apply user-defined colors on the frontend if provided
function sentifyd_enqueue_custom_css() {
    $settings = (array) get_option('sentifyd_settings', sentifyd_default_settings());
    $map = [
        'sentifyd_color_primary'           => '--primary-color',
        'sentifyd_color_secondary'         => '--secondary-color',
        'sentifyd_color_text_primary_bg'   => '--text-color-primary-bg',
        'sentifyd_color_text_secondary_bg' => '--text-color-secondary-bg',
    ];
    $lines = [];
    foreach ($map as $opt => $var) {
        $val = trim($settings[$opt] ?? '');
        if ($val !== '') {
            $lines[] = $var . ': ' . $val . ';';
        }
    }
    if (!$lines) return;
    
    // Register a dummy inline style handle and add our custom CSS variables
    wp_register_style('sentifyd-avatar-user-vars', false);
    wp_enqueue_style('sentifyd-avatar-user-vars');
    wp_add_inline_style('sentifyd-avatar-user-vars', 'sentifyd-bot {' . implode(' ', $lines) . '}');
}
add_action('wp_enqueue_scripts', 'sentifyd_enqueue_custom_css');

// Built-in REST endpoint to issue temporary tokens
add_action('rest_api_init', function () {
    register_rest_route('sentifyd/v1', '/request_tokens', [
        'methods'             => 'GET', // GET method to match frontend expectations
        'callback'            => 'sentifyd_rest_request_tokens',
        'permission_callback' => function () {
            // Check the plugin settings
            $settings = (array) get_option('sentifyd_settings', []);
            $require_auth = (isset($settings['sentifyd_require_auth']) && $settings['sentifyd_require_auth'] === 'on');

            // If the site owner enabled "Require Authentication", check if user is logged in.
            if ($require_auth) {
                return is_user_logged_in();
            }

            // Otherwise, it is a public endpoint for site visitors.
            // It provides short-lived session tokens for the frontend chat widget,
            // allowing unauthenticated site visitors to interact with the avatar.
            // Abuse is mitigated via IP-based rate limiting within the callback.
            return true;
        },
    ]);
});

/**
 * GET /wp-json/sentifyd/v1/request_tokens
 *
 * Exchanges the stored API key + avatar_id for temporary tokens using the Sentifyd backend.
 *
 * Response shape:
 * {
 *   "tokens": { "accessToken": "...", "refreshToken": "...", "tokenType"?: "Bearer", "expiresIn"?: 3600 },
 *   "avatarParameters"?: { ... }
 * }
 */
function sentifyd_rest_request_tokens( \WP_REST_Request $request ) {
    // Ensure responses are not cached by browsers or intermediaries
    nocache_headers();

    $settings        = (array) get_option('sentifyd_settings', []);
    $stored_api_key  = trim($settings['sentifyd_api_key'] ?? '');
    $stored_avatarId = trim($settings['sentifyd_avatar_id'] ?? '');

    // Accept ?avatar_id=...; fallback to stored setting
    $req_avatar_id = $request->get_param('avatar_id');
    $avatar_id     = is_numeric($req_avatar_id) ? (int) $req_avatar_id : ($stored_avatarId !== '' ? (int) $stored_avatarId : null);

    if (!$stored_api_key) {
        return new \WP_REST_Response(['error' => 'Avatar API key is not configured'], 500);
    }
    if ($avatar_id === null) {
        return new \WP_REST_Response(['error' => 'Avatar ID is not configured'], 400);
    }

    // Tiny cache per avatar to reduce upstream load
    $cache_key = 'sentifyd_token_' . md5((string) $avatar_id);
    $cached    = get_transient($cache_key);
    if (is_array($cached) && !empty($cached['tokens']) && isset($cached['expires_at']) && (time() < ((int) $cached['expires_at'] - 10))) {
        return new \WP_REST_Response([
            'tokens'           => $cached['tokens'],
            'avatarParameters' => $cached['avatarParameters'] ?? null,
        ], 200);
    }

    // Backend origin — allow integrators to override via filter.
    $backend_base = 'https://serve.sentifyd.io';
    $backend_base = rtrim(apply_filters('sentifyd_backend_base', $backend_base), '/');

    $login_endpoints = [
        $backend_base . '/api/v1/chatbot/login',
    ];

    $payload = [
        'avatar_api_key' => $stored_api_key,
        'avatar_id'      => $avatar_id,
    ];

    // Light IP-based rate limit: 1 request / 5 seconds (not applied to cache hits)
    $ip_raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    $ip_val = filter_var( $ip_raw, FILTER_VALIDATE_IP );
    $ip     = $ip_val ? $ip_val : 'unknown';
    $rl_key = 'sentifyd_rl_' . md5( $ip . '|' . (string) $avatar_id );
    if (get_transient($rl_key)) {
        return new \WP_REST_Response(['error' => 'rate_limited'], 429);
    }
    set_transient($rl_key, 1, 5);

    $last_error_status = null;
    $last_error_body   = null;
    $failure_reason    = null;

    foreach ($login_endpoints as $login_url) {
        $resp = wp_remote_post($login_url, [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($resp)) {
            // Transport error: try next endpoint
            continue;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $text = wp_remote_retrieve_body($resp);

        if ($code !== 200) {
            $last_error_status = $code;
            $last_error_body   = $text;
            continue;
        }

        $json = json_decode($text, true);
        if (!is_array($json)) {
            $last_error_status = 502;
            $last_error_body   = $text;
            $failure_reason    = 'Avatar backend returned an invalid response';
            continue;
        }

        // Support both { data: {...} } and flat shapes
        $data = $json['data'] ?? $json;
        if (!is_array($data)) {
            $last_error_status = 502;
            $last_error_body   = $text;
            $failure_reason    = 'Avatar backend returned an invalid response';
            continue;
        }

        // Support snake_case or camelCase from upstream
        $access  = $data['access_token']  ?? $data['accessToken']  ?? null;
        $refresh = $data['refresh_token'] ?? $data['refreshToken'] ?? null;
        $type    = $data['token_type']    ?? $data['tokenType']    ?? null;
        $exp     = $data['expires_in']    ?? $data['expiresIn']    ?? null;

        if (!$access || !$refresh) {
            $last_error_status = 502;
            $last_error_body   = $text;
            $failure_reason    = 'Avatar backend returned incomplete token data';
            continue;
        }

        $tokens = [
            'accessToken'  => (string) $access,
            'refreshToken' => (string) $refresh,
        ];
        if ($type !== null) $tokens['tokenType'] = (string) $type;
        if ($exp  !== null) $tokens['expiresIn'] = (int) $exp;

        $avatar_params = $data['avatar_params'] ?? $data['avatarParameters'] ?? null;

        // Store tiny cache with a short TTL derived from expiresIn
        $ttl = isset($tokens['expiresIn']) ? max(5, (int) $tokens['expiresIn'] - 5) : 55; // default ~1 min
        set_transient($cache_key, [
            'tokens'           => $tokens,
            'avatarParameters' => is_array($avatar_params) ? $avatar_params : null,
            'expires_at'       => time() + $ttl,
        ], $ttl);

        return new \WP_REST_Response([
            'tokens'           => $tokens,
            'avatarParameters' => is_array($avatar_params) ? $avatar_params : null,
        ], 200);
    }

    if ($last_error_status === null) {
        return new \WP_REST_Response(['error' => 'Unable to reach avatar backend'], 502);
    }

    return new \WP_REST_Response(
        ['error' => $failure_reason ?: 'Failed to authenticate with the avatar backend'],
        $last_error_status
    );
}

?>

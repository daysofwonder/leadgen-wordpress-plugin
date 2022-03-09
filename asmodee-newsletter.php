<?php

/**
 * Plugin Name: Asmodée newsletter
 * Description: Asmodée newsletter
 * Version: 0.2
 * Author: Apsulis
 * Author URI: https://www.apsulis.io
 * License: GPLv2 or later
 */
defined('WPINC') || die();

define('DIR_ASMODEE_NEWSLETTER', dirname(__FILE__));
define('ASMODEE_NEWSLETTER_VERSION', '0.1');

function asmodee_newsletter_install()
{
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'asmodee_newsletter_install');

function asmodee_newsletter_deactivation()
{
}
register_deactivation_hook(__FILE__, 'asmodee_newsletter_deactivation');

/**
 * Enqueue scripts and styles.
 */
function asmodee_newsletter_scripts()
{
    wp_enqueue_style(
        'asmodee-newsletter-css',
        plugins_url('assets/css/asmodee-newsletter.css', __FILE__),
        array(),
        '1.0.0'
    );

    wp_enqueue_script(
        'asmodee-newsletter-js',
        plugins_url('assets/js/newsletter.js', __FILE__),
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script(
        'asmodee-newsletter-js',
        'asmodee_newsletter',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'assets_url' => esc_url(plugins_url('assets/img/', __FILE__)),
            'nonce_newsletter' => wp_create_nonce('newsletter-nonce'),
            'txt_loading' => __('Submitting email', 'asmodee-newsletter'),
            'txt_user_subscribed' => __('You are now subscribed!', 'asmodee-newsletter'),
            'txt_user_subscribed_confirm' => __('An email has been sent to confirm!', 'asmodee-newsletter'),
            'txt_user_invalid_email' => __('Invalid email', 'asmodee-newsletter'),
            'txt_user_already_registred' => __('User already registred', 'asmodee-newsletter'),
            'txt_user_error' => __('An error has occured', 'asmodee-newsletter'),
        )
    );
}
add_action('wp_enqueue_scripts', 'asmodee_newsletter_scripts');

function asmodee_newsletter_display_subscription_block()
{
	if (!asmodee_newsletter_get_credentials_status()) {
		if (user_can(get_current_user_id(), 'manage_options')) {
			printf(
				__(
					'<p class="asmodee-newsletter_credentials"><a href="%s">Newsletter : Set credentials in settings page</a></p>',
					'asmodee-newsletter'
				),
				esc_url(admin_url('admin.php?page=asmodee_newsletter'))
			);
		}
		return false;
	}

	ob_start();
	?>
    <section class="asmodee-newsletter">
        <div class="asmodee-newsletter__container">
            <div class="column">
                <h5 class="asmodee-newsletter__title"><?php _e('Want the latest news about boardgaming and take advantage of exclusive offers?', 'asmodee-newsletter'); ?></h5>
                <p><?php _e('Subscribe to our newsletter!', 'asmodee-newsletter'); ?></p>
            </div>
            <div class="column">
				<?php echo asmodee_newsletter_display_form_subscribe(); ?>
				<?php echo asmodee_newsletter_display_form_mentions(); ?>
            </div>
        </div>
    </section>
	<?php
	$html = ob_get_clean();
	return $html;
}

function asmodee_newsletter_create_confirmation_page()
{
}
add_action('init', 'asmodee_newsletter_create_confirmation_page');

function asmodee_newsletter_get_credentials_status()
{
    $options = get_option('asmodee_newsletter_settings');
    $status =
        !empty($options)
        && is_array($options)
        && isset($options['asmodee_newsletter_client_id']) && !empty($options['asmodee_newsletter_client_id'])
        && isset($options['asmodee_newsletter_client_secret']) && !empty($options['asmodee_newsletter_client_secret'])
        ? true : false;
    // Define constants from options
    if (!empty($status) && !defined('ASMODEE_NEWSLETTER_CLIENT_ID') && !defined('ASMODEE_NEWSLETTER_CLIENT_SECRET')) {
        define('ASMODEE_NEWSLETTER_CLIENT_ID', $options['asmodee_newsletter_client_id']);
        define('ASMODEE_NEWSLETTER_CLIENT_SECRET', $options['asmodee_newsletter_client_secret']);
    }
    if (empty($status)) {
        $status = defined('ASMODEE_NEWSLETTER_CLIENT_ID') && defined('ASMODEE_NEWSLETTER_CLIENT_SECRET') ? true : false;
    }
    return $status;
}

function asmodee_newsletter_subscribe_user($email, $first_name = null, $last_name = null)
{
    if (empty($email)) {
        return;
    }

    $asmodee_newsletter_token = asmodee_newsletter_check_token();
    if (is_wp_error($asmodee_newsletter_token)) {
        return $asmodee_newsletter_token;
    }
    // Get the language set in wordpress
    $language = get_locale();
    if (!empty($language)) {
        $language = explode('_', $language);
        if (is_array($language)) {
            $language = reset($language);
        }
    } else {
        $language = 'en';
    }
    // Subscription
    $url = "https://leadgen.asmodee.net/v1/subscription";

    $curl = curl_init();
    $first_name = !empty($first_name) ? $first_name : "";
    $last_name = !empty($last_name) ? $last_name : "";
    $post_fields = json_encode(array(
        'email'         => $email,
        'language'      => $language,
        'first_name'    => !empty($first_name) ? $first_name : "",
        'last_name'     => !empty($last_name) ? $last_name : ""
    ));
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $post_fields,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $asmodee_newsletter_token
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

function asmodee_newsletter_confirm_subscription($key)
{
    if (empty($key)) {
        return;
    }
    $asmodee_newsletter_token = asmodee_newsletter_check_token();
    if (is_wp_error($asmodee_newsletter_token)) {
        return $asmodee_newsletter_token;
    }
    // Get the language set in wordpress
    $language = get_locale();
    if (!empty($language)) {
        $language = explode('_', $language);
        if (is_array($language)) {
            $language = reset($language);
        }
    } else {
        $language = 'en';
    }
    // Confirmation
    $url = "https://leadgen.asmodee.net/v1/subscription/confirmation/" . $key;

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $asmodee_newsletter_token
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

function asmodee_newsletter_check_subscription_exists($email)
{
    if (empty($email)) {
        return;
    }

    $asmodee_newsletter_token = asmodee_newsletter_check_token();
    if (is_wp_error($asmodee_newsletter_token)) {
        return $asmodee_newsletter_token;
    }
    // Get the language set in wordpress
    $language = get_locale();
    if (!empty($language)) {
        $language = explode('_', $language);
        if (is_array($language)) {
            $language = reset($language);
        }
    } else {
        $language = 'en';
    }
    // Confirmation
    $url = add_query_arg("email", urlencode($email), "https://leadgen.asmodee.net/v1/subscription");

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $asmodee_newsletter_token
        ),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}

function asmodee_newsletter_request_token()
{
    if (!asmodee_newsletter_get_credentials_status()) {
        return;
    }

    $url = "https://api.asmodee.net/main/v2/oauth/token";
    $args = array(
        'headers'  => array(
            'Content-type: application/x-www-form-urlencoded'
        ),
        'timeout'     => 60,
        'body' => array(
            'grant_type'    => 'client_credentials',
            'client_id'     => ASMODEE_NEWSLETTER_CLIENT_ID,
            'client_secret' => ASMODEE_NEWSLETTER_CLIENT_SECRET,
            'scope'         => 'public ' . ASMODEE_NEWSLETTER_CLIENT_ID . ':leadgen'
        )
    );
    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log(__FILE__ . ' ' . $error_message);
    } else {
        $datas = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($datas['access_token']) && !empty($datas['access_token'])) {
            set_transient('asmodee_newsletter_token', $datas['access_token'], $datas['expires_in']);
            return $datas['access_token'];
        } else {
            error_log(__FILE__ . ' ' . json_encode($datas));
        }
    }
}

function asmodee_newsletter_subscribe_ajax_handler()
{
    if (!wp_verify_nonce($_POST['nonce_newsletter'], 'newsletter-nonce')) {
        wp_send_json_error(['error_description' => __('Invalid nonce', 'asmodee-newsletter')]);
        wp_die();
    }

    if (isset($_POST) && is_array($_POST) && isset($_POST['email']) && is_email($_POST['email'])) {
        $response = asmodee_newsletter_subscribe_user($_POST['email']);
        $response = is_wp_error($response) ? $response->get_error_message() : json_decode($response, true);

        if (empty($response)) {
            $response = wp_send_json_success($response);
        } else {
            $response = wp_send_json_error($response);
        }
    } else {
        wp_send_json_error(['error_description' => __('Invalid email', 'asmodee-newsletter')]);
    }
    wp_die();
}
add_action('wp_ajax_asmodee_newsletter_subscribe', 'asmodee_newsletter_subscribe_ajax_handler');
add_action('wp_ajax_nopriv_asmodee_newsletter_subscribe', 'asmodee_newsletter_subscribe_ajax_handler');

function asmodee_newsletter_check_confirm_subscription()
{
    // Confirmation key has 32 characters
    if (isset($_GET) && is_array($_GET) && isset($_GET['key']) && strlen($_GET['key']) == 32) {
        $response = asmodee_newsletter_confirm_subscription($_GET['key']);
        $response = json_decode($response, true);

        if (empty($response)) {
            wp_redirect(add_query_arg('key', 'valid', home_url('confirm-subscription')));
            exit;
        } else {
            wp_redirect(add_query_arg('key', 'invalid', home_url('confirm-subscription')));
            exit;
        }
    } else {
        return;
    }
}
add_action('init', 'asmodee_newsletter_check_confirm_subscription');

function asmodee_newsletter_confirmation_page_template_redirect()
{
    if (
        isset($_GET)
        && is_array($_GET)
        && isset($_GET['key'])
        && $_SERVER['REQUEST_URI']
        && stripos($_SERVER['REQUEST_URI'], 'confirm-subscription') !== false
    ) {
        global $wp_query;
        $wp_query->is_404 = false;
        status_header(200);
        add_action('wp_head', 'asmodee_confirmation_page_redirect');
        include(dirname(__FILE__) . '/template/confirm-subscription.php');
        exit();
    }
}
add_action('template_redirect', 'asmodee_newsletter_confirmation_page_template_redirect');

function asmodee_newsletter_display_form_subscribe($print_and_play = false)
{
    // Text depending the context
    $text_button = $print_and_play ? __('Subscribe to our newsletter!', 'asmodee-newsletter') : __('Subscribe', 'asmodee-newsletter');
    return '
    <form>
        <input id="asmodee-newsletter-email" class="btn" type="email" placeholder="' . __('Your email', 'asmodee-newsletter') . '">
        <button class="newsletter-subscribe btn btn--full-m btn--black" type="submit" value="email">
            ' . $text_button . '
        </button>
        <div class="asmodee-newsletter-response"></div>
        <span class="newsletter-skip modal__close">
            ' . __('Skip', 'asmodee-newsletter') . '
        </span>
    </form>';
}

function asmodee_newsletter_display_form_mentions()
{
    $lang = !empty(substr(get_locale(), 0, 2)) ? substr(get_locale(), 0, 2) : 'en';
    switch ($lang) {
        case 'fr':
            $mentions_link = "https://account.asmodee.net/fr/legal/privacy";
            break;
        default:
            $mentions_link = "https://account.asmodee.net/en/legal/privacy";
            break;
    }
    return '
    <div class="mentions">
                    <p>
                        ' .
        __('Asmodee Digital, as the Data Controller, processes the data collected solely to manage your subscription to the newsletter.', 'asmodee-newsletter')
        . '
                    </p>
                    <p>
                        ' .
        __('You can use the unsubscribe link in the newsletter at any time.', 'asmodee-newsletter')
        . '
                    </p>
                    <p>
                        <a href="' . $mentions_link . '" target="_blank" rel="noopener">
                            ' .
        __('To find out more about your rights and how we process your personal data, please see our Privacy Policy', 'asmodee-newsletter')
        . '
                        </a>
                    </p>
            </div>
    ';
}

function asmodee_confirmation_page_redirect()
{
    echo '<meta http-equiv="refresh" content="2;url=' . home_url() . '" />';
}

add_action('admin_menu', 'asmodee_newsletter_add_admin_menu');
add_action('admin_init', 'asmodee_newsletter_settings_init');

function asmodee_newsletter_add_admin_menu()
{

    add_menu_page('Asmodee Newsletter', 'Asmodee Newsletter', 'manage_options', 'asmodee_newsletter', 'asmodee_newsletter_options_page');
}

function asmodee_newsletter_settings_init()
{
    register_setting('asmodee-newsletter', 'asmodee_newsletter_settings');

    add_settings_section(
        'asmodee_newsletter_asmodee-newsletter_section',
        __('', 'asmodee-newsletter'),
        'asmodee_newsletter_settings_section_callback',
        'asmodee-newsletter'
    );

    add_settings_field(
        'asmodee_newsletter_client_id',
        __('Client ID', 'asmodee-newsletter'),
        'asmodee_newsletter_client_id_render',
        'asmodee-newsletter',
        'asmodee_newsletter_asmodee-newsletter_section'
    );

    add_settings_field(
        'asmodee_newsletter_client_secret',
        __('Client Secret', 'asmodee-newsletter'),
        'asmodee_newsletter_client_secret_render',
        'asmodee-newsletter',
        'asmodee_newsletter_asmodee-newsletter_section'
    );
}


function asmodee_newsletter_client_id_render()
{
    $options = get_option('asmodee_newsletter_settings');
    $client_id = isset($options['asmodee_newsletter_client_id']) ? $options['asmodee_newsletter_client_id'] : '';
?>
    <input type='text' name='asmodee_newsletter_settings[asmodee_newsletter_client_id]' value='<?php echo $client_id; ?>'>
<?php

}

function asmodee_newsletter_client_secret_render()
{
    $options = get_option('asmodee_newsletter_settings');
    $client_secret = isset($options['asmodee_newsletter_client_secret']) ? $options['asmodee_newsletter_client_secret'] : '';
?>
    <input type='text' name='asmodee_newsletter_settings[asmodee_newsletter_client_secret]' value='<?php echo $client_secret; ?>' style="width:400px">
<?php

}

function asmodee_newsletter_settings_section_callback()
{
    echo __('Enter Client ID and Client Secret', 'asmodee-newsletter');
}

function asmodee_newsletter_options_page()
{
?>
    <form action='options.php' method='post'>
        <h2>Asmodee Newsletter</h2>
        <?php
        settings_fields('asmodee-newsletter');
        do_settings_sections('asmodee-newsletter');
        submit_button();
        ?>
    </form>
<?php

}

function asmodee_newsletter_language()
{
    load_plugin_textdomain('asmodee-newsletter', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'asmodee_newsletter_language');

function asmodee_newsletter_check_token()
{
    if (false === ($asmodee_newsletter_token = get_transient('asmodee_newsletter_token'))) {
        $asmodee_newsletter_token = asmodee_newsletter_request_token();
        if (!empty($asmodee_newsletter_token)) {
            set_transient('asmodee_newsletter_token', $asmodee_newsletter_token);
        } else {
            return new WP_Error('token', __('Can\'t set a token', 'asmodee-newsletter'));
        }
    }
    return $asmodee_newsletter_token;
}

add_action('init', 'asmodee_newsletter_shortcode');

function asmodee_newsletter_shortcode()
{
    add_shortcode('asmodee_newsletter', 'asmodee_newsletter_display_subscription_block');
}

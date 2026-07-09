<?php

/**
 * Plugin Name: Custom Captcha Field for Fluent Forms
 * Description: Adds a custom captcha field to Fluent Forms — image, math or question/answer challenges. No external services.
 * Version: 2.0.0
 * Author: Lukman Nakib
 * Author URI: https://nkb-bd.github.io/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: custom-captcha-field-for-fluent-forms
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: fluentform
 */

defined('ABSPATH') or die;

define('FF_CUSTOM_CAPTCHA_DIR_PATH', plugin_dir_path(__FILE__));
define('FF_CUSTOM_CAPTCHA_URL', plugin_dir_url(__FILE__));
define('FF_CUSTOM_CAPTCHA_VERSION', '2.0.0');

add_action('plugins_loaded', function () {
    (new FFC_CaptchaAddon())->boot();
});

class FFC_CaptchaAddon
{
    public function boot()
    {
        if (!defined('FLUENTFORM')) {
            return $this->injectDependency();
        }

        include FF_CUSTOM_CAPTCHA_DIR_PATH . 'Bootstrap.php';
        new FFC_CaptchaField();
    }

    protected function injectDependency()
    {
        add_action('admin_notices', function () {
            $pluginInfo = $this->getFluentFormInstallationDetails();

            $installUrlText = __('Click here to install Fluent Forms', 'custom-captcha-field-for-fluent-forms');

            if ($pluginInfo->action == 'activate') {
                $installUrlText = __('Click here to activate Fluent Forms', 'custom-captcha-field-for-fluent-forms');
            }

            $message = __('Custom Captcha Field for Fluent Forms requires the Fluent Forms plugin. ', 'custom-captcha-field-for-fluent-forms');
            $message .= '<b><a href="' . $pluginInfo->url . '">' . $installUrlText . '</a></b>';

            printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
        });
    }

    protected function getFluentFormInstallationDetails()
    {
        $activation = (object) [
            'action' => 'install',
            'url'    => ''
        ];

        $allPlugins = get_plugins();

        if (isset($allPlugins['fluentform/fluentform.php'])) {
            $url = wp_nonce_url(
                self_admin_url('plugins.php?action=activate&plugin=fluentform/fluentform.php'),
                'activate-plugin_fluentform/fluentform.php'
            );

            $activation->action = 'activate';
        } else {
            $url = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=fluentform'),
                'install-plugin_fluentform'
            );
        }

        $activation->url = $url;
        return $activation;
    }
}

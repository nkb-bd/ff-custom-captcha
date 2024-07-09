<?php

/**
Plugin Name: Custom Captcha Field for Fluent Forms
Description: Custom Captcha - 3 types of captcha
Version: 0.1
Author: Lukman Nakib
Author URI: https://nkb-bd.github.io/
License: GPLv2 or later
Text Domain: ff_custom_recaptcha
**/


defined('ABSPATH') or die;

define('FF_CUSTOM_CAPTCHA_DIR_PATH', plugin_dir_path(__FILE__));

add_action('plugins_loaded', function () {
    (new FFRecaptcha())->boot();
});

class FFRecaptcha
{
    public function boot()
    {
        if (!defined('FLUENTFORM')) {
            return $this->injectDependency();
        }
        
        $this->includeFiles();
        
       
    }
    
    protected function includeFiles()
    {
        include FF_CUSTOM_CAPTCHA_DIR_PATH . '/Bootstrap.php';
        new FFCustomField();
    }
    /**
     * Notify the user about the FluentForm dependency and instructs to install it.
     */
    protected function injectDependency()
    {
        add_action('admin_notices', function () {
            $pluginInfo = $this->getFluentFormInstallationDetails();
            
            $class = 'notice notice-error';
            
            $install_url_text = __('Click Here to Install the Plugin', 'ff_custom_recaptcha');
            
            if ($pluginInfo->action == 'activate') {
                $install_url_text = __('Click Here to Activate the Plugin', 'ff_custom_recaptcha');
            }
            
            $message = __('FluentForm pdf Add-On Requires Fluent Forms Plugin, ', 'ff_custom_recaptcha');
            $message .= '<b><a href="' .$pluginInfo->url . '">' . $install_url_text . '</a></b>';
            
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
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
            $api = (object) ['slug' => 'fluentform'];
            
            $url = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=' . $api->slug),
                'install-plugin_' . $api->slug
            );
        }
        $activation->url = $url;
        return $activation;
    }
}




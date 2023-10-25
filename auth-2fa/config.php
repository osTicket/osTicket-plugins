<?php

require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR.'class.forms.php';

class Auth2FAConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('2fa-auth');
    }

    function getOptions() {
        return array(
            'custom_issuer' => new TextboxField(array(
                'label' => __('Issuer'),
                'required' => false,
                'configuration' => array('size'=>40),
                'hint' => __('Customize the Issuer shown in your Authenticator app after scanning a QR Code.'),
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        global $msg;
        if (!$errors)
            $msg = __('Configuration updated successfully');
        return true;
    }
}

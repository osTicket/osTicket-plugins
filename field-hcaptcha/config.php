<?php

class hCaptchaConfig extends PluginConfig {
    function getOptions() {
        return array(
            '_' => new SectionBreakField(array(
                'label' => 'hCaptcha Configuration',
                'hint' => 'Requires separate registration for a key set'
            )),
            'siteKey' => new TextboxField(array(
                'required' => true,
                'configuration'=>array('length'=>36, 'size'=>40),
                'label' => 'Site Key',
            )),
            'secretKey' => new TextboxField(array(
                'widget' => 'PasswordWidget',
                'required' => false,
                'configuration'=>array('length'=>42, 'size'=>40),
                'label' => 'Secret Key',
            )),
        );
    }

    function pre_save($config, &$errors) {
        // Todo: verify key

        if (!function_exists('curl_init')) {
            Messages::error('CURL extension is required');
            return false;
        }

        global $msg;
        if (!$errors)
            $msg = 'Successfully updated hCaptcha settings';

        return true;
    }
}

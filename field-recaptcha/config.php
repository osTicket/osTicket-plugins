<?php

class reCaptchaConfig extends PluginConfig {
    function getOptions() {
        return array(
            '_' => new SectionBreakField(array(
                'label' => 'reCaptcha Configuration',
                'hint' => 'Requires separate registration for a key set'
            )),
            'public' => new TextboxField(array(
                'required' => true,
                'configuration'=>array('length'=>64, 'size'=>40),
                'label' => 'Public Key',
            )),
            'private' => new TextboxField(array(
                'widget' => 'PasswordWidget',
                'required' => false,
                'configuration'=>array('length'=>64, 'size'=>40),
                'label' => 'Private Key',
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
            $msg = 'Successfully updated reCAPTCHA settings';

        return true;
    }
}

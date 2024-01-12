<?php
require_once(INCLUDE_DIR.'/class.forms.php');

class IpAuthConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-ip');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'auth' => new SectionBreakField(array(
                'label' => $__('Authentication Modes'),
                'hint' => $__('Authentication mode for clients. Clients
                    can be identified via their IP address.'),
            )),
            'auth-client' => new BooleanField(array(
                'label' => $__('Client Authentication'),
                'default' => false,
                'configuration' => array(
                    'desc' => $__('Enable IP authentication of clients')
                )
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        global $msg;

        list($__, $_N) = self::translate();
        if (!$errors)
            $msg = $__('Configuration updated successfully');

        return true;
    }
}

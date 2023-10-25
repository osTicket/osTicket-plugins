<?php
require_once(INCLUDE_DIR.'/class.forms.php');

class PassthruAuthConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-passthru');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'auth' => new SectionBreakField(array(
                'label' => $__('Authentication Modes'),
                'hint' => $__('Authentication modes for clients and staff
                    members can be enabled independently. Client discovery
                    can be supported via a separate backend (such as LDAP)'),
            )),
            'auth-staff' => new BooleanField(array(
                'label' => $__('Staff Authentication'),
                'default' => true,
                'configuration' => array(
                    'desc' => $__('Enable authentication of staff members')
                )
            )),
            'auth-client' => new BooleanField(array(
                'label' => $__('Client Authentication'),
                'default' => false,
                'configuration' => array(
                    'desc' => $__('Enable authentication and discovery of clients')
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

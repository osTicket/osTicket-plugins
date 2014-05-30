<?php
require_once(INCLUDE_DIR.'/class.forms.php');

class PassthruAuthConfig extends PluginConfig {
    function getOptions() {
        return array(
            'auth' => new SectionBreakField(array(
                'label' => 'Authentication Modes',
                'hint' => 'Authentication modes for clients and staff
                    members can be enabled independently. Client discovery
                    can be supported via a separate backend (such as LDAP)',
            )),
            'auth-staff' => new BooleanField(array(
                'label' => 'Staff Authentication',
                'default' => true,
                'configuration' => array(
                    'desc' => 'Enable authentication of staff members'
                )
            )),
            'auth-client' => new BooleanField(array(
                'label' => 'Client Authentication',
                'default' => false,
                'configuration' => array(
                    'desc' => 'Enable authentication and discovery of clients'
                )
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        global $msg;

        if (!$errors)
            $msg = 'Configuration updated successfully';

        return true;
    }
}

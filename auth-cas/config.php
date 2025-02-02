<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class CasPluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-cas');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        $modes = new ChoiceField(array(
            'label' => $__('Authentication'),
            'default' => "disabled",
            'choices' => array(
                'disabled' => $__('Disabled'),
                'staff' => $__('Agents Only'),
                'client' => $__('Clients Only'),
                'all' => $__('Agents and Clients'),
            ),
        ));
        return array(
            'cas' => new SectionBreakField(array(
                'label' => $__('CAS Authentication'),
            )),
            'cas-hostname' => new TextboxField(array(
                'label' => $__('CAS Server Hostname'),
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'cas-port' => new TextboxField(array(
                'label' => $__('CAS Server Port'),
                'configuration' => array('size'=>10, 'length'=>8),
            )),
            'cas-context' => new TextboxField(array(
                'label' => $__('CAS Server Context'),
                'configuration' => array('size'=>60, 'length'=>100),
                'hint' => $__('This value is "/cas" for most installs.'),
            )),
            'cas-ca-cert-path' => new TextboxField(array(
                'label' => $__('CAS CA Cert Path'),
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'cas-at-domain' => new TextboxField(array(
                'label' => $__('CAS e-mail suffix'),
                'configuration' => array('size'=>60, 'length'=>100),
                'hint' => $__('Use this field if your CAS server does not
                    report an e-mail attribute. ex: "@domain.tld"'),
            )),
            'cas-name-attribute-key' => new TextboxField(array(
                'label' => $__('CAS name attribute key'),
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'cas-email-attribute-key' => new TextboxField(array(
                'label' => $__('CAS email attribute key'),
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'cas-enabled' => clone $modes,
        );
    }
}

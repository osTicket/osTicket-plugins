<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class CasPluginConfig extends PluginConfig {
    function getOptions() {
        $modes = new ChoiceField(array(
            'label' => 'Authenticate',
            'choices' => array(
                '0' => 'Disabled',
                'staff' => 'Agents Only',
                'client' => 'Clients Only',
                'all' => 'Agents and Clients',
            ),
        ));
        return array(
            'cas' => new SectionBreakField(array(
                'label' => 'CAS Authentication',
            )),
            'cas-hostname' => new TextboxField(array(
                'label' => 'CAS Server Hostname',
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'cas-port' => new TextboxField(array(
                'label' => 'CAS Server Port',
                'configuration' => array('size'=>10, 'length'=>8),
            )),
            'cas-context' => new TextboxField(array(
                'label' => 'CAS Server Context',
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'cas-ca-cert-path' => new TextboxField(array(
                'label' => 'CAS CA Cert Path',
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'cas-at-domain' => new TextboxField(array(
                'label' => 'CAS e-mail suffix (ex. @domain.tld)',
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'cas-name-attribute-key' => new TextboxField(array(
                'label' => 'CAS name attribute key',
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'cas-email-attribute-key' => new TextboxField(array(
                'label' => 'CAS email attribute key',
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'cas-enabled' => clone $modes,
        );
    }
}

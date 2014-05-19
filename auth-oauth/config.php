<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class OauthPluginConfig extends PluginConfig {
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
            'google' => new SectionBreakField(array(
                'label' => 'Google+ Authentication',
            )),
            'g-client-id' => new TextboxField(array(
                'label' => 'Client ID',
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'g-client-secret' => new TextboxField(array(
                'label' => 'Client Secret',
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'g-enabled' => clone $modes,
        );
    }
}

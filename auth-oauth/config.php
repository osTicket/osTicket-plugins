<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class OauthPluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-oauth');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        $modes = new ChoiceField(array(
            'label' => $__('Authentication'),
            'choices' => array(
                '0' => $__('Disabled'),
                'staff' => $__('Agents Only'),
                'client' => $__('Clients Only'),
                'all' => $__('Agents and Clients'),
            ),
        ));
        return array(
            'google' => new SectionBreakField(array(
                'label' => $__('Google+ Authentication'),
            )),
            'g-client-id' => new TextboxField(array(
                'label' => $__('Client ID'),
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'g-client-secret' => new TextboxField(array(
                'label' => $__('Client Secret'),
                'configuration' => array('size'=>60, 'length'=>100),
            )),
            'g-enabled' => clone $modes,
            'generic' => new SectionBreakField(array(
                'label' => $__('Generic OAuth2'),
            )),
            'generic-servicename' => new TextboxField(array(
                'label' => $__('Service name'),
                'configuration' => array('size'=>60, 'length'=>200),
            )),
            'generic-client-id' => new TextboxField(array(
                'label' => $__('Client ID'),
                'configuration' => array('size'=>60, 'length'=>200),
            )),
            'generic-client-secret' => new TextboxField(array(
                'label' => $__('Client Secret'),
                'configuration' => array('size'=>60, 'length'=>200),
            )),
            'generic-authorize-url' => new TextboxField(array(
                'label' => $__('Authorize URL'),
                'configuration' => array('size'=>60, 'length'=>200),
            )),
            'generic-token-url' => new TextboxField(array(
                'label' => $__('Token URL'),
                'configuration' => array('size'=>60, 'length'=>200),
            )),
            'generic-userinfo-url' => new TextboxField(array(
                'label' => $__('User JSON URL'),
                'configuration' => array('size'=>60, 'length'=>200),
            )),
            'generic-scope' => new TextboxField(array(
                'label' => $__('Scope'),
                'configuration' => array('size'=>60, 'length'=>200),
            )),
            'generic-enabled' => clone $modes,
        );
    }
}

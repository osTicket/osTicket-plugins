<?php

namespace ImapAuth;

use ChoiceField;
use SectionBreakField;
use TextboxField;
use Plugin;
use PluginConfig;

class Config extends PluginConfig
{
    /**
     * Provide compatibility function for versions of osTicket prior to translation support (v1.9.4)
     */
    public function translate()
    {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function ($x) {
                    return $x;
                },
                function ($x, $y, $n) {
                    return $n != 1 ? $y : $x;
                },
            );
        }
        return Plugin::translate('auth-url');
    }

    public function getOptions()
    {
        list($trans) = $this->translate();

        return array(

            'plugin-header' => new SectionBreakField(
                array(
                    'label' => $trans('Plugin Settings'),
                )
            ),

            'enabled-for' => new ChoiceField(
                array(
                    'label' => $trans('Authentication'),
                    'choices' => array(
                        '0' => $trans('Disabled'),
                        'staff' => $trans('Agents (Staff) Only'),
                        //'client' => $trans('Clients Only'),
                        //'all' => $trans('Agents and Clients'),
                    ),
                )
            ),

            'imap-header' => new SectionBreakField(
                array(
                    'label' => $trans('IMAP Settings'),
                )
            ),

            'imap-server' => new TextboxField(
                array(
                    'label' => $trans('IMAP server'),
                    'configuration' => array(
                        'size' => 60,
                        'length' => 200
                    ),
                )
            ),

            'method' => new ChoiceField(
                array(
                    'label' => $trans('Method'),
                    'choices' => array(
                        'imap4' => 'IMAP4',
                        'pop3' => 'POP3',
                    ),
                )
            ),

            'tls-ssl' => new ChoiceField(
                array(
                    'label' => $trans('TLS/SSL'),
                    'choices' => array(
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                    ),
                )
            ),

        );

    }
}

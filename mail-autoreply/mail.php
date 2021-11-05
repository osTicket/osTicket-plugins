<?php

class MailFilterPlugin extends Plugin {
    var $config_class = 'MailFilterConfig';

    function bootstrap() {
        Signal::connect('mail.processed', array($this, 'filterMail'));
    }

    function filterMail($sender, &$info) {
        $texts = array($info['subject'], $info['message']->getSearchable());
        $config = $this->getConfig();

        foreach (explode("\n", $config->get('auto-replies')) as $ex) {
            $pattern = str_replace('/', '\/', trim($ex));
            foreach ($texts as $text) {
                if (preg_match("/$pattern/imu", $text)) {
                    $info['flags']['auto-reply'] = true;
                    // No further processing necessary
                    return;
                }
            }
        }
    }
}

class MailFilterConfig extends PluginConfig {
    function getOptions() {
        return array(
            'info' => new SectionBreakField(array(
                'label' => 'Filter Search Configurations',
            )),
            'auto-replies' => new TextareaField(array(
                'label' => 'Auto-Reply Keywords',
                'hint' => 'Mail that matches one of these expressions will
                have automated responses supressed. One match expression per
                line. Both the subject and body are considered. Use regular
                expression syntax for wildcards.',
                'configuration' => array(
                    'html' => false, 'rows' => 10, 'cols' => 60
                ),
                'validators' => array(
                function($self, $val) {
                    foreach (explode("\n", $val) as $ex) {
                        $p = str_replace('/', '\/', trim($ex));
                        if (@preg_match("/$p/imu", null) === false)
                            $self->addError(trim($ex)
                                . ': Invalid Regular Expression');
                    }
                }),
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        global $msg;

        if (!$errors)
            $msg = 'Successfully updated mail filter configuration';

        return true;
    }
}

<?php
require_once(INCLUDE_DIR.'class.forms.php');
class AuditConfig extends PluginConfig {
    function getOptions() {
        return array(
            'show_view_audits' => new BooleanField(array(
                'label' => __('Show View Audits'),
                'default' => true,
                'configuration' => array(
                    'desc' => __('Show Audit Logs for when a User or Agent views Tickets')
                )
            )),
        );
    }
    function pre_save(&$config, &$errors) {
        global $msg;
        if (!$errors)
            $msg = __('Configuration updated successfully');
        return true;
    }
}

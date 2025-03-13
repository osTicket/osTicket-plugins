<?php
class TEA_RemoveThreadEntry extends ThreadEntryAction {
    static $id = 'delete';
    static $name = /* trans */ 'Delete';
    static $icon = 'remove';

    static $plugin_config;

    static function setConfig(PluginConfig $config) {
        static::$plugin_config = $config->getInfo();
    }

    function getConfig() {
        return static::$plugin_config;
    }

    function isVisible() {
        $config = $this->getConfig();

        // Removal of messages is not allowed
        if ($this->entry->type == 'M')
            return false;

        // Configuration indicates if removal of responses is allowed
        if ($this->entry->type == 'R' && !@$config['responses'])
            return false;

        // Can't remove system posts
        return ($this->entry->staff_id || $this->entry->user_id)
            && $this->isEnabled();
    }

    function isEnabled() {
        global $thisstaff;

        // You have to be an admin *and* have access to the ticket/task
        $T = $this->entry->getThread()->getObject();
        return $thisstaff->isAdmin()
            && $T->checkStaffPerm($thisstaff);
    }

    function getJsStub() {
        return sprintf(<<<JS
var url = '%s';
$.dialog(url, [201], function(xhr, resp) {
  var json = JSON.parse(resp);
  if (!json || !json.thread_id)
    return;
  $('#thread-entry-'+json.thread_id)
    .remove();
});
JS
        , $this->getAjaxUrl());
    }

    function trigger() {
        switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            return $this->trigger__get();
        case 'POST':
            return $this->trigger__post();
        }
    }

    protected function trigger__get() {
        global $cfg, $thisstaff;

        $poster = $this->entry->getStaff();
        $action = str_replace('ajax.php/','#', $this->getAjaxUrl());

        include 'templates/thread-entry-delete.tmpl.php';
    }

    protected function trigger__post() {
        $config = $this->getConfig();

        if ($this->entry->type == 'M') {
            Messages::error(__('Deletion of messages is not supported'));
        }
        if ($this->entry->type == 'R' && !@$config['responses']) {
            Messages::error(__('Deletion of responses is disabled'));
        }
        elseif ($this->entry->delete()) {
            Http::response('201', JsonDataEncoder::encode(array(
                'thread_id' => $this->entry->id,
            )));
        }
        return $this->trigger__get();
    }
}

class TEA_RemovePlugin
extends Plugin {
    var $config_class = 'TEA_RemovePluginConfig';

    function bootstrap() {
        TEA_RemoveThreadEntry::setConfig($this->getConfig());
        ThreadEntry::registerAction(/* trans */ 'Manage', 'TEA_RemoveThreadEntry');
    }
}

class TEA_RemovePluginConfig
extends PluginConfig {
    function getOptions() {
        return array(
            'responses' => new BooleanField(array(
                'label' => 'Allow removal of Responses',
                'default' => false,
                'hint' => 'Not recommended. Removing responses which were emailed to your users could cause significant confusion',
                'configuration' => array(
                    'desc' => 'Allow removal of Responses',
                ),
            )),
        );
    }
}

<?php

require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');

class CasAuthPlugin extends Plugin {
    var $config_class = "CasPluginConfig";

    function bootstrap() {
        $config = $this->getConfig();

        $enabled = $config->get('cas-enabled');
        if (in_array($enabled, array('all', 'staff'))) {
            require_once('cas.php');
            StaffAuthenticationBackend::register(
                new CasStaffAuthBackend($this->getConfig()));
        }
        if (in_array($enabled, array('all', 'client'))) {
            require_once('cas.php');
            UserAuthenticationBackend::register(
                new CasClientAuthBackend($this->getConfig()));
        }
    }
}

require_once(INCLUDE_DIR.'UniversalClassLoader.php');
use Symfony\Component\ClassLoader\UniversalClassLoader_osTicket;
$loader = new UniversalClassLoader_osTicket();
$loader->registerNamespaceFallbacks(array(
    dirname(__file__).'/lib'));
$loader->register();

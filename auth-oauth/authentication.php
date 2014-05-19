<?php

require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');

class OauthAuthPlugin extends Plugin {
    var $config_class = "OauthPluginConfig";

    function bootstrap() {
        $config = $this->getConfig();

        # ----- Google Plus ---------------------
        $google = $config->get('g-enabled');
        if (in_array($google, array('all', 'staff'))) {
            require_once('google.php');
            StaffAuthenticationBackend::register(
                new GoogleStaffAuthBackend($this->getConfig()));
        }
        if (in_array($google, array('all', 'client'))) {
            require_once('google.php');
            UserAuthenticationBackend::register(
                new GoogleClientAuthBackend($this->getConfig()));
        }
    }
}

require_once(INCLUDE_DIR.'UniversalClassLoader.php');
use Symfony\Component\ClassLoader\UniversalClassLoader_osTicket;
$loader = new UniversalClassLoader_osTicket();
$loader->registerNamespaceFallbacks(array(
    dirname(__file__).'/lib'));
$loader->register();

<?php

require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');
require_once('class.auth2fa.php');

class Auth2FAPlugin extends Plugin {
    var $config_class = "Auth2FAConfig";

    function bootstrap() {
        $config = $this->getConfig();
        if ($config->get('custom_issuer'))
            Auth2FABackend::$custom_issuer = $config->get('custom_issuer');

        TwoFactorAuthenticationBackend::register('Auth2FABackend');
    }

    function enable() {
        return parent::enable();
    }

    function uninstall(&$errors) {
        $errors = array();

        self::disable();

        return parent::uninstall($errors);
    }

    function disable() {
        $default2fas = ConfigItem::getConfigsByNamespace(false, 'default_2fa', Auth2FABackend::$id);
        foreach($default2fas as $default2fa)
            $default2fa->delete();

        $tokens = ConfigItem::getConfigsByNamespace(false, Auth2FABackend::$id);
        foreach($tokens as $token)
            $token->delete();

        return parent::disable();
    }
}

require_once(INCLUDE_DIR.'UniversalClassLoader.php');
use Symfony\Component\ClassLoader\UniversalClassLoader_osTicket;
$loader = new UniversalClassLoader_osTicket();
$loader->registerNamespaceFallbacks(array(
    dirname(__file__).'/lib'));
$loader->register();

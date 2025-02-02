<?php
require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');
class OAuth2Plugin extends Plugin {
    var $config_class = "OAuth2Config";
    // config instance
    var $config;
    // default scopes
    static $default_scopes = ['profile', 'email'];

    static function default_scopes() {
        return implode(',', self::$default_scopes ?: []);
    }

    static function callback_url() {
        return osTicket::get_base_url().'api/auth/oauth2';
    }

    /**
     * Function: registerEndpoint
     *
     * Called to attach SP endpoints to the api and provide callback for
     * IdP response processing.
     *
     */
    private static function registerEndpoint($url='^/auth/oauth2', $id=null) {
        Signal::connect('api', function ($dispatcher) use($url, $id) {
            $dispatcher->append(
                url_get("$url", function () use($id) {
                    $id = $id ?: $_SESSION['ext:bk:id'];
                    if (isset($_GET['code'])
                            && isset($_GET['state'])
                            && ($bk=self::getAuthBackend($id))) {
                        $bk->callback($_GET, $id);
                    }
                    // Authentication failed or downstream failed to redirect user.
                    Http::redirect(ROOT_PATH);
                })
            );
        });
    }

    private static function registerAuthBackends(PluginConfig $config) {

        $target = $config->get('auth_target') ?: 'none';
        if (in_array($target, array('all', 'agents'))) {
            StaffAuthenticationBackend::register(
                new OAuth2StaffAuthBackend($config));
        }
        if (in_array($target, array('all', 'users'))) {
            UserAuthenticationBackend::register(
                new OAuth2UserAuthBackend($config));
        }
    }

    private static function getAuthBackend($id) {
        // Authentication backends
        $bk = AuthenticationBackend::lookupBackend($id);
        if ($bk instanceof OAuth2AuthBackend)
            return $bk;
        // OAuth2 Authorization backends
        if (($bk=OAuth2AuthorizationBackend::getBackend($id)))
            return $bk;
        // OAuth2 Authentication backends
        if (($bk=OAuth2AuthenticationBackend::getBackend($id)))
            return $bk;
    }

    public function getNewInstanceOptions() {
        $newOptions = [];
        foreach (OAuth2AuthenticationBackend::allRegistered() as $bk) {
             $newOptions[] = [
                 'name' => $bk::$name,
                 'href' => sprintf('plugins.php?id=%d&provider=%s&a=add-instance#instances',
                         $this->getId(), $bk::$id),
                 'class' => '',
                 'icon' => $bk::$icon,
             ];
        }
        return $newOptions;
    }

    public function getNewInstanceDefaults($options) {
        $defaults = ['auth_type' => 'auth'];
        if (isset($options['provider'])
                && ($id=$options['provider'])
                && (($bk=OAuth2AuthenticationBackend::getBackend($id))))
            $defaults += $bk->getDefaults();

        return $defaults;
    }

    public function init() {
        // Register API Endpoint
        self::registerEndpoint();
        // Register Oauth2 Authorization Providers
        OAuth2ProviderBackend::registerProviders([
                'plugin_id' => $this->getId()]);
    }

    public function bootstrap() {
        // Get sideloaded instance config - this is neccessary for backwards
        // compatibility before multi-instance support
        $config = $this->getConfig();
        // Only register Authentication backends Authorization Backends are
        // done on-demand via Email Account interface
        if ($config && $config->isAuthen())
            self::registerAuthBackends($config);
    }
}


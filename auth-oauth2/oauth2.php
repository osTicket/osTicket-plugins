<?php
/*********************************************************************
    class.oauth2.php

    Oauth2 authentication backends using League\OAuth2 toolkit

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2022 osTicket
    https://osticket.com

    Credit:
    * https://github.com/thephpleague/oauth2-client
    * Interwebz

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
include_once 'auth.php';

use League\OAuth2\Client\Provider\GenericProvider;

/**
 * OAuth2AuthBackend
 *
 * Interface class for OAuth2 authentication backends
 *
 * Not entirely necessary but used to decorate classes to make it easy to
 * test if it's an instance of desired backend.
 *
 */

interface OAuth2AuthBackend {

    /**
     * Function: handleCallback
     *
     * Called when we receive OAuth2 auth code
     */
    function callBack($resp, $ref='');

    /**
     * Function: redirect util
     *
     * Called to redirect user to either internal or external urls.
     */
    function redirectTo($url);
    function setState($state);
    function getState();
    function resetState();
    function getAccessToken($code);
}

/**
 * OAuth2AuthenticationTrait
 *
 * Trait class with the core OAuth2 authentication functions used by
 * downstream backends.
 *
 */
trait OAuth2AuthenticationTrait {
    // OAuth2 Id Provider
    private $provider;
    // debug mode flag
    private $debug = false;

    // SESSION store for data like AuthNRequestID
    private $session;
    // Configuration store
    protected $config;
    // Supported attributes mapped to scopes - hard coded for now
    private $attributes = ['username', 'givenname', 'surname', 'email'];

    function __construct($config, $provider=null) {
        $this->config = $config;
        // Session data stash for backends
        $this->session = &$_SESSION[':oauth'][$this->getId()];
        if ($provider instanceof OAuth2AuthorizationBackend)
            $this->provider = $provider;
        else
            $this->provider = new GenericOauth2Provider();

        // Get Oauth Client based on provider
        $this->client = $this->provider->getClient($config);
    }

    function callback($resp, $ref=null) {
        try {
            if ($this->getState() == $resp['state']
                    && ($token=$this->getAccessToken($resp['code']))
                    && ($owner=$this->client->getResourceOwner($token))
                    && ($attrs=$this->mapAttributes($owner->toArray()))) {
                $this->resetState();
                // Attempt to signIn the user based on returned attributes
                $result = $this->signIn($attrs);
                if ($result instanceof AuthenticatedUser) {
                    // SignIn successful - login the user and redirect to
                    // desired panel
                    if ($this->login($result, $this))
                        $this->onSignIn();
                }
            }
        } catch (Exception $ex) {
            return false;
        }
    }

    function getId() {
        return static::$id;
    }

    function getName() {
        return $this->config->getName();
    }

    function getServiceName() {
        return $this->config->getServiceName();
    }

    public function setState($state) {
        $this->session['AuthState'] =  $state;
    }

    public function resetState() {
        $this->setState('');
    }

    public function getState() {
        return  $this->session['AuthState'];
    }

    private function mapAttributes(array $result) {
        // Mapout the supported attributes only
        $attributes = array();
        $result = array_change_key_case($result, CASE_LOWER);
        foreach ($this->attributes as $attr) {
            if (!($key=strtolower($this->config->getAttributeFor($attr))))
                continue;
            $attributes[$attr] = $result[$key] ?: null;
        }
        // Use email as username if none is provided or vice versa!
        if (!isset($attributes['username']) && isset($attributes['email']))
            $attributes['username'] = $attributes['email'];
        elseif (!isset($attributes['email'])
                && isset($attributes['username'])
                && Validator::is_email($attributes['username']))
            $attributes['email'] = $attributes['username'];

        return $attributes;
    }

    public function getAccessToken($code) {
        return $this->client->getAccessToken('authorization_code',
                ['code' => $code]);
    }

    public function refreshAccessToken($refreshToken) {
        return $this->client->getAccessToken('refresh_token',
                ['refresh_token' => $refreshToken]);
    }

    public function triggerAuth() {
        parent::triggerAuth();
        // Regenerate OAuth2 auth request
        $authUrl = $this->client->getAuthorizationUrl();
        // Get the state generated for you and store it to the session.
        $this->setState($this->client->getState());
        $this->redirectTo($authUrl);
    }


    function redirectTo($url) {
        // No cache redirect
        header('Pragma: no-cache');
        header('Cache-Control: no-cache, must-revalidate');
        Http::redirect($url);
    }

    static function signOut($user) {
        parent::signOut($user);
    }

    abstract function signIn($attrs);
    abstract function onSignIn();
}

/**
 * OAuth2StaffAuthBackend
 *
 * Provides OAuth2 authentication backend for agents
 */
class OAuth2StaffAuthBackend extends ExternalStaffAuthenticationBackend
implements OAuth2AuthBackend  {
    use OAuth2AuthenticationTrait;
    static $id = "oauth2.agent";
    static $name = "OAuth2";
    static $service_name = "OAuth2";

    private function signIn($attrs) {
        if ($attrs && isset($attrs['username'])) {
            if (($staff = StaffSession::lookup($attrs['username']))
                    && $staff->getId()) {
                // Older versions
                if (!$staff instanceof StaffSession)
                    $staff = new StaffSession($staff->getId());

                return $staff;
            } else {
                $_SESSION['_staff']['auth']['msg'] = sprintf('%s (%s)',
                        'Have your administrator create a local account',
                        Format::htmlchars($attrs['username']));
            }
        }
    }

    private function onSignIn() {
        $this->redirectTo($_SESSION['_staff']['auth']['dest'] ?: osTicket::get_base_url().'scp/');
    }

}


/**
 * OAuth2UserAuthBackend
 *
 * Provides OAuth2 authentication backend for users
 */
class OAuth2UserAuthBackend extends ExternalUserAuthenticationBackend
implements OAuth2AuthBackend {
    use OAuth2AuthenticationTrait;
    static $id = "oauth2.user";
    static $name = "OAuth2";
    static $service_name = "OAuth2";

    private function signIn($attrs) {
        if ($attrs && isset($attrs['username'])) {
            if (($acct = ClientAccount::lookupByUsername($attrs['username']))
                    && $acct->getId())
                return new ClientSession(new EndUser($acct->getUser()));
            // Auto-register user if possible
            $email = $attrs['email'] ?: $attrs['username'];
            if (Validator::is_email($email)) {
                if (!($name = trim(sprintf('%s %s', $attrs['givenname'], $attrs['surname']))))
                    $name = $attrs['username'];
                $info = ['email' => $email, 'name' => $name];
                $req = new ClientCreateRequest($this, $attrs['username'], $info);
                return $req->attemptAutoRegister();
            }
        }
    }

    private function onSignIn() {
        $this->redirectTo($_SESSION['_client']['auth']['dest'] ?: osTicket::get_base_url());
    }
}

/*
 * OAuth Email Auth Backend
 *
 */
class OAuth2EmailAuthBackend implements OAuth2AuthBackend  {
    use OAuth2AuthenticationTrait;
    static $id = "oauth2.emailautho";
    private $options;
    public  $account;

    const ERR_UNKNOWN = 0;
    const ERR_EMAIL_ATTR = 1;
    const ERR_EMAIL_MISMATCH = 2;
    const ERR_REFRESH_TOKEN = 3;

    private function isStrict() {
        // TODO: Require osTicket v1.18 and delegate strict checking to
        // the email account ($this->account->isStrict())
        // For now the flag is being set via the provider by overloading
        // backend id
        return ($this->provider && $this->provider->isStrict());
    }

    function getEmailId() {
        return $this->account->getEmailId();
    }

    function getEmail() {
        return $this->account->getEmail();
    }

    function getEmailAddress() {
        return $this->account->email->getEmail();
    }

    private function updateCredentials($info, &$errors) {
        return $this->account->updateCredentials(
                $this->provider->getId(), $info, $errors);
    }

    public function callback($resp, $ref=null) {
        $errors = [];
        $err = sprintf('%s_auth_bk', $this->account->getType());
        try {
            if ($this->getState() == $resp['state']
                    && ($token=$this->getAccessToken($resp['code']))
                    && ($owner=$this->client->getResourceOwner($token))
                    && ($attrs=$this->mapAttributes($owner->toArray()))) {
                $this->resetState();
                $info =  [
                    'access_token' => $token->getToken(),
                    'refresh_token' => $token->getRefreshToken(),
                    'expires' => $token->getExpires(),
                    'resource_owner_id' => $token->getResourceOwnerId(),
                    'resource_owner_email' => $attrs['email'],
                ];

                if (!isset($attrs['email']))
                    $errors[$err] = $this->error_msg(self::ERR_EMAIL_ATTR, $attrs);
                elseif (!$info['refresh_token'])
                    $errors[$err] = $this->error_msg(self::ERR_REFRESH_TOKEN);
                elseif (!$this->signIn($attrs) && $this->isStrict()) {
                    // On strict mode email mismatch is an error
                    // TODO: Move Strict checking to osTiket core on
                    // credentials update.
                    $errors[$err] = $this->error_msg(self::ERR_EMAIL_MISMATCH, $attrs);
                }
                // Update the credentials if no validation errors
                if (!$errors
                        && !$this->updateCredentials($info, $errors)
                        && !isset($errors[$err]))
                     $errors[$err] = $this->error_msg(self::ERR_UNKNOWN);
            }
        } catch (Exception $ex) {
            $errors[$err] =  $ex->getMessage();
        }

        // stash the results before redirecting
        $email = $this->getEmail();
        // TODO: check if email implements StashableTrait
        if ($errors)
            $email->stash('errors', $errors);
        else
            $email->stash('notice', sprintf('%s: %s',
                        $this->account->getType(),
                        __('OAuth2 Authorization Successful')
                        ));
        // redirect back to email page
        $this->onSignIn();
    }

    public function triggerAuth() {
        // Regenerate OAuth2 auth request
        $urlOptions = $this->provider->getUrlOptions() ?: [];
        $authUrl = $this->client->getAuthorizationUrl($urlOptions);
        // Get the state generated for you and store it to the session.
        $this->setState($this->client->getState());
        $this->redirectTo($authUrl);
    }

    private function signIn($attrs) {
        return !strcasecmp($attrs['email'], $this->getEmailAddress());
    }

    private function onSignIn() {
        $this->redirectTo(osTicket::get_base_url()
                .sprintf('scp/emails.php?id=%d#%s',
                    $this->getEmailId(),
                    $this->account->getType())
                );
    }

    private function error_msg($errorno, $attrs=[]) {
        switch ($errorno) {
            case self::ERR_EMAIL_ATTR:
                return __('Invalid Email Atrribute');
                break;
            case self::ERR_EMAIL_MISMATCH:
                return sprintf(__('Email Mismatch: Expecting Authorization for %s not %s'),
                        $this->getEmailAddress(),
                        $attrs['email']);
                break;
            case self::ERR_REFRESH_TOKEN:
                return __('Unable to obtain Refresh Token');
                break;
            case self::ERR_UNKNOWN:
            default:
                return __('Unknown Error');
        }
    }
}

abstract class OAuth2ProviderBackend extends OAuth2AuthorizationBackend {
    protected $config;
    private $plugin;
    private $plugin_id;
    static $defaults = [];

    // Strict flag
    private $strict = false;

    function __construct($options=[]) {
        if (isset($options['plugin_id']))
            $this->plugin_id = (int) $options['plugin_id'];
    }

    function isStrict() {
        return (bool) $this->strict;
    }

    function getId() {
        return static::$id;
    }

    function getName() {
        return static::$name;
    }

    function getPluginId() {
        return $this->plugin_id;
    }

    function getPlugin() {
        if (!isset($this->plugin) && $this->plugin_id)
            $this->plugin = PluginManager::lookup($this->plugin_id);

        return $this->plugin;
    }

    function getConfig($instance=null, $vars=[]) {
        if  ($instance && !is_object($instance))
            $instance = $this->getPluginInstance($instance);
        if (!isset($this->config) || $instance) {
            $this->config = new OAuth2EmailConfig($instance ?
                    $instance->getNamespace() : null, $vars);
            $this->config->setInstance($instance);
        }

        return $this->config;
    }

    function getConfigForm($vars, $id=null) {
        $vars = $vars ?: $this->getDefaults();
        return $this->getConfig($id, $vars)->getForm($vars);
    }

    function getDefaults() {
        return static::$defaults ?: [];
    }

    function getPluginInstance($id) {
        return $this->getPlugin()->getInstance($id);
    }

    function addPluginInstance($vars, &$errors) {
        if (!($plugin=$this->getPlugin()))
            return false;
        // Add some default values not set on Basic Config
        $vars = array_merge($vars, array_intersect_key($this->getDefaults(),
                array_flip(['attr_username', 'attr_email', 'attr_givenname',
                'attr_surname'])));
        return $plugin->addInstance($vars, $errors);
    }

    function getEmailAuthBackend($id)  {
        list($auth, $a, $i, $strict) = self::parseId($id);
        if (!strcasecmp($auth, $this->getId())
                && ($plugin=$this->getPlugin())
                && $plugin->isActive()
                && ($instance=$this->getPluginInstance((int) $i))
                && ($config=$instance->getConfig())
                && ($account=EmailAccount::lookup((int) $a))
                && $account->isEnabled()) {
            // Set strict flag
            $this->strict = (bool) $strict;
            $bk = new  OAuth2EmailAuthBackend($config, $this);
            $bk->account = $account;
            return  $bk;
        }
    }

    function refreshAccessToken($refreshToken, $id, &$errors)  {
        if (!$refreshToken || !($bk=$this->getEmailAuthBackend($id)))
            return false;

        try {
            $token = $bk->refreshAccessToken($refreshToken);
            return array_filter([
		'access_token' => $token->getToken(),
		'refresh_token' => $token->getRefreshToken(),
		'expires' => $token->getExpires()
	    ]);
        } catch( Exception $ex) {
            $errors['refresh_token'] = $ex->getMessage();
        }
    }

    function triggerEmailAuth($id) {
        if (!($bk=$this->getEmailAuthBackend($id)))
            return false;

        $_SESSION['ext:bk:id'] = $id;
        $bk->triggerAuth();
    }

    // We delegate call back to Email Authorization backend
    function callback($resp, $id='') {
        if (!$id || !($bk=$this->getEmailAuthBackend($id)))
            return false;

        return $bk->callback($resp, $id);
    }

    //  Register Authentication Providers (Templates)
    static function registerAuthenticationProviders($options=[]) {
        OAuth2AuthenticationBackend::register(new
                GoogleOAuth2Provider($options));
        OAuth2AuthenticationBackend::register(new
                MicrosoftOAuth2Provider($options));
        OAuth2AuthenticationBackend::register(new
                OktaOAuth2Provider($options));
        OAuth2AuthenticationBackend::register(new
                OtherOAuth2Provider($options));
    }

    //  Register Authorization Providers
    static function registerEmailAuthoProviders($options=[]) {
        OAuth2AuthorizationBackend::register(new
                GoogleEmailOAuth2Provider($options));
        OAuth2AuthorizationBackend::register(new
                MicrosoftEmailOAuth2Provider($options));
        OAuth2AuthorizationBackend::register(new
                OtherEmailOAuth2Provider($options));
    }

    static function registerProviders($options=[]) {
        self::registerEmailAuthoProviders($options);
        self::registerAuthenticationProviders($options);
    }

    abstract function getClient(PluginConfig $config);
}

class OAuth2Client extends GenericProvider {
    protected function getAuthorizationParameters(array $options) {
        // Cleanup prompt conflicts
        // approval_prompt, hardcoded upstream, nolonger works for Google
        // when attempting to force new refresh token.
        $options = parent::getAuthorizationParameters($options);
        if (isset($options['prompt']) && isset($options['approval_prompt']))
            unset($options['approval_prompt']);

        return $options;
    }
}


class GenericOauth2Provider extends OAuth2ProviderBackend {
    static $id = 'oauth2:other';
    static $name = 'OAuth2 - Other';
    static $defaults = [];
    static $urlOptions = [];


    function getUrlOptions() {
        return static::$urlOptions;
    }

    function getClient(PluginConfig $config) {
        return new OAuth2Client($config->getClientSettings());
    }
}

class OtherOauth2Provider extends GenericOauth2Provider {
    static $id = 'oauth2:other';
    static $name = 'OAuth2 - Other';
    static $icon = 'icon-plus-sign';
    static $defaults = [];
    static $urlOptions = [];

}

// Authentication Providers
class GoogleOauth2Provider extends GenericOauth2Provider {
    static $id = 'oauth2:google';
    static $name = 'Google';
    static $icon = 'icon-google-plus-sign';
    static $defaults = [
        'urlAuthorize'   => 'https://accounts.google.com/o/oauth2/v2/auth',
        'urlAccessToken' => 'https://oauth2.googleapis.com/token',
        'urlResourceOwnerDetails' => 'https://www.googleapis.com/oauth2/v2/userinfo',
        'scopes' => 'profile https://www.googleapis.com/auth/userinfo.email',
        'auth_name' => 'Google',
        'auth_service' => 'Google',
        'attr_username' => 'email',
        'attr_email' => 'email',
        'attr_givenname' => 'given_name',
        'attr_surname' => 'family_name',
        ];
}

class MicrosoftOauth2Provider extends GenericOauth2Provider {
    static $id = 'oauth2:microsoft';
    static $name = 'Microsoft';
    static $icon = 'icon-windows';
    static $defaults = [
        'urlAuthorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
        'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
        'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
        'scopes' => 'https://graph.microsoft.com/.default',
        'auth_name' => 'Microsoft',
        'auth_service' => 'Azure',
        'attr_username' => 'userPrincipalName',
        'attr_email' => 'mail',
        'attr_givenname' => 'givenname',
        'attr_surname' => 'surname',
        ];
}

class OktaOauth2Provider extends GenericOauth2Provider {
    static $id = 'oauth2:okta';
    static $name = 'Okta';
    static $icon = 'icon-circle-blank';
    static $defaults = [
        'urlAuthorize' => 'https://${yourOktaDomain}/oauth2/v1/authorize',
        'urlAccessToken' => 'https://${yourOktaDomain}/oauth2/v1/token',
        'urlResourceOwnerDetails' => 'https://${yourOktaDomain}/oauth2/v1/userinfo',
        'scopes' => 'openid profile email',
        'auth_name' => 'Okta',
        'auth_service' => 'Okta',
        'attr_username' => 'userName',
        'attr_email' => 'email',
        'attr_givenname' => 'given_name',
        'attr_surname' => 'family_name',
        ];
}

// Authorization Email OAuth Providers
class GenericEmailOauth2Provider extends GenericOauth2Provider {
   function getPluginInstance($id) {
       $i = parent::getPluginInstance($id);
       // Set config class for Email Authorization Providers
       $i->setConfigClass('OAuth2EmailConfig');
       return $i;
    }
}

class OtherEmailOauth2Provider extends GenericEmailOauth2Provider {
    static $id = 'oauth2:othermail';
    static $name = 'OAuth2 - Other Provider';
    static $defaults = [];
    static $urlOptions = [];
}

class GoogleEmailOauth2Provider extends GenericEmailOauth2Provider {
    static $id = 'oauth2:gmail';
    static $name = 'OAuth2 - Google';
    static $defaults = [
        'urlAuthorize'   => 'https://accounts.google.com/o/oauth2/v2/auth',
        'urlAccessToken' => 'https://oauth2.googleapis.com/token',
        'urlResourceOwnerDetails' => 'https://www.googleapis.com/gmail/v1/users/me/profile',
        'scopes' => 'https://mail.google.com/',
        'attr_username' => 'emailaddress',
        'attr_email' => 'emailaddress',
        'attr_givenname' => 'given_name',
        'attr_surname' => 'family_name',
        ];
    static $urlOptions = [
        'responseType' => 'code',
        'access_type' => 'offline',
        'prompt' => 'consent',
        ];
}

class MicrosoftEmailOauth2Provider extends GenericEmailOauth2Provider {
    static $id = 'oauth2:msmail';
    static $name = 'OAuth2 - Microsoft';
    static $defaults = [
        'urlAuthorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
        'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
        'urlResourceOwnerDetails' => 'https://outlook.office.com/api/v2.0/me',
        'scopes' => 'offline_access https://outlook.office.com/Mail.ReadWrite',
        'attr_username' => 'EmailAddress',
        'attr_email' => 'EmailAddress',
        'attr_givenname' => 'givenname',
        'attr_surname' => 'surname',
        ];
    static $urlOptions = [
        'tenant' => 'common',
        'accessType' => 'offline_access',
        'prompt' => 'select_account',
        ];
}
?>

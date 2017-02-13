<?php

use ohmy\Auth2;

class GoogleAuth {
    var $config;
    var $access_token;

    function __construct($config) {
        $this->config = $config;
    }

    function triggerAuth() {
        global $ost;
        $self = $this;
        return Auth2::legs(3)
            ->set('id', $this->config->get('g-client-id'))
            ->set('secret', $this->config->get('g-client-secret'))
            ->set('redirect', rtrim($ost->getConfig()->getURL(), '/') . '/api/auth/ext')
            ->set('scope', 'profile email')

            ->authorize('https://accounts.google.com/o/oauth2/auth')
            ->access('https://accounts.google.com/o/oauth2/token')

            ->finally(function($data) use ($self) {
                $self->access_token = $data['access_token'];
            });
    }
}

class GoogleStaffAuthBackend extends ExternalStaffAuthenticationBackend {
    static $id = "google";
    static $name = "Google Plus";

    static $sign_in_image_url = "https://developers.google.com/+/images/branding/sign-in-buttons/White-signin_Long_base_44dp.png";
    static $service_name = "Google+";

    var $config;

    function __construct($config) {
        $this->config = $config;
        $this->google = new GoogleAuth($config);
    }

    function signOn() {
        // TODO: Check session for auth token
        if (isset($_SESSION[':oauth']['email'])) {
            if (($staff = StaffSession::lookup(array('email' => $_SESSION[':oauth']['email'])))
                && $staff->getId()
            ) {
                if (!$staff instanceof StaffSession) {
                    // osTicket <= v1.9.7 or so
                    $staff = new StaffSession($user->getId());
                }
                return $staff;
            }
            else
                $_SESSION['_staff']['auth']['msg'] = 'Have your administrator create a local account';
        }
    }

    static function signOut($user) {
        parent::signOut($user);
        unset($_SESSION[':oauth']);
    }


    function triggerAuth() {
        parent::triggerAuth();
        $google = $this->google->triggerAuth();
        $google->GET(
            "https://www.googleapis.com/oauth2/v1/tokeninfo?access_token="
                . $this->google->access_token)
            ->then(function($response) {
                require_once INCLUDE_DIR . 'class.json.php';
                if ($json = JsonDataParser::decode($response->text))
                    $_SESSION[':oauth']['email'] = $json['email'];
                Http::redirect(ROOT_PATH . 'scp');
            }
        );
    }
}

class GoogleClientAuthBackend extends ExternalUserAuthenticationBackend {
    static $id = "google.client";
    static $name = "Google Plus";

    static $sign_in_image_url = "https://developers.google.com/+/images/branding/sign-in-buttons/Red-signin_Long_base_44dp.png";
    static $service_name = "Google+";

    function __construct($config) {
        $this->config = $config;
        $this->google = new GoogleAuth($config);
    }

    function supportsInteractiveAuthentication() {
        return false;
    }

    function signOn() {
        // TODO: Check session for auth token
        if (isset($_SESSION[':oauth']['email'])) {
            if (($acct = ClientAccount::lookupByUsername($_SESSION[':oauth']['email']))
                    && $acct->getId()
                    && ($client = new ClientSession(new EndUser($acct->getUser()))))
                return $client;

            elseif (isset($_SESSION[':oauth']['profile'])) {
                // TODO: Prepare ClientCreateRequest
                $profile = $_SESSION[':oauth']['profile'];
                $info = array(
                    'email' => $_SESSION[':oauth']['email'],
                    'name' => $profile['displayName'],
                );
                return new ClientCreateRequest($this, $info['email'], $info);
            }
        }
    }

    static function signOut($user) {
        parent::signOut($user);
        unset($_SESSION[':oauth']);
    }

    function triggerAuth() {
        require_once INCLUDE_DIR . 'class.json.php';
        parent::triggerAuth();
        $google = $this->google->triggerAuth();
        $token = $this->google->access_token;
        $google->GET(
            "https://www.googleapis.com/oauth2/v1/tokeninfo?access_token="
                . $token)
            ->then(function($response) use ($google, $token) {
                if (!($json = JsonDataParser::decode($response->text)))
                    return;
                $_SESSION[':oauth']['email'] = $json['email'];
                $google->GET(
                    "https://www.googleapis.com/plus/v1/people/me?access_token="
                        . $token)
                    ->then(function($response) {
                        if (!($json = JsonDataParser::decode($response->text)))
                            return;
                        $_SESSION[':oauth']['profile'] = $json;
                        Http::redirect(ROOT_PATH . 'login.php');
                    }
                );
            }
        );
    }
}

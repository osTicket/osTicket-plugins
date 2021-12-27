<?php

use ohmy\Auth2;

class GenericOAuth {
    var $config;
    var $access_token;

    function __construct($config) {
        $this->config = $config;
    }

    function triggerAuth() {
        $self = $this;
        global $ost;
        return Auth2::legs(3)
            ->set('id', $this->config->get('generic-client-id'))
            ->set('secret', $this->config->get('generic-client-secret'))
            ->set('redirect', rtrim($ost->getConfig()->getURL(), '/') . '/api/auth/ext')
            ->set('scope', $this->config->get('generic-scope'))

            ->authorize($this->config->get('generic-authorize-url'))
            ->access($this->config->get('generic-token-url'))
            ->finally(function($data) use ($self) {
                $self->access_token = $data['access_token'];
            });
    }
}

class GenericOAuth2StaffAuthBackend extends ExternalStaffAuthenticationBackend {
    static $id = "oauth";
    static $name = "OAuth2";

    static $sign_in_image_url = "";

    var $config;

    function __construct($config) {
        $this->config = $config;
        $this->oauth = new GenericOAuth($config);
    }

    function getServiceName() {
        return $this->config->get('generic-servicename');
    }

    function signOn() {
        // TODO: Check session for auth token
        if (isset($_SESSION[':oauth']['profile']['nickname'])) {
            if (($staff = StaffSession::lookup($_SESSION[':oauth']['profile']['nickname']))
                && $staff->getId()
            ) {
                $staffobject = $staff;
            }
        } elseif (isset($_SESSION[':oauth']['profile']['email'])) {
            if (($staff = StaffSession::lookup(array('email' => $_SESSION[':oauth']['profile']['email'])))
                && $staff->getId()
            ) {
                $staffobject = $staff;
            }
        }
        if (isset($staffobject)) {
            if (!$staffobject instanceof StaffSession) {
                // osTicket <= v1.9.7 or so
                $staffobject = new StaffSession($user->getId());
            }
            return $staffobject;
        } else {
            $_SESSION['_staff']['auth']['msg'] = 'Have your administrator create a local account';
        }
    }

    static function signOut($user) {
        parent::signOut($user);
        unset($_SESSION[':oauth']);
    }


    function triggerAuth() {
        parent::triggerAuth();
        $oauth = $this->oauth->triggerAuth();
        $oauth->GET(
            $this->config->get('generic-userinfo-url'), [], array('Authorization' => 'Bearer ' . $this->oauth->access_token))
            ->then(function($response) {
                if (!($json = JsonDataParser::decode($response->text)))
                    return;
                $_SESSION[':oauth']['profile'] = $json;
                Http::redirect(ROOT_PATH . 'scp');
            }
            );
    }
}

class GenericOAuth2AuthBackend extends ExternalUserAuthenticationBackend {
    static $id = "oauth.client";
    static $name = "OAuth2";

    static $sign_in_image_url = "";

    function __construct($config) {
        $this->config = $config;
        $this->oauth = new GenericOAuth($config);
    }

    function getServiceName() {
        return $this->config->get('generic-servicename');
    }

    function supportsInteractiveAuthentication() {
        return false;
    }

    function signOn() {
        // TODO: Check session for auth token
        if (isset($_SESSION[':oauth']['profile']['email'])) {
            if (($acct = ClientAccount::lookupByUsername($_SESSION[':oauth']['profile']['email']))
                && $acct->getId()
                && ($client = new ClientSession(new EndUser($acct->getUser()))))
                return $client;

            elseif (isset($_SESSION[':oauth']['profile'])) {
                // TODO: Prepare ClientCreateRequest
                $profile = $_SESSION[':oauth']['profile'];
                $info = array(
                    'email' => $profile['email'],
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
        $oauth = $this->oauth->triggerAuth();
        $oauth->GET(
            $this->config->get('generic-userinfo-url'), [], array('Authorization' => 'Bearer ' . $this->oauth->access_token))
            ->then(function($response) {
                if (!($json = JsonDataParser::decode($response->text)))
                    return;
                $_SESSION[':oauth']['profile'] = $json;
                Http::redirect(ROOT_PATH . 'login.php');
            }
            );
    }
}


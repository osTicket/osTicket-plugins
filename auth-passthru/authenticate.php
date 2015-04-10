<?php

require_once(INCLUDE_DIR.'class.auth.php');
class HttpAuthentication extends StaffAuthenticationBackend {
    static $name = "HTTP Authentication";
    static $id = "passthru";

    function supportsInteractiveAuthentication() {
        return false;
    }

    function signOn() {
        if (isset($_SERVER['REMOTE_USER']) && !empty($_SERVER['REMOTE_USER']))
            // User was authenticated by the HTTP server
            $username = $_SERVER['REMOTE_USER'];
        elseif (isset($_SERVER['REDIRECT_REMOTE_USER'])
                && !empty($_SERVER['REDIRECT_REMOTE_USER']))
            $username = $_SERVER['REDIRECT_REMOTE_USER'];

        if ($username) {
            // Support ActiveDirectory domain specification with either
            // "user@domain" or "domain\user" formats
            if (strpos($username, '@') !== false)
                list($username, $domain) = explode('@', $username, 2);
            elseif (strpos($username, '\\') !== false)
                list($domain, $username) = explode('\\', $username, 2);
            $username = trim(strtolower($username));

            if (($user = StaffSession::lookup($username)) && $user->getId()) {
                if (!$user instanceof StaffSession) {
                    // osTicket <= v1.9.7 or so
                    $user = new StaffSession($user->getId());
                }
                return $user;
            }

            // TODO: Consider client sessions
        }
    }
}

class UserHttpAuthentication extends UserAuthenticationBackend {
    static $name = "HTTP Authentication";
    static $id = "passthru.client";

    function supportsInteractiveAuthentication() {
        return false;
    }

    function signOn() {
        if (isset($_SERVER['REMOTE_USER']) && !empty($_SERVER['REMOTE_USER']))
            // User was authenticated by the HTTP server
            $username = $_SERVER['REMOTE_USER'];
        elseif (isset($_SERVER['REDIRECT_REMOTE_USER'])
                && !empty($_SERVER['REDIRECT_REMOTE_USER']))
            $username = $_SERVER['REDIRECT_REMOTE_USER'];

        if ($username) {
            // Support ActiveDirectory domain specification with either
            // "user@domain" or "domain\user" formats
            if (strpos($username, '@') !== false)
                list($username, $domain) = explode('@', $username, 2);
            elseif (strpos($username, '\\') !== false)
                list($domain, $username) = explode('\\', $username, 2);
            $username = trim(strtolower($username));

            if ($acct = ClientAccount::lookupByUsername($username)) {
                if (($client = new ClientSession(new EndUser($acct->getUser())))
                        && $client->getId())
                    return $client;
            }
            else {
                // No such account. Attempt a lookup on the username
                $users = parent::searchUsers($username);
                if (!is_array($users))
                    return;

                foreach ($users as $u) {
                    if (0 === strcasecmp($u['username'], $username)
                            || 0 === strcasecmp($u['email'], $username))
                        // User information matches HTTP username
                        return new ClientCreateRequest($this, $username, $u);
                }
            }
        }
    }
}

require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');
class PassthruAuthPlugin extends Plugin {
    var $config_class = 'PassthruAuthConfig';

    function bootstrap() {
        $config = $this->getConfig();
        if ($config->get('auth-staff'))
            StaffAuthenticationBackend::register('HttpAuthentication');
        if ($config->get('auth-client'))
            UserAuthenticationBackend::register('UserHttpAuthentication');
    }
}

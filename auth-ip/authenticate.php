<?php

require_once(INCLUDE_DIR.'class.auth.php');

class UserIpAuthentication extends UserAuthenticationBackend {
    static $name = "IP Authentication";
    static $id = "ip.client";

    function supportsInteractiveAuthentication() {
        return false;
    }

    function signOn() {
        if (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            if (isset($_GET['ddns']) && !empty($_GET['ddns']) && $_SERVER['REMOTE_ADDR'] === gethostbyname($_GET['ddns'])) {
                $username = $_GET['ddns'];
            } else {
                $username = $_SERVER['REMOTE_ADDR'];
            }
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
                        // User information is valid
                        return new ClientCreateRequest($this, $username, $u);
                }
            }
        }
    }
}

require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');
class IpAuthPlugin extends Plugin {
    var $config_class = 'IpAuthConfig';

    function bootstrap() {
        $config = $this->getConfig();
        if ($config->get('auth-client'))
            UserAuthenticationBackend::register('UserIpAuthentication');
    }
}


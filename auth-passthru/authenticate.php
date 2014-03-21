<?php

require_once(INCLUDE_DIR.'class.auth.php');
class HttpAuthentication extends StaffAuthenticationBackend {
    static $name = "HTTP Authentication";
    static $id = "passthru";

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

            if (($user = new StaffSession($username)) && $user->getId())
                return $user;

            // TODO: Consider client sessions
        }
    }
}

class PassthruAuthPlugin extends Plugin {
    function bootstrap() {
        StaffAuthenticationBackend::register('HttpAuthentication');
    }
}

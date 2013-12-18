<?php

require_once(INCLUDE_DIR.'class.auth.php');
class HttpAuthentication extends AuthenticationBackend {
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
        AuthenticationBackend::register('HttpAuthentication');
    }
}

return array(
    'id' =>             'auth:passthru', # notrans
    'version' =>        '0.1',
    'name' =>           'HTTP Passthru Authentication',
    'author' =>         'Jared Hancock',
    'description' =>    'Allows for the HTTP server (Apache or IIS) to perform
    the authentication of the user. osTicket will match the username from the
    server authentication to a username defined internally',
    'url' =>            'http://www.osticket.com/plugins/auth/passthru',
    'plugin' =>         'PassthruAuthPlugin'
);

?>

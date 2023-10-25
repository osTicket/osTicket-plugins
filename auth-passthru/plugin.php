<?php

return array(
    'id' =>             'auth:passthru', # notrans
    'version' =>        '0.2',
    'name' =>           /* trans */ 'HTTP Passthru Authentication',
    'author' =>         'Jared Hancock',
    'description' =>    /* trans */ 'Allows for the HTTP server (Apache or IIS) to perform
    the authentication of the user. osTicket will match the username from the
    server authentication to a username defined internally',
    'url' =>            'http://www.osticket.com/plugins/auth/passthru',
    'plugin' =>         'authenticate.php:PassthruAuthPlugin'
);

?>

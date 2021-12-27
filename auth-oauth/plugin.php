<?php

return array(
    'id' =>             'auth:oath2', # notrans
    'version' =>        '0.2',
    'name' =>           /* trans */ 'Oauth2 Authentication and Lookup',
    'author' =>         'Jared Hancock, Andreas Valder',
    'description' =>    /* trans */ 'Provides a configurable authentication backend
        for authenticating staff and clients using an OAUTH2 server
        interface.',
    'url' =>            'https://github.com/osTicket/osTicket-plugins',
    'plugin' =>         'authentication.php:OauthAuthPlugin',
    'requires' => array(
        "ohmy/auth" => array(
            "version" => "*",
            "map" => array(
                "ohmy/auth/src" => 'lib',
            )
        ),
    ),
);

?>
<?php
return array(
    'id' =>             'auth:oath2', # notrans
    'version' =>        '0.1',
    'name' =>           /* trans */ 'Oauth2 Client',
    'author' =>         'Peter Rotich <peter@osticket.com>',
    'description' =>    /* trans */ 'Provides a configurable Oauth2 authentication and authorization backends.  backends.',
    'url' =>            'http://www.osticket.com/',
    'plugin' =>         'auth.php:OAuth2Plugin',
    'requires' => array(
        "league/oauth2-client" => array(
            "version" => "*",
            "map" => array(
                "league/oauth2-client/src" => 'lib/League/OAuth2/Client',
                'guzzlehttp/guzzle/src' => 'lib/GuzzleHttp',
                'guzzlehttp/psr7/src/' => 'lib/Psr7',
            )
        ),
    ),
);
?>

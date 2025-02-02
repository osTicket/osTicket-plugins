<?php
return array(
    'id' =>             'auth:oath2', # notrans
    'version' =>        '0.6',
    'ost_version' =>    '1.17', # Require osTicket v1.17+
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
                'guzzlehttp/psr7/src'   => 'lib/GuzzleHttp/Psr7',
                'guzzlehttp/promises/src' => 'lib/GuzzleHttp/Promise',
                'psr/http-client/src' => 'lib/Psr/Http/Client',
                'psr/http-factory/src' => 'lib/Psr/Http/Factory',
                'psr/http-message/src' => 'lib/Psr/Http/Message',

            )
        ),
    ),
);
?>

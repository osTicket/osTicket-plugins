<?php

return array(
    'id' =>             '2fa:auth', # notrans
    'version' =>        '0.3',
    'name' =>           /* trans */ 'Two Factor Authenticator',
    'author' =>         'Adriane Alexander',
    'description' =>    /* trans */ 'Provides 2 Factor Authentication
                        using an Authenticator App',
    'url' =>            'https://www.osticket.com/download',
    'plugin' =>         'auth2fa.php:Auth2FAPlugin',
    'requires' => array(
        "sonata-project/google-authenticator" => array(
            "version" => "*",
            "map" => array(
                "sonata-project/google-authenticator/src" => 'lib/Sonata/GoogleAuthenticator',
            )
        ),
    ),
);
?>

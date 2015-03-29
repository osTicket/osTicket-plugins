<?php
return array(
    'id' =>             'auth:cas', # notrans
    'version' =>        '0.1',
    'name' =>           'JASIG CAS Authentication',
    'author' =>         'Kevin O\'Connor',
    'description' =>    'Provides a configurable authentication backend
        for authenticating staff and clients using anJASIG CAS interface.',
    'url' =>            'http://www.osticket.com/plugins/auth/cas',
    'plugin' =>         'authentication.php:CasAuthPlugin',
    'requires' => array(
        "jasig/phpcas" => array(
            "version" => "1.3.3",
            "map" => array(
                "jasig/phpcas/source" => 'lib/jasig/phpcas',
            )
        ),
    ),
);

?>

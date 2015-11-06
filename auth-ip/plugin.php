<?php

return array(
    'id' =>             'auth:ip', # notrans
    'version' =>        '0.1',
    'name' =>           /* trans */ 'IP Authentication',
    'author' =>         'Maximilian Weber',
    'description' =>    /* trans */ 'Allows user authentication based on request IP addresses. osTicket will match the request IP address to the username.',
    'url' =>            'http://www.osticket.com/plugins/auth/ip',
    'plugin' =>         'authenticate.php:IpAuthPlugin'
);

?>

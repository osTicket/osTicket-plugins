<?php
set_include_path(get_include_path().PATH_SEPARATOR.dirname(__file__).'/include');
return array(
    'id' =>             'auth:ldap', # notrans
    'version' =>        '0.6.2',
    'name' =>           /* trans */ 'LDAP Authentication and Lookup',
    'author' =>         'Jared Hancock',
    'description' =>    /* trans */ 'Provides a configurable authentication backend
        which works against Microsoft Active Directory and OpenLdap
        servers',
    'url' =>            'http://www.osticket.com/plugins/auth/ldap',
    'plugin' =>         'authentication.php:LdapAuthPlugin',
    'requires' => array(
        "pear-pear.php.net/Net_LDAP2" => array(
            "version" => "*",
            "map" => array(
                'pear-pear.php.net/Net_LDAP2' => 'include',
            ),
        ),
    ),
);

?>

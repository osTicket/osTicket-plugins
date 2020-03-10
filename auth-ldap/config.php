<?php

require_once(INCLUDE_DIR.'/class.plugin.php');
require_once(INCLUDE_DIR.'/class.forms.php');


class LdapConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('auth-ldap');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'msad' => new SectionBreakField(array(
                'label' => 'Microsoft® Active Directory',
                'hint' => $__('This section should be all that is required for Active Directory domains'),
            )),
            'domain' => new TextboxField(array(
                'label' => $__('Default Domain'),
                'hint' => $__('Default domain used in authentication and searches'),
                'configuration' => array('size'=>40, 'length'=>60),
                'validators' => array(
                function($self, $val) use ($__) {
                    if (strpos($val, '.') === false)
                        $self->addError(
                            $__('Fully-qualified domain name is expected'));
                }),
            )),
            'dns' => new TextboxField(array(
                'label' => $__('DNS Servers'),
                'hint' => $__('(optional) DNS servers to query about AD servers.
                    Useful if the AD server is not on the same network as
                    this web server or does not have its DNS configured to
                    point to the AD servers'),
                'configuration' => array('size'=>40),
                'validators' => array(
                function($self, $val) use ($__) {
                    if (!$val) return;
                    $servers = explode(',', $val);
                    foreach ($servers as $s) {
                        if (!Validator::is_ip(trim($s)))
                            $self->addError(sprintf(
                                $__('%s: Expected an IP address'), $s));
                    }
                }),
            )),

            'ldap' => new SectionBreakField(array(
                'label' => $__('Generic configuration for LDAP'),
                'hint' => $__('Not necessary if Active Directory is configured above'),
            )),
            'servers' => new TextareaField(array(
                'id' => 'servers',
                'label' => $__('LDAP servers'),
                'configuration' => array('html'=>false, 'rows'=>2, 'cols'=>40),
                'hint' => $__('Use "server" or "server:port". Place one server entry per line'),
            )),
            'tls' => new BooleanField(array(
                'id' => 'tls',
                'label' => $__('Use TLS'),
                'configuration' => array(
                    'desc' => $__('Use TLS to communicate with the LDAP server'))
            )),

            'conn_info' => new SectionBreakField(array(
                'label' => $__('Connection Information'),
                'hint' => $__('Useful only for information lookups. Not
                necessary for authentication. NOTE that this data is not
                necessary if your server allows anonymous searches')
            )),
            'bind_dn' => new TextboxField(array(
                'label' => $__('Search User'),
                'hint' => $__('Bind DN (distinguished name) to bind to the LDAP
                    server as in order to perform searches'),
                'configuration' => array('size'=>40, 'length'=>120),
            )),
            'bind_pw' => new TextboxField(array(
                'widget' => 'PasswordWidget',
                'label' => $__('Password'),
                'validator' => 'noop',
                'hint' => $__("Password associated with the DN's account"),
                'configuration' => array('size'=>40),
            )),
            'search_base' => new TextboxField(array(
                'label' => $__('Search Base'),
                'hint' => $__('Used when searching for users'),
                'configuration' => array('size'=>70, 'length'=>120),
            )),
            'schema' => new ChoiceField(array(
                'label' => $__('LDAP Schema'),
                'hint' => $__('Layout of the user data in the LDAP server'),
                'default' => 'auto',
                'choices' => array(
                    'auto' => '— '.$__('Automatically Detect').' —',
                    'msad' => 'Microsoft® Active Directory',
                    '2307' => 'Posix Account (rfc 2307)',
                ),
            )),

            'auth' => new SectionBreakField(array(
                'label' => $__('Authentication Modes'),
                'hint' => $__('Authentication modes for clients and staff
                    members can be enabled independently'),
            )),
            'auth-staff' => new BooleanField(array(
                'label' => $__('Staff Authentication'),
                'default' => true,
                'configuration' => array(
                    'desc' => $__('Enable authentication of staff members')
                )
            )),
            'auth-client' => new BooleanField(array(
                'label' => $__('Client Authentication'),
                'default' => false,
                'configuration' => array(
                    'desc' => $__('Enable authentication of clients')
                )
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        require_once('include/Net/LDAP2.php');
        list($__, $_N) = self::translate();

        global $ost;
        if ($ost && !extension_loaded('ldap')) {
            $ost->setWarning($__('LDAP extension is not available'));
            $errors['err'] = $__('LDAP extension is not available. Please
                install or enable the `php-ldap` extension on your web
                server');
            return;
        }

        if ($config['domain'] && !$config['servers']) {
            if (!($servers = LDAPAuthentication::autodiscover($config['domain'],
                    preg_split('/,?\s+/', $config['dns']))))
                $this->getForm()->getField('servers')->addError(
                    $__("Unable to find LDAP servers for this domain. Try giving
                    an address of one of the DNS servers or manually specify
                    the LDAP servers for this domain below."));
        }
        else {
            if (!$config['servers'])
                $this->getForm()->getField('servers')->addError(
                    $__("No servers specified. Either specify a Active Directory
                    domain or a list of servers"));
            else {
                $servers = array();
                foreach (preg_split('/\s+/', $config['servers']) as $host)
                    if (preg_match('/([^:]+):(\d{1,4})/', $host, $matches))
                        $servers[] = array('host' => $matches[1], 'port' => $matches[2]);
                    else
                        $servers[] = array('host' => $host);
            }
        }
        $connection_error = false;
        foreach ($servers as $info) {
            // Assume MSAD
            $info['options']['LDAP_OPT_REFERRALS'] = 0;
            if ($config['tls']) {
                $info['starttls'] = true;
                // Don't require a certificate here
                putenv('LDAPTLS_REQCERT=never');
            }
            if ($config['bind_dn']) {
                $info['binddn'] = $config['bind_dn'];
                $info['bindpw'] = $config['bind_pw']
                    ? $config['bind_pw']
                    : Crypto::decrypt($this->get('bind_pw'), SECRET_SALT,
                        $this->getNamespace());
            }
            // Set reasonable timeouts so we dont exceed max_execution_time
            $info['options'] = array(
                'LDAP_OPT_TIMELIMIT' => 5,
                'LDAP_OPT_NETWORK_TIMEOUT' => 5,
            );
            $c = new Net_LDAP2($info);
            $r = $c->bind();
            if (PEAR::isError($r)) {
                if (false === strpos($config['bind_dn'], '@')
                        && false === strpos($config['bind_dn'], ',dc=')) {
                    // Assume Active Directory, add the default domain in
                    $config['bind_dn'] .= '@' . $config['domain'];
                    $info['bind_dn'] = $config['bind_dn'];
                    $c = new Net_LDAP2($info);
                    $r = $c->bind();
                }
            }
            if (PEAR::isError($r)) {
                $connection_error = sprintf($__(
                    '%s: Unable to bind to server %s'),
                    $r->getMessage(), $info['host']);
            }
            else {
                $connection_error = false;
                break;
            }
        }
        if ($connection_error) {
            $this->getForm()->getField('servers')->addError($connection_error);
            $errors['err'] = $__('Unable to connect any listed LDAP servers');
        }

        if (!$errors && $config['bind_pw'])
            $config['bind_pw'] = Crypto::encrypt($config['bind_pw'],
                SECRET_SALT, $this->getNamespace());
        else
            $config['bind_pw'] = $this->get('bind_pw');

        global $msg;
        if (!$errors)
            $msg = $__('LDAP configuration updated successfully');

        return !$errors;
    }
}

?>

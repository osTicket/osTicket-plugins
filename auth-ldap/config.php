<?php

require_once(INCLUDE_DIR.'/class.plugin.php');
require_once(INCLUDE_DIR.'/class.forms.php');

class LdapConfig extends PluginConfig {
    function getOptions() {
        return array(
            'msad' => new SectionBreakField(array(
                'label' => 'Microsoft® Active Directory',
                'hint' => 'This section should be complete for Active
                    Directory domains',
            )),
            'domain' => new TextboxField(array(
                'label' => 'Default Domain',
                'hint' => 'Default domain used in authentication and searches',
                'configuration' => array('size'=>40, 'length'=>60),
                'validators' => array(
                function($self, $val) {
                    if (strpos($val, '.') === false)
                        $self->addError(
                            'Fully-qualified domain name is expected');
                }),
            )),
            'dns' => new TextboxField(array(
                'label' => 'DNS Servers',
                'hint' => '(optional) DNS servers to query about AD servers.
                    Useful if the AD server is not on the same network as
                    this web server or does not have its DNS configured to
                    point to the AD servers',
                'configuration' => array('size'=>40),
                'validators' => array(
                function($self, $val) {
                    if (!$val) return;
                    $servers = explode(',', $val);
                    foreach ($servers as $s) {
                        if (!Validator::is_ip(trim($s)))
                            $self->addError($s.': Expected an IP address');
                    }
                }),
            )),

            'ldap' => new SectionBreakField(array(
                'label' => 'Generic configuration for LDAP',
                'hint' => 'Not necessary if Active Directory is configured above',
            )),
            'servers' => new TextareaField(array(
                'id' => 'servers',
                'label' => 'LDAP servers',
                'configuration' => array('html'=>false, 'rows'=>2, 'cols'=>40),
                'hint' => 'Use "server" or "server:port". Place one server '
                    .'entry per line',
            )),
            'tls' => new BooleanField(array(
                'id' => 'tls',
                'label' => 'Use TLS',
                'configuration' => array(
                    'desc' => 'Use TLS to communicate with the LDAP server')
            )),

            'conn_info' => new SectionBreakField(array(
                'label' => 'Connection Information',
                'hint' => 'Useful only for information lookups. Not
                necessary for authentication. NOTE that this data is not
                necessary if your server allows anonymous searches'
            )),
            'bind_dn' => new TextboxField(array(
                'label' => 'Search User',
                'hint' => 'Bind DN (distinguised name) to bind to the LDAP
                    server as in order to perform searches',
                'configuration' => array('size'=>40, 'length'=>120),
            )),
            'bind_pw' => new TextboxField(array(
                'widget' => 'PasswordWidget',
                'label' => 'Password',
                'hint' => "Password associated with the DN's account",
                'configuration' => array('size'=>40),
            )),
            'search_base' => new TextboxField(array(
                'label' => 'Search Base',
                'hint' => 'Used when searching for users',
                'configuration' => array('size'=>70, 'length'=>120),
            )),
            'schema' => new ChoiceField(array(
                'label' => 'LDAP Schema',
                'hint' => 'Layout of the user data in the LDAP server',
                'default' => 'auto',
                'choices' => array(
                    'auto' => '-- Automatically Detect --',
                    'msad' => 'Microsoft Active Directory',
                    '2307' => 'Posix Account',
                ),
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        require_once('include/Net/LDAP2.php');

        global $ost;
        if ($ost && !extension_loaded('ldap')) {
            $ost->setWarning('LDAP extension is not available');
            return;
        }

        if ($config['domain'] && !$config['servers']) {
            if (!($servers = LDAPAuthentication::autodiscover($config['domain'],
                    preg_split('/,?\s+/', $config['dns']))))
                $this->getForm()->getField('servers')->addError(
                    "Unable to find LDAP servers for this domain. Try giving
                    an address of one of the DNS servers or manually specify
                    the LDAP servers for this domain below.");
        }
        else {
            if (!$config['servers'])
                $this->getForm()->getField('servers')->addError(
                    "No servers specified. Either specify a Active Directory
                    domain or a list of servers");
            else {
                $servers = array();
                foreach (preg_split('/\s+/', $config['servers']) as $host)
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
                $connection_error =
                    $r->getMessage() .': Unable to bind to '.$info['host'];
            }
            else {
                $connection_error = false;
                break;
            }
        }
        if ($connection_error) {
            $this->getForm()->getField('servers')->addError($connection_error);
            $errors['err'] = 'Unable to connect any listed LDAP servers';
        }

        if (!$errors && $config['bind_pw'])
            $config['bind_pw'] = Crypto::encrypt($config['bind_pw'],
                SECRET_SALT, $this->getNamespace());
        else
            $config['bind_pw'] = $this->get('bind_pw');

        global $msg;
        if (!$errors)
            $msg = 'LDAP configuration updated successfully';

        return !$errors;
    }
}

?>

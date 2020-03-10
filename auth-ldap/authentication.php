<?php

function flatten($array) {
    $a = array();
    foreach ($array as $e) {
        if (is_array($e))
            $a = array_merge($a, flatten($e));
        else
            $a[] = $e;
    }
    return $a;
}

function splat($what) {
    return is_array($what) ? flatten($what) : array($what);
}

require_once(INCLUDE_DIR.'class.auth.php');
class LDAPAuthentication {

    /**
     * LDAP typical schema variations
     *
     * References:
     * http://www.kouti.com/tables/userattributes.htm (AD)
     * https://fsuid.fsu.edu/admin/lib/WinADLDAPAttributes.html (AD)
     */
    static $schemas = array(
        'msad' => array(
            'user' => array(
                'filter' => '(objectClass=user)',
                'base' => 'CN=Users',
                'first' => 'givenName',
                'last' => 'sn',
                'full' => 'displayName',
                'email' => 'mail',
                'phone' => 'telephoneNumber',
                'mobile' => false,
                'username' => 'sAMAccountName',
                'dn' => '{username}@{domain}',
                'search' => '(&(objectCategory=person)(objectClass=user)(|(sAMAccountName={q}*)(firstName={q}*)(lastName={q}*)(displayName={q}*)))',
                'lookup' => '(&(objectCategory=person)(objectClass=user)({attr}={q}))',
            ),
            'group' => array(
                'ismember' => '(&(objectClass=user)(sAMAccountName={username})
                    (|(memberOf={distinguishedName})(primaryGroupId={primaryGroupToken})))',
                'lookup' => '(&(objectClass=group)(sAMAccountName={groupname}))',
            ),
        ),
        // A general approach for RFC-2307
        '2307' => array(
            'user' => array(
                'filter' => '(objectClass=inetOrgPerson)',
                'first' => 'gn',
                'last' => 'sn',
                'full' => array('displayName', 'gecos', 'cn'),
                'email' => 'mail',
                'phone' => 'telephoneNumber',
                'mobile' => 'mobileTelephoneNumber',
                'username' => 'uid',
                'dn' => 'uid={username},{search_base}',
                'search' => '(&(objectClass=inetOrgPerson)(|(uid={q}*)(displayName={q}*)(cn={q}*)))',
                'lookup' => '(&(objectClass=inetOrgPerson)({attr}={q}))',
            ),
        ),
    );

    var $config;
    var $type = 'staff';

    function __construct($config, $type='staff') {
        $this->config = $config;
        $this->type = $type;
    }
    function getConfig() {
        return $this->config;
    }

    function autodiscover($domain, $dns=array()) {
        require_once(PEAR_DIR.'Net/DNS2.php');
        // TODO: Lookup DNS server from hosts file if not set
        $q = new Net_DNS2_Resolver();
        if ($dns)
            $q->setServers($dns);

        $servers = array();
        try {
            $r = $q->query('_ldap._tcp.'.$domain, 'SRV');
        } catch (Net_DNS2_Exception $e) {
            // TODO: Log warning or something
            return $servers;
        }
        foreach ($r->answer as $srv) {
            // TODO: Get the actual IP of the server (?)
            $servers[] = array(
                'host' => "{$srv->target}:{$srv->port}",
                'priority' => $srv->priority,
                'weight' => $srv->weight,
            );
        }
        // Sort servers by priority ASC, then weight DESC
        usort($servers, function($a, $b) {
            return ($a['priority'] << 15) - $a['weight']
                - ($b['priority'] << 15) + $b['weight'];
        });
        return $servers;
    }

    function getServers() {
        if (!($servers = $this->getConfig()->get('servers'))
                || !($servers = preg_split('/\s+/', $servers))) {
            if ($domain = $this->getConfig()->get('domain')) {
                $dns = preg_split('/,?\s+/', $this->getConfig()->get('dns'));
                return $this->autodiscover($domain, array_filter($dns));
            }
        }
        if ($servers) {
            $hosts = array();
            foreach ($servers as $h)
                if (preg_match('/([^:]+):(\d{1,4})/', $h, $matches))
                    $hosts[] = array('host' => $matches[1], 'port' => $matches[2]);
                else
                    $hosts[] = array('host' => $h);
            return $hosts;
        }
    }

    function getConnection($force_reconnect=false) {
        static $connection = null;

        if ($connection && !$force_reconnect)
            return $connection;

        require_once('include/Net/LDAP2.php');
        // Set reasonable timeout limits
        $defaults = array(
            'options' => array(
                'LDAP_OPT_TIMELIMIT' => 5,
                'LDAP_OPT_NETWORK_TIMEOUT' => 5,
            )
        );
        if ($this->getConfig()->get('tls'))
            $defaults['starttls'] = true;
        if ($this->getConfig()->get('schema') == 'msad') {
            // Special options for Active Directory (2000+) servers
            //$defaults['starttls'] = true;
            $defaults['options'] += array(
                'LDAP_OPT_PROTOCOL_VERSION' => 3,
                'LDAP_OPT_REFERRALS' => 0,
            );
            // Active Directory servers almost always use self-signed certs
            putenv('LDAPTLS_REQCERT=never');
        }

        foreach ($this->getServers() as $s) {
            $params = $defaults + $s;
            $c = new Net_LDAP2($params);
            $r = $c->bind();
            if (!PEAR::isError($r)) {
                $connection = $c;
                return $c;
            }
        }
    }

    /**
     * Binds to the directory under the search-user credentials configured
     */
    function _bind($connection) {
        if ($dn = $this->getConfig()->get('bind_dn')) {
            $pw = Crypto::decrypt($this->getConfig()->get('bind_pw'),
                SECRET_SALT, $this->getConfig()->getNamespace());
            $r = $connection->bind($dn, $pw);
            unset($pw);
            return !PEAR::isError($r);
        }
        else {
            // try anonymous bind
            $r = $connection->bind();
            return !PEAR::isError($r);
        }
    }

    function authenticate($username, $password=null) {
        // Thanks, http://stackoverflow.com/a/764651
        // Binding with an empty password implies an anonymous bind which
        // will likely be successful and incorrect
        if (!$password)
            return null;

        $c = $this->getConnection();
        $config = $this->getConfig();
        $schema_type = $this->getSchema($c);
        $schema = static::$schemas[$schema_type]['user'];
        $domain = false;
        if ($schema_type == 'msad') {
            // Allow username specification of DOMAIN\user, LDAP already
            // allows user@domain
            if (strpos($username, '\\') !== false)
                list($domain, $username) = explode('\\', $username);
            else
                $domain = $config->get('domain');
        }
        // Create the DN string for the bind based on the directory schema
        $dn = preg_replace_callback(':\{([^}]+)\}:',
            function($match) use ($username, $domain, $config) {
                switch ($match[1]) {
                case 'username':
                    return $username;
                case 'domain':
                    return $domain;
                case 'search_base':
                    if (!$config->get('search_base'))
                        return 'dc=' . implode(',dc=',
                            explode('.', $config->get('domain')));
                    // Fall through to default
                default:
                    return $config->get($match[1]);
                }
            },
            $schema['dn']
        );
        $r = $c->bind($dn, $password);
        if (!PEAR::isError($r))
            return $this->lookupAndSync($username, $dn);

        // Another effort is to search for the user
        if (!$this->_bind($c))
            return null;

        $r = $c->search(
            $this->getSearchBase(),
            str_replace(
                array('{attr}','{q}'),
                // Assume email address if the $username contains an @ sign
                array(strpos($username, '@') ? $schema['email'] : $schema['username'],
                    $username),
                $schema['lookup']),
            array('sizelimit' => 1)
        );
        if (PEAR::isError($r) || !$r->count())
            return null;

        // Attempt to bind as the DN of the user looked up with the password
        // specified
        $bound = $c->bind($r->current()->dn(), $password);
        if (PEAR::isError($bound))
            return null;

        // TODO: Save the DN in the config table so a lookup isn't necessary
        //       in the future
        return $this->lookupAndSync($username, $r->current()->dn());
    }

    /**
     * Retrieve currently configured LDAP schema, perhaps by inspecting the
     * server's advertised DSE information
     */
    function getSchema($connection) {
        $schema = $this->getConfig()->get('schema');
        if (!$schema || $schema == 'auto') {
            $dse = $connection->rootDse(array('supportedCapabilities'));
            // Microsoft Active Directory
            // http://www.alvestrand.no/objectid/1.2.840.113556.1.4.800.html
            if (($caps = $dse->getValue('supportedCapabilities'))
                    && in_array('1.2.840.113556.1.4.800', $caps)) {
                $this->getConfig()->set('schema', 'msad');
                return 'msad';
            }
        }
        elseif ($schema)
            return $schema;

        // Fallback
        return '2307';
    }

    function lookup($lookup_dn, $bind=true) {
        $c = $this->getConnection();
        if ($bind && !$this->_bind($c))
            return null;

        $schema = static::$schemas[$this->getSchema($c)];
        $schema = $schema['user'];
        $opts = array(
            'scope' => 'base',
            'sizelimit' => 1,
            'attributes' => array_filter(flatten(array(
                $schema['first'], $schema['last'], $schema['full'],
                $schema['phone'], $schema['mobile'], $schema['email'],
                $schema['username'],
            )))
        );
        $r = $c->search($lookup_dn, '(objectClass=*)', $opts);
        if (PEAR::isError($r) || !$r->count())
            return null;

        return $this->_getUserInfoArray($r->current(), $schema);
    }

    function search($query) {
        $c = $this->getConnection();
        // TODO: Include bind information
        $users = array();
        if (!$this->_bind($c))
            return $users;

        $schema = static::$schemas[$this->getSchema($c)];
        $schema = $schema['user'];
        $r = $c->search(
            $this->getSearchBase(),
            str_replace('{q}', $query, $schema['search']),
            array('attributes' => array_filter(flatten(array(
                $schema['first'], $schema['last'], $schema['full'],
                $schema['phone'], $schema['mobile'], $schema['email'],
                $schema['username'], 'dn',
            ))))
        );
        // XXX: Log or return some kind of error?
        if (PEAR::isError($r))
            return $users;

        foreach ($r as $e)
            $users[] = $this->_getUserInfoArray($e, $schema);
        return $users;
    }

    function getSearchBase() {
        $base = $this->getConfig()->get('search_base');
        if (!$base && ($domain=$this->getConfig()->get('domain')))
            $base = 'dc='.str_replace('.', ',dc=', $domain);
        return $base;
    }

    function _getValue($entry, $names) {
        foreach (array_filter(splat($names)) as $n)
            // Support multi-value attributes
            foreach (splat($entry->getValue($n, 'all')) as $val)
                // Return the first non-bool-false value of the entries
                if ($val)
                    return $val;
    }

    function _getUserInfoArray($e, $schema) {
        // Detect first and last name if only full name is given
        if (!($first = $this->_getValue($e, $schema['first']))
                || !($last = $this->_getValue($e, $schema['last']))) {
            $name = new PersonsName($this->_getValue($e, $schema['full']));
            $first = $name->getFirst();
            $last = $name->getLast();
        }
        else
            $name = "$first $last";

        return array(
            'username' => $this->_getValue($e, $schema['username']),
            'first' => $first,
            'last' => $last,
            'name' => $name,
            'email' => $this->_getValue($e, $schema['email']),
            'phone' => $this->_getValue($e, $schema['phone']),
            'mobile' => $this->_getValue($e, $schema['mobile']),
            'dn' => $e->dn(),
        );
    }

    function lookupAndSync($username, $dn) {
        switch ($this->type) {
        case 'staff':
            if (($user = StaffSession::lookup($username)) && $user->getId()) {
                if (!$user instanceof StaffSession) {
                    // osTicket <= v1.9.7 or so
                    $user = new StaffSession($user->getId());
                }
                return $user;
            }
            break;
        case 'client':
            $c = $this->getConnection();
            if ('msad' == $this->getSchema($c) && stripos($dn, ',dc=') === false) {
                // The user login DN will be user@domain. We need an LDAP DN
                // -- fetch the real DN which looks like `CN=blah,DC=`
                // NOTE: Already bound, so no need to bind again
                list($samid) = explode('@', $dn);
                $r = $c->search(
                    $this->getSearchBase(),
                    sprintf('(|(userPrincipalName=%s)(samAccountName=%s))', $dn, $samid),
                    $opts);
                if (!PEAR::isError($r) && $r->count())
                    $dn = $r->current()->dn();
            }

            // Lookup all the information on the user. Try to get the email
            // addresss as well as the username when looking up the user
            // locally.
            if (!($info = $this->lookup($dn, false)))
                return;

            $acct = false;
            foreach (array($username, $info['username'], $info['email']) as $name) {
                if ($name && ($acct = ClientAccount::lookupByUsername($name)))
                    break;
            }
            if (!$acct)
                return new ClientCreateRequest($this, $username, $info);

            if (($client = new ClientSession(new EndUser($acct->getUser())))
                    && !$client->getId())
                return;

            return $client;
        }

        // TODO: Auto-create users, etc.
    }
}

class StaffLDAPAuthentication extends StaffAuthenticationBackend
        implements AuthDirectorySearch {

    static $name = /* trans */ "Active Directory or LDAP";
    static $id = "ldap";

    function __construct($config) {
        $this->_ldap = new LDAPAuthentication($config);
        $this->config = $config;
    }

    function authenticate($username, $password=false, $errors=array()) {
        return $this->_ldap->authenticate($username, $password);
    }

    function getName() {
        $config = $this->config;
        list($__, $_N) = $config::translate();
        return $__(static::$name);
    }

    function lookup($dn) {
        $hit =  $this->_ldap->lookup($dn);
        if ($hit) {
            $hit['backend'] = static::$id;
            $hit['id'] = static::$id . ':' . $hit['dn'];
        }
        return $hit;
    }

    function search($query) {
        if (strlen($query) < 3)
            return array();

        $hits = $this->_ldap->search($query);
        foreach ($hits as &$h) {
            $h['backend'] = static::$id;
            $h['id'] = static::$id . ':' . $h['dn'];
        }
        return $hits;
    }
}

class ClientLDAPAuthentication extends UserAuthenticationBackend {
    static $name = /* trans */ "Active Directory or LDAP";
    static $id = "ldap.client";

    function __construct($config) {
        $this->_ldap = new LDAPAuthentication($config, 'client');
        $this->config = $config;
        if ($domain = $config->get('domain'))
            self::$name .= sprintf(' (%s)', $domain);
    }

    function getName() {
        $config = $this->config;
        list($__, $_N) = $config::translate();
        return $__(static::$name);
    }

    function authenticate($username, $password=false, $errors=array()) {
        $object = $this->_ldap->authenticate($username, $password);
        if ($object instanceof ClientCreateRequest)
            $object->setBackend($this);
        return $object;
    }
}

require_once(INCLUDE_DIR.'class.plugin.php');
require_once('config.php');
class LdapAuthPlugin extends Plugin {
    var $config_class = 'LdapConfig';

    function bootstrap() {
        $config = $this->getConfig();
        if ($config->get('auth-staff'))
            StaffAuthenticationBackend::register(new StaffLDAPAuthentication($config));
        if ($config->get('auth-client'))
            UserAuthenticationBackend::register(new ClientLDAPAuthentication($config));
    }
}

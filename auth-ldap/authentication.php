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
                'avatar' => array('jpegPhoto', 'thumbnailPhoto'),
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
                'avatar' => 'jpegPhoto',
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

    static function lookupDnsWithServers($domain, $dns=array()) {
        require_once(PEAR_DIR.'Net/DNS2.php');
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
        return $servers;
    }

    static function lookupDnsWindows($domain) {
        $servers = array();
        if (!($answers = dns_get_record('_ldap._tcp.'.$domain, DNS_SRV)))
            return $servers;

        foreach ($answers as $srv) {
            $servers[] = array(
                'host' => "{$srv['target']}:{$srv['port']}",
                'priority' => $srv['pri'],
                'weight' => $srv['weight'],
            );
        }
        return $servers;
    }

    /**
     * Discover Active Directory LDAP servers using DNS.
     *
     * Parameters:
     * $domain - AD domain
     * $dns - DNS server hints (optional)
     * $closestOnly - Return at most one server which is definitely
     *      available and represents the first to respond of all the servers
     *      discovered in DNS.
     *
     * References:
     * "DNS-Based Discovery" (Microsoft)
     * https://msdn.microsoft.com/en-us/library/cc717360.aspx
     */
    static function autodiscover($domain, $dns=array(), $closestOnly=false,
        $config=null
    ) {
        if (!$dns && stripos(PHP_OS, 'WIN') === 0) {
            // Net_DNS2_Resolver won't work on windows servers without DNS
            // specified
            // TODO: Lookup DNS server from hosts file if not set 
            $servers = self::lookupDnsWindows($domain);
        }
        else {
            $servers = self::lookupDnsWithServers($domain, $dns);
        }
        // Sort by priority and weight
        // priority ASC, then weight DESC
        usort($servers, function($a, $b) {
            return ($a['priority'] << 15) - $a['weight']
                - ($b['priority'] << 15) + $b['weight'];
        });
        // Locate closest domain controller (if requested)
        if ($closestOnly) {
            // If there are no servers from DNS, but there is one saved in the
            // config, return that one
            if (count($servers) === 0
                && $config && ($T = $config->get('closest'))
            ) {
                return array($T);
            }
            if (is_int($idx = self::findClosestLdapServer($servers, $config))) {
                return array($servers[$idx]);
            }
        }
        return $servers;
    }

    /**
     * Discover the closest LDAP server based on apparent TCP connect
     * timing. This method will attempt parallel, asynchronous connections
     * to all received LDAP servers and return the array index of the
     * first-respodning server.
     *
     * Returns:
     * (int|false|null) - index into the received servers list for the
     * first-responding server. NULL if no servers responded in a few
     * seconds, and FALSE if the socket extension is not loaded for this
     * PHP setup.
     *
     * References:
     * "Finding a Domain Controller in the Closest Site" (Microsoft)
     * https://technet.microsoft.com/en-us/library/cc978016.aspx
     *
     * "here's how you can implement timeouts with the socket functions"
     * (PHP, rbarnes at fake dot com)
     * http://us3.php.net/manual/en/function.socket-connect.php#84465
     */
    static function findClosestLdapServer($servers, $config=false,
        $defl_port=389
    ) {
        if (!function_exists('socket_create'))
            return false;

        // If there's only one selection, then it must be the fastest
        reset($servers);
        if (count($servers) < 2)
            return key($servers);

        // Start with last-used closest server
        if ($config && ($T = $config->get('closest'))) {
            foreach ($servers as $i=>$S) {
                if ($T == $S['host']) {
                    // Move this server to the front of the list (but don't
                    // change the indexing
                    $servers = array($i=>$S) + $servers;
                    break;
                }
            }
        }

        $sockets = array();
        $closest = null;
        $loops = 100; # ~50ms seconds max
        while (!$closest && $loops--) {
            // Look for a successful connection
            foreach ($sockets as $i=>$X) {
                list($sk, $host, $port) = $X;
                if (@socket_connect($sk, $host, $port)) {
                    // Connected!!!
                    $closest = $i;
                    break;
                }
                else {
                    $error = socket_last_error();
                    if (!in_array($error, array(SOCKET_EINPROGRESS, SOCKET_EALREADY))) {
                        // Bad mojo
                        socket_close($sk);
                        unset($sockets[$i]);
                    }
                }
            }
            // Look for another server
            list($i, $S) = each($servers);
            if ($S) {
                // Add another socket to the list
                // Lookup IP address for host
                list($host, $port) = explode(':', $S['host'], 2);
                if (!@inet_pton($host)) {
                    if ($host == ($ip = gethostbyname($host))) {
                        continue;
                    }
                    $host = $ip;
                }
                // Start an async connect to this server
                if (!($sk = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)))
                    continue;

                socket_set_nonblock($sk);

                $sockets[$i] = array($sk, $host, $port ?: $defl_port);
            }
            // Microsoft recommends waiting 0.1s; however, we're in the
            // business of providing quick response times
            usleep(500);
        }
        foreach ($sockets as $X) {
            list($sk) = $X;
            socket_close($sk);
        }
        // Save closest server for faster response next time
        if ($config) {
            $config->set('closest', $servers[$closest]['host']);
        }
        return $closest;
    }

    function getServers() {
        if (!($servers = $this->getConfig()->get('servers'))
                || !($servers = preg_split('/\s+/', $servers))) {
            if ($domain = $this->getConfig()->get('domain')) {
                $dns = preg_split('/,?\s+/', $this->getConfig()->get('dns'));
                return self::autodiscover($domain, array_filter($dns),
                    true, $this->getConfig());
            }
        }
        if ($servers) {
            $hosts = array();
            foreach ($servers as $h)
                if (preg_match('/([^:]+):(\d{1,4})/', $h, $matches))
                    $hosts[] = array('host' => $matches[1], 'port' => (int) $matches[2]);
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
            @list($host, $port) = explode(':', $s['host'], 2);
            if ($port) {
                $s['port'] = $port;
                $s['host'] = $host;
            }
            $params = $defaults + $s;
            $c = new Net_LDAP2($params);
            if ($this->_bind($c)) {
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

if (defined('MAJOR_VERSION') && version_compare(MAJOR_VERSION, '1.10', '>=')) {
    require_once INCLUDE_DIR . 'class.avatar.php';

    class LdapAvatarSource
    extends AvatarSource {
        static $id = 'ldap';
        static $name = 'LDAP and Active Directory';

        static $config;

        function getAvatar($user) {
            return new LdapAvatar($user);
        }

        static function registerUrl($config) {
            static::$config = $config;
            Signal::connect('api', function($dispatcher) {
                $dispatcher->append(
                    url_get('^/ldap/avatar$', array('LdapAvatarSource', 'tryFetchAvatar'))
                );
            });
        }

        static function tryFetchAvatar() {
            static::fetchAvatar();
            // if fetchAvatar is successful, then it won't return
            Http::redirect(ROOT_PATH.'images/mystery-oscar.png');
        }

        static function fetchAvatar() {
            $ldap = new LDAPAuthentication(static::$config);

            if (!($c = $ldap->getConnection()))
                return null;

            // This requires a search user to be defined
            if (!$ldap->_bind($c))
                return null;

            $schema_type = $ldap->getSchema($c);
            $schema = $ldap::$schemas[$schema_type]['user'];
            list($email, $username) =
                Net_LDAP2_Util::escape_filter_value(array(
                    $_GET['email'], $_GET['username']));

            $r = $c->search(
                $ldap->getSearchBase(),
                sprintf('(|(%s=%s)(%s=%s))', $schema['email'], $email,
                    $schema['username'], $username),
                array(
                    'sizelimit' => 1,
                    'attributes' => array_filter(flatten(array(
                        $schema['avatar']
                    ))),
                )
            );
            if (PEAR::isError($r) || !$r->count())
                return null;

            if (!($avatar = $ldap->_getValue($r->current(), $schema['avatar'])))
                return null;

            // Ensure the value is cacheable
            $etag = md5($avatar);
            Http::cacheable($etag, false, 86400);
            Http::response(200, $avatar, 'image/jpeg', false);
        }
    }

    class LdapAvatar
    extends Avatar {
        function getUrl($size) {
            $user = $this->user;
            $acct = $user instanceof User
                ? $this->user->getAccount()
                : $user;
            return ROOT_PATH . 'api/ldap/avatar?'
                .Http::build_query(array(
                    'email' => $this->user->getEmail(),
                    'username' => $acct ? $acct->username : '',
                ));
        }
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
        if (class_exists('LdapAvatarSource')) {
            AvatarSource::register('LdapAvatarSource');
            LdapAvatarSource::registerUrl($config);
        }
    }
}

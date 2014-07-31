<?php

class Option {

    var $default = false;

    function __construct($options=false) {
        list($this->short, $this->long) = array_slice($options, 0, 2);
        $this->help = (isset($options['help'])) ? $options['help'] : "";
        $this->action = (isset($options['action'])) ? $options['action']
            : "store";
        $this->dest = (isset($options['dest'])) ? $options['dest']
            : substr($this->long, 2);
        $this->type = (isset($options['type'])) ? $options['type']
            : 'string';
        $this->const = (isset($options['const'])) ? $options['const']
            : null;
        $this->default = (isset($options['default'])) ? $options['default']
            : null;
        $this->metavar = (isset($options['metavar'])) ? $options['metavar']
            : 'var';
        $this->nargs = (isset($options['nargs'])) ? $options['nargs']
            : 1;
    }

    function hasArg() {
        return $this->action != 'store_true'
            && $this->action != 'store_false';
    }

    function handleValue(&$destination, $args) {
        $nargs = 0;
        $value = ($this->hasArg()) ? array_shift($args) : null;
        if ($value[0] == '-')
            $value = null;
        elseif ($value)
            $nargs = 1;
        if ($this->type == 'int')
            $value = (int)$value;
        switch ($this->action) {
            case 'store_true':
                $value = true;
                break;
            case 'store_false':
                $value = false;
                break;
            case 'store_const':
                $value = $this->const;
                break;
            case 'append':
                if (!isset($destination[$this->dest]))
                    $destination[$this->dest] = array($value);
                else {
                    $T = &$destination[$this->dest];
                    $T[] = $value;
                    $value = $T;
                }
                break;
            case 'store':
            default:
                break;
        }
        $destination[$this->dest] = $value;
        return $nargs;
    }

    function toString() {
        $short = explode(':', $this->short);
        $long = explode(':', $this->long);
        if ($this->nargs === '?')
            $switches = sprintf('    %s [%3$s], %s[=%3$s]', $short[0],
                $long[0], $this->metavar);
        elseif ($this->hasArg())
            $switches = sprintf('    %s %3$s, %s=%3$s', $short[0], $long[0],
                $this->metavar);
        else
            $switches = sprintf("    %s, %s", $short[0], $long[0]);
        $help = preg_replace('/\s+/', ' ', $this->help);
        if (strlen($switches) > 23)
            $help = "\n" . str_repeat(" ", 24) . $help;
        else
            $switches = str_pad($switches, 24);
        $help = wordwrap($help, 54, "\n" . str_repeat(" ", 24));
        return $switches . $help;
    }
}

class OutputStream {
    var $stream;

    function __construct($stream) {
        $this->stream = fopen($stream, 'w');
    }

    function write($what) {
        fwrite($this->stream, $what);
    }
}

class Module {

    var $options = array();
    var $arguments = array();
    var $prologue = "";
    var $epilog = "";
    var $usage = '$script [options] $args [arguments]';
    var $autohelp = true;
    var $module_name;

    var $stdout;
    var $stderr;

    var $_options;
    var $_args;

    function __construct() {
        $this->options['help'] = array("-h","--help",
            'action'=>'store_true',
            'help'=>"Display this help message");
        foreach ($this->options as &$opt)
            $opt = new Option($opt);
        $this->stdout = new OutputStream('php://output');
        $this->stderr = new OutputStream('php://stderr');
    }

    function showHelp() {
        if ($this->prologue)
            echo $this->prologue . "\n\n";

        global $argv;
        $manager = @$argv[0];

        echo "Usage:\n";
        echo "    " . str_replace(
                array('$script', '$args'),
                array($manager ." ". $this->module_name, implode(' ', array_keys($this->arguments))),
            $this->usage) . "\n";

        ksort($this->options);
        if ($this->options) {
            echo "\nOptions:\n";
            foreach ($this->options as $name=>$opt)
                echo $opt->toString() . "\n";
        }

        if ($this->arguments) {
            echo "\nArguments:\n";
            foreach ($this->arguments as $name=>$help) {
                $extra = '';
                if (isset($help['options']) && is_array($help['options'])) {
                    foreach($help['options'] as $op=>$desc)
                        $extra .= wordwrap(
                            "\n        $op - $desc", 76, "\n            ");
                }
                $help = $help['help'];
                echo $name . "\n    " . wordwrap(
                    preg_replace('/\s+/', ' ', $help), 76, "\n    ")
                        .$extra."\n";
            }
        }

        if ($this->epilog) {
            echo "\n\n";
            $epilog = preg_replace('/\s+/', ' ', $this->epilog);
            echo wordwrap($epilog, 76, "\n");
        }

        echo "\n";
    }

    function fail($message, $showhelp=false) {
        $this->stderr->write($message . "\n");
        if ($showhelp)
            $this->showHelp();
        die();
    }

    function getOption($name, $default=false) {
        $this->parseOptions();
        if (isset($this->_options[$name]))
            return $this->_options[$name];
        elseif (isset($this->options[$name]) && $this->options[$name]->default)
            return $this->options[$name]->default;
        else
            return $default;
    }

    function getArgument($name, $default=false) {
        $this->parseOptions();
        if (isset($this->_args[$name]))
            return $this->_args[$name];
        return $default;
    }

    function parseOptions() {
        if (is_array($this->_options))
            return;

        global $argv;
        list($this->_options, $this->_args) =
            $this->parseArgs(array_slice($argv, 1));

        foreach (array_keys($this->arguments) as $idx=>$name) {
            if (!is_array($this->arguments[$name]))
                $this->arguments[$name] = array(
                    'help' => $this->arguments[$name]);
            $this->arguments[$name]['idx'] = $idx;
        }

        foreach ($this->arguments as $name=>$info) {
            if (!isset($this->_args[$info['idx']])) {
                if (isset($info['required']) && !$info['required'])
                    continue;
                $this->optionError($name . " is a required argument");
            }
            else {
                $this->_args[$name] = &$this->_args[$info['idx']];
            }
        }

        foreach ($this->options as $name=>$opt)
            if (!isset($this->_options[$name]))
                $this->_options[$name] = $opt->default;

        if ($this->autohelp && $this->getOption('help')) {
            $this->showHelp();
            die();
        }
    }

    function optionError($error) {
        echo "Error: " . $error . "\n\n";
        $this->showHelp();
        die();
    }

    function _run($module_name) {
        $this->module_name = $module_name;
        $this->parseOptions();
        return $this->run($this->_args, $this->_options);
    }

    /* abstract */
    function run($args, $options) {
    }

    /* static */
    function register($action, $class) {
        global $registered_modules;
        $registered_modules[$action] = new $class();
    }

    /* static */ function getInstance($action) {
        global $registered_modules;
        return $registered_modules[$action];
    }

    function parseArgs($argv) {
        $options = $args = array();
        $argv = array_slice($argv, 0);
        while ($arg = array_shift($argv)) {
            if (strpos($arg, '=') !== false) {
                list($arg, $value) = explode('=', $arg, 2);
                array_unshift($argv, $value);
            }
            $found = false;
            foreach ($this->options as $opt) {
                if ($opt->short == $arg || $opt->long == $arg) {
                    if ($opt->handleValue($options, $argv))
                        array_shift($argv);
                    $found = true;
                }
            }
            if (!$found && $arg[0] != '-')
                $args[] = $arg;
        }
        return array($options, $args);
    }
}

class PluginBuilder extends Module {
    var $prologue =
        "Inspects, tests, and builds a plugin PHAR file";

    var $arguments = array(
        'action' => array(
            'help' => "What to do with the plugin",
            'options' => array(
                'build' => 'Compile a PHAR file for a plugin',
                'hydrate' => 'Prep plugin folders for embedding in osTicket directly',
                'list' => 'List the contents of a phar file',
                'unpack' => 'Unpack a PHAR file (similar to unzip)',
            ),
        ),
        'plugin' => array(
            'help' => "Plugin to be compiled",
            'required' => false
        ),
    );

    var $options = array(
        'sign' => array('-S','--sign', 'metavar'=>'KEY', 'help'=>
            'Sign the compiled PHAR file with the provided OpenSSL private
            key file'),
        'verbose' => array('-v','--verbose','help'=>
            'Be more verbose','default'=>false, 'action'=>'store_true'),
        'compress' => array('-z', '--compress', 'help' =>
            'Compress source files when hydrading and building. Useful for
            saving space when building PHAR files',
            'action'=>'store_true', 'default'=>false),
        "key" => array('-k','--key','metavar'=>'API-KEY',
            'help'=>'Crowdin project API key.'),
        'osticket' => array('-R', '--osticket', 'metavar'=>'ROOT',
            'help'=>'Root of osTicket installation (required for language compilation)'),
    );

    static $project = 'osticket-plugins';
    static $crowdin_api_url = 'http://i18n.osticket.com/api/project/{project}/{command}';

    function run($args, $options) {
        $this->key = $options['key'];
        if (!$this->key && defined('CROWDIN_API_KEY'))
            $this->key = CROWDIN_API_KEY;

        if (@$options['osticket']) {
            require $options['osticket'] . '/include/class.translation.php';
        }

        switch (strtolower($args['action'])) {
        case 'build':
            $plugin = $args['plugin'];

            if (!file_exists($plugin))
                $this->fail("Plugin folder '$plugin' does not exist");

            $this->_build($plugin, $options);
            break;

        case 'hydrate':
            $this->_hydrate($options);
            break;
        case 'list':
            $P = new Phar($args[1]);
            $base = realpath($args[1]);
            foreach (new RecursiveIteratorIterator($P) as $finfo) {
                $name = str_replace('phar://'.$base.'/', '', $finfo->getPathname());
                $this->stdout->write($name . "\n");
            }
            break;

        case 'list':
            $plugin = $args['plugin'];
            if (!file_exists($plugin))
                $this->fail("PHAR file '$plugin' does not exist");

            $p = new Phar($plugin);
            $total = 0;
            foreach (new RecursiveIteratorIterator($p) as $info) {
                $this->stdout->write(sprintf(
                    "% 10.10d  %s  %s\n",
                    $info->getSize(),
                    strftime('%x %X', $info->getMTime()),
                    str_replace(
                        array('phar://', realpath($plugin).'/'),
                        array('',''),
                        (string) $info)));
                $total += $info->getSize();
            }
            $this->stdout->write("---------------------------------------\n");
            $this->stdout->write(sprintf("% 10.10d\n", $total));
            break;

        default:
            $this->fail("Unsupported MAKE action. See help");
        }
    }

    function _build($plugin, $options) {
        @unlink("$plugin.phar");
        $phar = new Phar("$plugin.phar");
        $phar->startBuffering();

        if ($options['sign']) {
            if (!function_exists('openssl_get_privatekey'))
                $this->fail('OpenSSL extension required for signing');
            $private = openssl_get_privatekey(
                    file_get_contents($options['sign']));
            $pkey = '';
            openssl_pkey_export($private, $pkey);
            $phar->setSignatureAlgorithm(Phar::OPENSSL, $pkey);
        }

        // Read plugin info
        $info = (include "$plugin/plugin.php");

        $this->resolveDependencies(false);

        $phar->buildFromDirectory($plugin);

        // Add library dependencies
        if (isset($info['requires'])) {
            $includes = array();
            foreach ($info['requires'] as $lib=>$info) {
                if (!isset($info['map']))
                    continue;
                foreach ($info['map'] as $lib=>$local) {
                    $phar_path = trim($local, '/').'/';
                    $full = rtrim(dirname(__file__).'/lib/'.$lib,'/').'/';
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($full),
                            RecursiveIteratorIterator::SELF_FIRST);
                    foreach ($files as $f) {
                        if (file_exists("$plugin/$phar_path"))
                            // Hydrated
                            continue;
                        elseif ($f->isDir())
                            // Unnecessary
                            continue;
                        elseif (preg_match('`/tests?/`i', $f->getPathname()))
                            // Don't package tests
                            // XXX: Add a option to override this
                            continue;
                        $content = '';
                        $local = str_replace($full, $phar_path, $f->getPathname());
                        if ($options['compress'] && fnmatch('*.php', $f->getPathname())) {
                            $p = popen('php -w '.realpath($f->getPathname()), 'r');
                            while ($b = fread($p, 8192))
                                $content .= $b;
                            fclose($p);
                            $phar->addFromString($local, $content);
                        }
                        else {
                            $phar->addFile($f->getPathname(), $local);
                        }
                    }
                }
            }
        }

        // Add language files
        if (@$this->key) {
            foreach ($this->getLanguageFiles($plugin) as $name=>$content) {
                $name = ltrim($name, '/');
                if (!$content) continue;
                $phar->addFromString("i18n/{$name}", $content);
            }
        }
        else {
            $this->stderr->write("Specify Crowdin API key to integrate language files\n");
        }

        $phar->setStub('<?php __HALT_COMPILER();');
        $phar->stopBuffering();
    }

    function _hydrate($options) {
        $this->resolveDependencies();

        // Move things into place
        foreach (glob(dirname(__file__).'/*/plugin.php') as $plugin) {
            $p = (include $plugin);
            if (!isset($p['requires']) || !is_array($p['requires']))
                continue;
            foreach ($p['requires'] as $lib=>$info) {
                if (!isset($info['map']) || !is_array($info['map']))
                    continue;
                foreach ($info['map'] as $lib=>$local) {
                    $source = dirname(__file__).'/lib/'.$lib;
                    $dest = dirname($plugin).'/'.$local;
                    if ($this->options['verbose']) {
                        $left = str_replace(dirname(__file__).'/', '', $source);
                        $right = str_replace(dirname(__file__).'/', '', $dest);
                        $this->stdout->write("Hydrating :: $left => $right\n");
                    }
                    if (is_file($source)) {
                        copy($left, $right);
                        continue;
                    }
                    foreach (
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::SELF_FIRST) as $item
                    ) {
                        if ($item->isDir())
                            continue;

                        $target = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
                        $parent = dirname($target);
                        if (!file_exists($parent))
                            mkdir($parent, 0777, true);
                        // Compress PHP files
                        if ($options['compress'] && fnmatch('*.php', $item)) {
                            $p = popen('php -w '.realpath($item), 'r');
                            $T = fopen($target, 'w');
                            while ($b = fread($p, 8192))
                                fwrite($T, $b);
                            fclose($p);
                            fclose($T);
                        }
                        else {
                            copy($item, $target);
                        }
                    }
                }
                // TODO: Fetch language files for this plugin
            }
        }
    }

    function _http_get($url) {
        #curl post
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'osTicket-cli');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result=curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array($code, $result);
    }

    function _crowdin($command, $args=array()) {

        $url = str_replace(array('{command}', '{project}'),
            array($command, self::$project),
            self::$crowdin_api_url);

        $args += array('key' => $this->key);
        foreach ($args as &$a)
            $a = urlencode($a);
        unset($a);
        $url .= '?' . http_build_query($args);

        return $this->_http_get($url);
    }

    function getTranslations() {
        error_reporting(E_ALL);
        list($code, $body) = $this->_crowdin('status');
        $langs = array();

        if ($code != 200) {
            $this->stderr->write($code.": Bad status from Crowdin fetching translations\n");
            return $langs;
        }
        $d = new DOMDocument();
        $d->loadXML($body);

        $xp = new DOMXpath($d);
        foreach ($xp->query('//language') as $c) {
            $name = $code = '';
            foreach ($c->childNodes as $n) {
                switch (strtolower($n->nodeName)) {
                case 'name':
                    $name = $n->textContent;
                    break;
                case 'code':
                    $code = $n->textContent;
                    break;
                }
            }
            if (!$code)
                continue;
            $langs[] = $code;
        }
        return $langs;
    }

    function getLanguageFiles($plugin) {
        $files = array();
        if (!class_exists('Translation'))
            $this->stderr->write("Specify osTicket root path to compile MO files\n");

        foreach ($this->getTranslations() as $lang) {
            list($code, $stuff) = $this->_crowdin("download/$lang.zip");
            if ($code != 200) {
                $this->stderr->write("$lang: Unable to download language files\n");
                continue;
            }

            $lang = str_replace('-','_',$lang);

            // Extract a few files from the zip archive
            $temp = tempnam('/tmp', 'osticket-cli');
            $f = fopen($temp, 'w');
            fwrite($f, $stuff);
            fclose($f);
            $zip = new ZipArchive();
            $zip->open($temp);
            unlink($temp);

            for ($i=0; $i<$zip->numFiles; $i++) {
                $info = $zip->statIndex($i);
                if (strpos($info['name'], $plugin) === 0) {
                    $name = substr($info['name'], strlen($plugin));
                    $name = ltrim($name, '/');
                    if (substr($name, -3) == '.po' && class_exists('Translation')) {
                        $content = $this->buildMo($zip->getFromIndex($i));
                        $name = substr($name, 0, -3) . '.mo.php';
                    }
                    else {
                        $content = $zip->getFromIndex($i);
                    }
                    // Files in the plugin are laid out by (lang)/(file),
                    // where (file) has the plugin name removed. Files on
                    // Crowdin are organized by (plugin)/file
                    $files["$lang/{$name}"] = $content;
                }
            }
            $zip->close();
        }
        return $files;
    }

    function buildMo($po_contents) {
        $pipes = array();
        $msgfmt = proc_open('msgfmt -o- -',
            array(0=>array('pipe','r'), 1=>array('pipe','w')),
            $pipes);
        if (is_resource($msgfmt)) {
            fwrite($pipes[0], $po_contents);
            fclose($pipes[0]);
            $mo_input = fopen('php://temp', 'r+b');
            fwrite($mo_input, stream_get_contents($pipes[1]));
            rewind($mo_input);
            $mo = Translation::buildHashFile($mo_input, false, true);
            fclose($mo_input);
        }
        return $mo;
    }

    function ensureComposer() {
        if (file_exists(dirname(__file__).'/composer.phar'))
            return true;

        return static::getComposer();
    }

    function getComposer() {
        list($code, $phar) = $this->_http_get('https://getcomposer.org/composer.phar');

        if (!($fp = fopen(dirname(__file__).'/composer.phar', 'wb')))
            $this->fail('Cannot install composer: Unable to write "composer.phar"');

        fwrite($fp, $phar);
        fclose($fp);
    }

    function resolveDependencies($autoupdate=true) {
        // Build dependency list
        $requires = array();
        foreach (glob(dirname(__file__).'/*/plugin.php') as $plugin) {
            $p = (include $plugin);
            if (isset($p['requires']))
                foreach ($p['requires'] as $lib=>$info)
                    $requires[$lib] = $info['version'];
        }

        // Write composer.json file
        $composer = <<<EOF
{
    "name": "osTicket/core-plugins",
    "repositories": [
        {
            "type": "pear",
            "url": "http://pear.php.net"
        }
    ],
    "require": %s,
    "config": {
        "vendor-dir": "lib"
    }
}
EOF;
        $composer = sprintf($composer, json_encode($requires));

        if (!($fp = fopen('composer.json', 'w')))
            $this->fail('Unable to save "composer.json"');

        fwrite($fp, $composer);
        fclose($fp);

        $this->ensureComposer();

        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        if (file_exists(dirname(__file__)."/composer.lock")) {
            if ($autoupdate)
                passthru($php." ".dirname(__file__)."/composer.phar -v update");
        }
        else
            passthru($php." ".dirname(__file__)."/composer.phar -v install");
    }
}
$registered_modules = array();

if (php_sapi_name() != "cli")
    die("Management only supported from command-line\n");

$builder = new PluginBuilder();
$builder->parseOptions();
$builder->_run(basename(__file__));

?>

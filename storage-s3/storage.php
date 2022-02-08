<?php

use Aws\S3\Exception\SignatureDoesNotMatchException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Stream;
require_once INCLUDE_DIR . 'class.json.php';
require_once 'lib/Aws/functions.php';
require_once 'lib/GuzzleHttp/functions.php';

class S3StorageBackend extends FileStorageBackend {
    static $desc;

    static $config;
    static $__config;
    private $body;
    private $upload_hash;
    private $upload_hash_final;
    static $version = '2006-03-01';
    static $sig_vers = 'v4';

    static $blocksize = 8192; # Default read size for sockets

    static function setConfig($config) {
        static::$config = $config->getInfo();
        static::$__config = $config;
    }
    function getConfig() {
        return static::$__config;
    }

    function __construct($meta) {
        parent::__construct($meta);
        $credentials['credentials'] = array(
            'key' => static::$config['aws-key-id'],
            'secret' => Crypto::decrypt(static::$config['secret-access-key'],
                SECRET_SALT, static::getConfig()->getNamespace()),
        );
        if (static::$config['aws-region'])
            $credentials['region'] = static::$config['aws-region'];

        $credentials['version'] = self::$version;
        $credentials['signature_version'] = self::$sig_vers;

        $this->client = new S3Client($credentials);
    }

    function read($bytes=false, $offset=0) {
        try {
            if (!$this->body)
                $this->openReadStream();
            // Reads may be cut short to 8k. Try to read $bytes if at all
            // possible.
            $chunk = '';
            $bytes = $bytes ?: self::getBlockSize();
            while (strlen($chunk) < $bytes) {
                $buf = $this->body->read($bytes - strlen($chunk));
                if (!$buf) break;
                $chunk .= $buf;
            }
            return $chunk;
        }
        catch (Aws\S3\Exception\NoSuchKeyException $e) {
            throw new IOException(self::getKey()
                .': Unable to locate file: '.(string)$e);
        }
    }

    function passthru() {
        try {
            while ($block = $this->read())
                print $block;
        }
        catch (Aws\S3\Exception\NoSuchKeyException $e) {
            throw new IOException(self::getKey()
                .': Unable to locate file: '.(string)$e);
        }
    }

    function write($block) {
        if (!$this->body)
            $this->openWriteStream();
        if (!isset($this->upload_hash))
            $this->upload_hash = hash_init('md5');
        hash_update($this->upload_hash, $block);
        return $this->body->write($block);
    }

    function flush() {
        return $this->upload($this->body);
    }

    function upload($filepath) {
        if ($filepath instanceof Stream) {
            $filepath->rewind();
            // Hashing already performed in the ::write() method
        }
        elseif (is_string($filepath)) {
            $this->upload_hash = hash_init('md5');
            hash_update_file($this->upload_hash, $filepath);
            $filepath = fopen($filepath, 'r');
            rewind($filepath);
        }

        try {
            $params = array(
                'ContentType' => $this->meta->getType(),
                'CacheControl' => 'private, max-age=86400',
            );
            if (isset($this->upload_hash))
                $params['Content-MD5'] =
                    $this->upload_hash_final = hash_final($this->upload_hash);

            $info = $this->client->upload(
                static::$config['bucket'],
                self::getKey(true),
                $filepath,
                static::$config['acl'] ?: 'private',
                array('params' => $params)
            );
            return true;
        }
        catch (S3Exception $e) {
            throw new IOException('Unable to upload to S3: '.(string)$e);
        }
        return false;
    }

    // Support MD5 hash via the returned ETag header;
    function getNativeHashAlgos() {
        return array('md5');
    }

    function getHashDigest($algo) {
        if ($algo == 'md5' && isset($this->upload_hash_final))
            return $this->upload_hash_final;

        // Return nothing. The migrater will compute the hash by downloading
        // the object contents
    }

    // Send a redirect when the file is requested locally
    function sendRedirectUrl($disposition='inline', $ttl = false) {
        // expire based on ttl (if given), otherwise expire at midnight
        $now = time();
        $ttl = $ttl ? $now + $ttl : ($now + 86400 - ($now % 86400));
        Http::redirect($this->getSignedRequest(
            $this->client->getCommand('GetObject', [
                'Bucket' => static::$config['bucket'],
                'Key'    => self::getKey(),
                'ResponseContentDisposition' => sprintf("%s; %s;",
                    $disposition,
                    Http::getDispositionFilename($this->meta->getName())),
            ]), $ttl)->getUri());
        return true;
    }

    function unlink() {
        try {
            $this->client->deleteObject(array(
                'Bucket' => static::$config['bucket'],
                'Key'    => self::getKey()
            ));
            return true;
        }
        catch (S3Exception $e) {
            throw new IOException('Unable to remove object: '
                . (string) $e);
        }
    }

    // Adapted from Aws\S3\StreamWrapper
    /**
     * Create a pre-signed Request for the given S3 command object.
     *
     * @param Aws\CommandInterface          $command Command to create a pre-signed
     *                                               URL for.
     * @param int|string|\DateTimeInterface $expires The time at which the URL should
     *                                               expire. This can be a Unix
     *                                               timestamp, a PHP DateTime object,
     *                                               or a string that can be evaluated
     *                                               by strtotime().
     *
     * @return RequestInterface
     */
    protected function getSignedRequest($command, $expires=0)
    {
        return $this->client->createPresignedRequest($command, $expires ?: '+30 minutes');
    }

    /**
     * Initialize the stream wrapper for a read only stream
     *
     * @return bool
     */
    protected function openReadStream() {
        $this->getBody(true);
        return true;
    }

    /**
     * Initialize the stream wrapper for a read/write stream
     */
    protected function openWriteStream() {
        $this->body = new Stream(fopen('php://temp', 'r+'));
    }

    protected function getBody($stream=false) {
        $params = array(
            'Bucket' => static::$config['bucket'],
            'Key'    => self::getKey(),
        );

        $command = $this->client->getCommand('GetObject', $params);
        $command['@http']['stream'] = $stream;
        $result = $this->client->execute($command);
        $this->body = $result['Body'];

        // Wrap the body in a caching entity body if seeking is allowed
        //if ($this->getOption('seekable') && !$this->body->isSeekable()) {
        //    $this->body = new CachingStream($this->body);
        //}
        return $this->body;
    }

    function getKey($create=false) {
        $attrs = $create ? self::getAttrs() : $this->meta->getAttrs();
        $attrs = JsonDataParser::parse($attrs);

        $key = ($attrs && $attrs['folder']) ?
            sprintf('%s/%s', $attrs['folder'], $this->meta->getKey()) :
            $this->meta->getKey();

        return $key;
    }

    function getAttrs() {
        $bucket = static::$config['bucket'];
        $folder = (static::$config['folder'] ? static::$config['folder'] : '');
        $attr = JsonDataEncoder::encode(array('bucket' => $bucket, 'folder' => $folder));

        return $attr;
    }
}

require_once 'config.php';

class S3StoragePlugin extends Plugin {
    var $config_class = 'S3StoragePluginConfig';

    function bootstrap() {
        require_once 'storage.php';
        S3StorageBackend::setConfig($this->getConfig());
        S3StorageBackend::$desc = sprintf('S3 (%s)', $this->getConfig()->get('bucket'));
        FileStorageBackend::register('3', 'S3StorageBackend');
    }
}

require_once INCLUDE_DIR . 'UniversalClassLoader.php';
use Symfony\Component\ClassLoader\UniversalClassLoader_osTicket;
$loader = new UniversalClassLoader_osTicket();
$loader->registerNamespaceFallbacks(array(
    dirname(__file__).'/lib'));
$loader->register();

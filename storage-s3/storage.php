<?php

use Aws\S3\Exception\SignatureDoesNotMatchException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use Aws\S3\S3Client;
use Guzzle\Http\EntityBody;
use Guzzle\Stream\PhpStreamRequestFactory;

class S3StorageBackend extends FileStorageBackend {
    static $desc;

    static $config;
    static $__config;
    private $body;
    private $upload_hash;
    private $upload_hash_final;

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
        $credentials = array(
            'key' => static::$config['aws-key-id'],
            'secret' => Crypto::decrypt(static::$config['secret-access-key'],
                SECRET_SALT, static::getConfig()->getNamespace()),
        );
        if ($config['aws-region'])
            $credentials['region'] = $config['aws-region'];

        $this->client = S3Client::factory($credentials);
    }

    function read($bytes=false) {
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
            throw new IOException($this->meta->getKey()
                .': Unable to locate file: '.(string)$e);
        }
    }

    function fpassthru() {
        try {
            $res = $this->client->getObject(array(
                'Bucket' => static::$config['bucket'],
                'Key' => $this->meta->getKey(),
            ));
            fpassthru($res['Body']);
        }
        catch (Aws\S3\Exception\NoSuchKeyException $e) {
            throw new IOException($this->meta->getKey()
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
        if ($filepath instanceof EntityBody) {
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
                $this->meta->getKey(),
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
    function sendRedirectUrl($disposition='inline') {
        $now = time();
        Http::redirect($this->client->getObjectUrl(
            static::$config['bucket'],
            $this->meta->getKey(),
            $now + 86400 - ($now % 86400), # Expire at midnight
            array(
                'ResponseContentDisposition' => sprintf("%s; %s;",
                    $disposition,
                    Http::getDispositionFilename($this->meta->getName())),
            )));
        return true;
    }

    function unlink() {
        try {
            $this->client->deleteObject(array(
                'Bucket' => static::$config['bucket'],
                'Key' => $this->meta->getKey()
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
     * Serialize and sign a command, returning a request object
     *
     * @param CommandInterface $command Command to sign
     *
     * @return RequestInterface
     */
    protected function getSignedRequest($command)
    {
        $request = $command->prepare();
        $request->dispatch('request.before_send',
            array('request' => $request));

        return $request;
    }

    /**
     * Initialize the stream wrapper for a read only stream
     *
     * @param array $params Operation parameters
     * @param array $errors Any encountered errors to append to
     *
     * @return bool
     */
    protected function openReadStream() {
        $params = array(
            'Bucket' => static::$config['bucket'],
            'Key' => $this->meta->getKey(),
        );

        // Create the command and serialize the request
        $request = $this->getSignedRequest(
            $this->client->getCommand('GetObject', $params));
        // Create a stream that uses the EntityBody object
        $factory = new PhpStreamRequestFactory();
        $this->body = $factory->fromRequest($request, array(),
            array('stream_class' => 'Guzzle\Http\EntityBody'));

        return true;
    }

    /**
     * Initialize the stream wrapper for a write only stream
     *
     * @param array $params Operation parameters
     * @param array $errors Any encountered errors to append to
     *
     * @return bool
     */
    protected function openWriteStream() {
        $this->body = new EntityBody(fopen('php://temp', 'r+'));
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

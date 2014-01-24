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

    var $blocksize = 131072;

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
            return $this->body->read($bytes ?: $this->blocksize);
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
        return $this->body->write($block);
    }

    function flush() {
        return $this->upload($this->body);
    }

    function upload($filepath) {
        if ($filepath instanceof EntityBody)
            $filepath->rewind();
        elseif (is_string($filepath))
            $filepath = fopen($filepath, 'r');

        try {
            $this->client->upload(
                static::$config['bucket'],
                $this->meta->getKey(),
                $filepath,
                static::$config['acl'] ?: 'private',
                array('params' => array(
                    'ContentType' => $this->meta->getType(),
                    'CacheControl' => 'private, max-age=86400',
                ))
            );
            return true;
        }
        catch (S3Exception $e) {
            throw new IOException('Unable to upload to S3: '.(string)$e);
        }
        return false;
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

require_once dirname(__file__)
    .'/lib/Symfony/Component/ClassLoader/UniversalClassLoader.php';
use Symfony\Component\ClassLoader\UniversalClassLoader;
$loader = new UniversalClassLoader();
$loader->registerNamespaceFallbacks(array(
    dirname(__file__).'/lib'));
$loader->register();

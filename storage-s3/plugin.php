<?php

return array(
    'id' =>             'storage:s3',
    'version' =>        '0.3',
    'name' =>           /* trans */ 'Attachments hosted in Amazon S3',
    'author' =>         'Jared Hancock, Kevin Thorne',
    'description' =>    /* trans */ 'Enables storing attachments in Amazon S3',
    'url' =>            'http://www.osticket.com/plugins/storage-s3',
    'requires' => array(
        "aws/aws-sdk-php" => array(
            'version' => "3.*",
            'map' => array(
                'aws/aws-sdk-php/src' => 'lib/Aws',
                'guzzlehttp/guzzle/src' => 'lib/GuzzleHttp',
                'guzzlehttp/promises/src' => 'lib/GuzzleHttp/Promise',
                'guzzlehttp/psr7/src/' => 'lib/GuzzleHttp/Psr7',
                'mtdowling/jmespath.php/src' => 'lib/JmesPath',
                'psr/http-client/src' => 'lib/Psr/Http/Client',
                'psr/http-factory/src' => 'lib/Psr/Http/Factory',
                'psr/http-message/src' => 'lib/Psr/Http/Message',
            ),
        ),
    ),
    'plugin' =>         'storage.php:S3StoragePlugin'
);

?>

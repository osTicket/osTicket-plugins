<?php

return array(
    'id' =>             'storage:s3',
    'version' =>        '0.2.1',
    'name' =>           /* trans */ 'Attachments hosted in Amazon S3',
    'author' =>         'Jared Hancock',
    'description' =>    /* trans */ 'Enables storing attachments in Amazon S3',
    'url' =>            'http://www.osticket.com/plugins/storage-s3',
    'requires' => array(
        "aws/aws-sdk-php" => array(
            'version' => "2.*",
            'map' => array(
                'aws/aws-sdk-php/src/Aws/S3' => 'lib/Aws/S3',
                'aws/aws-sdk-php/src/Aws/Common' => 'lib/Aws/Common',
                'guzzle/guzzle/src/Guzzle' => 'lib/Guzzle',
                'symfony/event-dispatcher' => 'lib/Symfony/Component/EventDispatcher',
            ),
        ),
    ),
    'plugin' =>         'storage.php:S3StoragePlugin'
);

?>

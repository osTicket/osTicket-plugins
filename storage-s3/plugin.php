<?php

return array(
    'id' =>             'storage:s3',
    'version' =>        '0.2',
    'name' =>           /* trans */ 'Attachments hosted in Amazon S3',
    'author' =>         'Jared Hancock',
    'description' =>    /* trans */ 'Enables storing attachments in Amazon S3',
    'url' =>            'http://www.osticket.com/plugins/storage-s3',
    'requires' => array(
        "symfony/class-loader" => array(
            'version' => "*",
            'map' => array(
                'symfony/class-loader/Symfony' => 'lib/Symfony',
            ),
        ),
        "aws/aws-sdk-php" => array(
            'version' => "2.*",
            'map' => array(
                'aws/aws-sdk-php/src/Aws/S3' => 'lib/Aws/S3',
                'aws/aws-sdk-php/src/Aws/Common' => 'lib/Aws/Common',
                'symfony/event-dispatcher/Symfony' => 'lib/Symfony',
                'guzzle/guzzle/src/Guzzle' => 'lib/Guzzle',
            ),
        ),
    ),
    'plugin' =>         'storage.php:S3StoragePlugin'
);

?>

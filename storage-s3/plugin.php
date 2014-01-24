<?php

return array(
    'id' =>             'storage:s3',
    'version' =>        '0.1',
    'name' =>           'Attachments hosted in Amazon S3',
    'author' =>         'Jared Hancock',
    'description' =>    'Enables storing attachments in Amazon S3',
    'url' =>            'http://www.osticket.com/plugins/storage-s3',
    'includes' => array(
        'lib/aws/aws-sdk-php/src/Aws/S3' => 'lib/Aws/S3',
        'lib/aws/aws-sdk-php/src/Aws/Common' => 'lib/Aws/Common',
        'lib/symfony/event-dispatcher/Symfony' => 'lib/Symfony',
        'lib/symfony/class-loader/Symfony' => 'lib/Symfony',
        'lib/guzzle/guzzle/src/Guzzle' => 'lib/Guzzle',
    ),
    'plugin' =>         'storage.php:S3StoragePlugin'
);

?>

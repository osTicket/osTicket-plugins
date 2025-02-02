<?php

/**
 * FilesystemStorage plugin
 *
 * Allows attachment data to be written to the disk rather than in the
 * database
 */
class FilesystemStorage extends FileStorageBackend {
    var $fp = null;
    static $base;

    function read($bytes=32768, $offset=false) {
        $hash = $this->meta->getKey();
        $filename = $this->getPath($hash);
        if (!$this->fp)
            $this->fp = @fopen($filename, 'rb');
        if (!$this->fp)
            throw new IOException($filename.': Unable to open for reading');
        if ($offset)
            fseek($this->fp, $offset);
        if (($status = @fread($this->fp, $bytes)) === false)
            throw new IOException($filename.': Unable to read from file');
        return $status;
    }

    function passthru() {
        $hash = $this->meta->getKey();
        $filename = $this->getPath($hash);
        // TODO: Raise IOException on failure
        if (($status = @readfile($filename)) === false)
            throw new IOException($filename.': Unable to read from file');
        return $status;
    }

    function write($data) {
        $hash = $this->meta->getKey();
        $filename = $this->getPath($hash);
        if (!$this->fp)
            $this->fp = @fopen($filename, 'wb');
        if (!$this->fp)
            throw new IOException($filename.':Unable to open for reading');
        if (($status = @fwrite($this->fp, $data)) === false)
            throw new IOException($filename.': Unable to write to file');
        return $status;
    }

    function upload($filepath) {
        $destination = $this->getPath($this->meta->getKey());
        if (!@move_uploaded_file($filepath, $destination))
            throw new IOException($filepath.': Unable to move file');
        // TODO: Consider CHMOD on the file
        return true;
    }

    function unlink() {
        $filename = $this->getPath($this->meta->getKey());
        if (!@unlink($filename))
            throw new IOException($filename.': Unable to delete file');
        return true;
    }

    function getPath($hash) {
        // TODO: Make this configurable
        $prefix = $hash[0];
        $base = static::$base;
        if ($base[0] != '/' && $base[1] != ':')
            $base = ROOT_DIR . $base;
        // Auto-create the subfolders
        $base .= '/'.$prefix;
        if (!is_dir($base))
            mkdir($base, 0751);

        return $base.'/'.$hash;
    }
}

class FsStoragePluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    static function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function($x) { return $x; },
                function($x, $y, $n) { return $n != 1 ? $y : $x; },
            );
        }
        return Plugin::translate('storage-fs');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'uploadpath' => new TextboxField(array(
                'label'=>$__('Base folder for attachment files'),
                'hint'=>$__('The path must already exist and be writeable by the
                    web server. If the path starts with neither a `/` nor a
                    drive letter, the path will be assumed to be relative to
                    the root of osTicket'),
                'configuration'=>array('size'=>60, 'length'=>255),
                'required'=>true,
            )),
        );
    }

    function pre_save(&$config, &$errors) {
        list($__, $_N) = self::translate();
        $path = $config['uploadpath'];
        if ($path[0] != '/' && $path[1] != ':')
            $path = ROOT_DIR . $path;

        $field = $this->getForm()->getField('uploadpath');
        $file = md5(microtime());
        if (!@is_dir($path))
            $field->addError($__('Path does not exist'));
        elseif (!@opendir($path))
            $field->addError($__('Unable to access directory'));
        elseif (!@touch("$path/$file"))
            $field->addError($__('Unable to write to directory'));
        elseif (!@unlink("$path/$file"))
            $field->addError($__('Unable to remove files from directory'));
        else {
            touch("$path/.keep");
            if (!is_file("$path/.htaccess"))
                file_put_contents("$path/.htaccess", array('Options -Indexes', PHP_EOL, 'Deny from all'));
        }
        return true;
    }
}

class FsStoragePlugin extends Plugin {
    var $config_class = 'FsStoragePluginConfig';

    function bootstrap() {
        $config = $this->getConfig();
        $uploadpath = $config->get('uploadpath');
        list($__, $_N) = $config::translate();
        if ($uploadpath) {
            FileStorageBackend::register('F', 'FilesystemStorage');
            FilesystemStorage::$base = $uploadpath;
            FilesystemStorage::$desc = $__('Filesystem') .': '.$uploadpath;
        }
    }
}


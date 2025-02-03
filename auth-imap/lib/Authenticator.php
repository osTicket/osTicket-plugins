<?php

namespace ImapAuth;

use Exception;

trait Authenticator
{
    private function getConfig($key, $required = true)
    {
        $value = $this->config->get($key);
        if (!$value && $required) {
            throw new Exception("Please set the '{$key}'' configuration value.");
        }
        return $value;
    }

    private function getImapResponse($username, $password)
    {
        $server = $this->getConfig('imap-server');
        $method = $this->getConfig('method');
        $ssltls = $this->getConfig('tls-ssl');
	$server =  "{".$server."/".$method."/".$ssltls."/novalidate-cert}";
        //$additionalParams = $this->getConfig('additionalParams', false);
        $return = (object)array(
            'success' => false,
            'error' => null,
            'user' => null
        );
	error_log($server);	
        if ($imap=imap_open( $server, $username, $password, OP_HALFOPEN )) {
            $return->success = true;
            $return->user = $username;
        } else {
                    $return->error = "Username/Password not found";
        }
        return $return;
    }
}

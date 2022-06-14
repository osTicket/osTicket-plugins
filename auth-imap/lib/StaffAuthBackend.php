<?php

namespace ImapAuth;

use AccessDenied;
use StaffAuthenticationBackend;
use StaffSession;

class StaffAuthBackend extends StaffAuthenticationBackend
{
    use Authenticator;

    public static $name = 'IMAP Authentication';
    public static $id = 'imapauth';
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function supportsInteractiveAuthentication()
    {
        return true;
    }

    public function authenticate($username, $password)
    {
        $imapResponse = $this->getImapResponse($username, $password);
        if ($imapResponse->success) {
            if (($user = StaffSession::lookup($username)) && $user->getId()) {
                if (!$user instanceof StaffSession) {
                    // osTicket <= v1.9.7 or so 
                    $user = new StaffSession($user->getId());
                }
                return $user;
            } else {
                return new AccessDenied('Your credentials are valid but you do not have a staff account.');
            }
        } elseif ($imapResponse->error) {
            return new AccessDenied($imapResponse->error);
        } else {
            return new AccessDenied('Unable to validate login.');
        }
    }

    public function renderExternalLink()
    {
        return false;
    }

    public function supportsPasswordChange()
    {
        return false;
    }

    public function supportsPasswordReset()
    {
        return false;
    }
}

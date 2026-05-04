<?php
require_once INCLUDE_DIR . 'class.export.php';

class Auth2FABackend extends TwoFactorAuthenticationBackend {
    static $id = "auth.agent";
    static $name = "Authenticator";

    static $desc = /* @trans */ 'Verification codes are located in the Authenticator app of your choice on your phone';
    static $custom_issuer;

    var $secretKey;

    protected function getSetupOptions() {
        global $thisstaff;
        global $thisclient;

        $currentUser = null;
        $auth2FA = null;
        $email = null;
        switch (true) {
            case !empty($thisstaff) && ($thisstaff instanceof Staff || $thisstaff instanceof StaffSession):
                $currentUser = $thisstaff;
                $auth2FA = new Auth2FABackend;
                $email = $currentUser->getEmail();
                break;
            case !empty($thisclient) && ($thisclient instanceof ClientAccount || $thisclient instanceof UserAccount || $thisclient instanceof ClientSession):
                $user = $thisclient->getUser(true);
                $currentUser = $user->getAccount();
                $auth2FA = new UserAuth2FABackend;
                $email = $user->getEmail();
                break;
            default:
                error_log("class of: " . get_class($thisclient));
                return false;
        }

        $qrCodeURL = $auth2FA->getQRCode($currentUser);
        if ($auth2FA->validateQRCode($currentUser)) {
            return array(
                '' => new FreeTextField(array(
                    'configuration' => array(
                        'content' => sprintf(
                            '<input type="hidden" name="email" value="%s" />
                            <em>Use an Authenticator application on your phone to scan
                                the QR Code below. If you lose the QR Code
                                on the app, you will need to have your 2FA configurations reset by
                                a helpdesk Administrator.</em>
                            </br>
                            <tr>
                                <td>
                                <img src="%s" alt="QR Code" />
                                </td>
                            </tr>',
                            $email, $qrCodeURL),
                    )
                )),
            );
        }
    }

    protected function getInputOptions() {
        return array(
            'token' => new TextboxField(array(
                'id'=>1, 'label'=>__('Verification Code'), 'required'=>true, 'default'=>'',
                'validator'=>'number',
                'hint'=>__('Please enter the code from your Authenticator app'),
                'configuration'=>array(
                    'size'=>40, 'length'=>40,
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'pattern' => '[0-9]*',
                    'validator-error' => __('Invalid Code format'),
                    ),
            )),
        );
    }

    function validate($form, $user) {
        // Make sure form is valid and token exists
        if (!($form->isValid()
                    && ($clean=$form->getClean())
                    && $clean['token']))
            return false; // keep this

        if (!$this->validateLoginCode($clean['token']))
            return false; // keep this

        // upstream validation might throw an exception due to expired token
        // or too many attempts (timeout). It's the responsibility of the
        // caller to catch and handle such exceptions.
        $secretKey = $this->getSecretKey();
        if (!$this->_validate($secretKey))
            return false; // keep this

        // Validator doesn't do house cleaning - it's our responsibility
        $this->onValidate($user);

        return true;
    }

    function send($user) {
        global $cfg;

        // Get backend configuration for this user
    
        global $thisclient;
        if ($thisclient) $user = $thisclient->getUser(true)->getAccount();

        if (!$cfg || !($info = $user->get2FAConfig($this->getId())))
            return false;

        // get configuration
        $config = $info['config'];

        // Generate Secret Key
        if (!$this->secretKey)
            $this->secretKey = $this->getSecretKey($user);

        $this->store($this->secretKey);

        return true;
    }

    function store($secretKey) {
       $nameLookUp = null;
       $currentUser = null;

       global $thisstaff, $thisclient;
       if ($thisstaff) {
            $currentUser = $thisstaff;
            $nameLookUp = 'staff.';
        }
       elseif ($thisclient) {
            $currentUser = $thisclient->getUser(true)->getAccount();
            $nameLookUp = 'user.';
        }
        

       if (empty($secretKey)) {
           return false; 
       }

       $store =  &$_SESSION['_2fa'][$this->getId()];
       $store = ['otp' => $secretKey, 'time' => time(), 'strikes' => 0];

       if ($currentUser) {
        $userValue = null;
        switch (true) {
            case $currentUser instanceof ClientAccount:
                $userValue = $currentUser->getUserId();
                break;
            case $currentUser instanceof User:
                $userValue = $currentUser->getId();
                break;
            case $currentUser instanceof Staff || $currentUser instanceof StaffSession:
                $userValue = $currentUser->getId();
                break;
            default:
                return false;
        }
           $config = array('config' => array('key' => $secretKey, 'external2fa' => true));
           $_config = new Config($nameLookUp.$userValue);
           $_config->set($this->getId(), JsonDataEncoder::encode($config));
           $currentUser->_config = $_config->getInfo();
           $errors['err'] = '';
       }

       return $store;
    }

    function validateLoginCode($code) {
        $auth2FA = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        $secretKey = $this->getSecretKey();

        return $auth2FA->checkCode($secretKey, $code);
    }

    function getSecretKey($currentUser=false) {
        global $thisstaff, $thisclient;
        $userValue = null;
        $nameLookUp = null;
        if ($currentUser instanceof Staff || $currentUser instanceof StaffSession) {
            $userValue = $currentUser->getId();
            $nameLookUp = 'staff.';
        }
        elseif ($currentUser instanceof ClientAccount) {
            $user = $currentUser->getUser(true);
            $userValue = $user->getId();
            $nameLookUp = 'user.';
        }
        elseif ($currentUser instanceof User || $currentUser instanceof ClientSession) {
            $userValue = $currentUser->getId();
            $nameLookUp = 'user.';
        }
        elseif ($thisstaff) {
            $userValue = $thisstaff->getId();
            $nameLookUp = 'staff.';
        }
        elseif ($thisclient) {
            $user = $thisclient->getUser(true);
            $userValue = $user->getId();
            $nameLookUp = 'user.';
        }

        if (!$token = ConfigItem::getConfigsByNamespace($nameLookUp.$userValue, static::$id)) {
            $auth2FA = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
            $this->secretKey = $auth2FA->generateSecret();
            $this->store($this->secretKey);
        }

        $key = $token->value ?: $this->secretKey;
        if (strpos($key, 'config')) {
            $key = json_decode($key, true);
            $key = $key['config']['key'];
        }

        return $key;
    }

    function getQRCode($currentUser=false) {
        if ($currentUser instanceof ClientAccount) $userEmail = $currentUser->getUser()->getEmail();
        else $userEmail = $currentUser->getEmail();
        $secretKey = $this->getSecretKey($currentUser);
        $title = preg_replace('/[^A-Za-z0-9]/', '', self::$custom_issuer ?: __('osTicket'));
        return \Sonata\GoogleAuthenticator\GoogleQrUrl::generate($userEmail, $secretKey, $title);
    }

    function validateQRCode($currentUser=false) {
        $auth2FA = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        $secretKey = $this->getSecretKey($currentUser);
        // Fix: Generate code using the CURRENT secret key, not a new backend instance
        $code = $auth2FA->getCode($secretKey);

        return $auth2FA->checkCode($secretKey, $code);
    }

    static function getCode($currentUser=false) {
        if (!$currentUser) {
            global $thisstaff, $thisclient;
            if ($thisstaff) {
                $currentUser = $thisstaff;
                $self = new Auth2FABackend();
            }
            elseif ($thisclient) {
                $currentUser = $thisclient->getUser(true)->getAccount();
                $self = new UserAuth2FABackend();
            }
        }

        $auth2FA = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        $secretKey = $self->getSecretKey();

        return $auth2FA->getCode($secretKey);
    }
}

class UserAuth2FABackend extends Auth2FABackend {
    static $id = "auth.user";
    static $name = "Authenticator";

    static $desc = /* @trans */ 'Verification codes are located in the Authenticator app of your choice on your phone';
    static $custom_issuer;

    var $secretKey;
}

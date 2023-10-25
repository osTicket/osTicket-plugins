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

        $auth2FA = new Auth2FABackend;
        $qrCodeURL = $auth2FA->getQRCode($thisstaff);
        if ($auth2FA->validateQRCode($thisstaff)) {
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
                            $thisstaff->getEmail(), $qrCodeURL),
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
            return false;

        if (!$this->validateLoginCode($clean['token']))
            return false;

        // upstream validation might throw an exception due to expired token
        // or too many attempts (timeout). It's the responsibility of the
        // caller to catch and handle such exceptions.
        $secretKey = $this->getSecretKey();
        if (!$this->_validate($secretKey))
            return false;

        // Validator doesn't do house cleaning - it's our responsibility
        $this->onValidate($user);

        return true;
    }

    function send($user) {
        global $cfg;

        // Get backend configuration for this user
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
       global $thisstaff;

       $store =  &$_SESSION['_2fa'][$this->getId()];
       $store = ['otp' => $secretKey, 'time' => time(), 'strikes' => 0];

       if ($thisstaff) {
           $config = array('config' => array('key' => $secretKey, 'external2fa' => true));
           $_config = new Config('staff.'.$thisstaff->getId());
           $_config->set($this->getId(), JsonDataEncoder::encode($config));
           $thisstaff->_config = $_config->getInfo();
           $errors['err'] = '';
       }

       return $store;
    }

    function validateLoginCode($code) {
        $auth2FA = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        $secretKey = $this->getSecretKey();

        return $auth2FA->checkCode($secretKey, $code);
    }

    function getSecretKey($staff=false) {
        if (!$staff) {
            $s = StaffAuthenticationBackend::getUser();
            $staff = Staff::lookup($s->getId());
        }

        if (!$token = ConfigItem::getConfigsByNamespace('staff.'.$staff->getId(), static::$id)) {
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

    function getQRCode($staff=false) {
        $staffEmail = $staff->getEmail();
        $secretKey = $this->getSecretKey($staff);
        $title = preg_replace('/[^A-Za-z0-9]/', '', self::$custom_issuer ?: __('osTicket'));

        return \Sonata\GoogleAuthenticator\GoogleQrUrl::generate($staffEmail, $secretKey, $title);
    }

    function validateQRCode($staff=false) {
        $auth2FA = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        $secretKey = $this->getSecretKey($staff);
        $code = self::getCode();

        return $auth2FA->checkCode($secretKey, $code);
    }

    static function getCode() {
        $auth2FA = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
        $self = new Auth2FABackend();
        $secretKey = $self->getSecretKey();

        return $auth2FA->getCode($secretKey);
    }
}

<?php

class PasswordManagementConfig
extends PluginConfig {
    function getOptions() {
        return array(
            // Character classes
            'length' => new TextboxField(array(
                'required' => true,
                'label' => __('Minimum length'),
                'configuration' => array(
                    'validator' => 'number',
                    'size' => 4,
                ),
                'default' => 8,
                'hint' => __('Minimum characters required'),
            )),
            // Classes of characters
            'classes' => new ChoiceField(array(
                'required' => true,
                'label' => __('Character classes required'),
                'choices' => array(
                    '2' => sprintf('%s (2)', __('Two')),
                    '3' => sprintf('%s (3)', __('Three')),
                    '4' => sprintf('%s (4)', __('Four')),
                ),
                'default' => 3,
                'hint' => __('Require this number of character classes: upper, lower, number, and special characters'),
            )),
            // Entropy
            'entropy' => new ChoiceField(array(
                'required' => false,
                'label' => __('Password strength'),
                'choices' => array(
                    ''  => __('Disable'),
                    '32' => sprintf('%s (32 bits)', __('Weak')),
                    '56' => sprintf('%s (56 bits)', __('Good')),
                    '80' => sprintf('%s (80 bits)', __('Strong')),
                    '108' => sprintf('%s (108 bits)', __('Awesome')),
                ),
                'default' => '',
                'hint' => sprintf('%s %s',
                    __('Enforce minimum password entropy.'),
                    __('See the wikipedia page for password strength for more reading on entropy')),
            )),
            // Enforcement
            'onlogin' => new BooleanField(array(
                'required' => false,
                'label' => __('Enforce on login'),
                'default' => false,
                'configuration'=>array(
                    'desc' => __('Enforce password policies on login')
                    ),
                'hint' => __('Enforce password policies the next time a user login.')
            )),
            // Reuse
            'reuse' => new BooleanField(array(
                'required' => false,
                'label' => __('Password reuse'),
                'default' => false,
                'configuration'=>array(
                    'desc' => __('Allow reuse')
                    ),
                'hint' => __('Allow password reuse')
            )),
            // Expiration
            'expires' => new ChoiceField(array(
                'required' => false,
                'label' => __('Password expiration'),
                'choices' => array(
                    ''  => __('Never expires'),
                    '30' => __('30 days'),
                    '60' => __('60 days'),
                    '90' => __('90 days'),
                    '180' => __('180 days'),
                    '365' => __('365 days'),
                ),
                'default' => '',
                'hint' => __('Password reset frequency')
            )),
        );
    }
}

class PasswordManagementPolicy
extends PasswordPolicy {
    var $config;
    static $id = 'ppp';
    static $name = /* @trans */ "Password Management Plugin";

    function __construct($config) {
        $this->config = $config;
    }

    function onLogin($user, $password) {
        if (is_a($user, 'RegisteredUser'))
            return;

        // Check password length and strength
        if ($this->config->get('onlogin'))
            $this->processPassword($password);

        // Check password expiration
        if ($this->config->get('expires')
                && ($time = $user->getPasswdResetTimestamp())
                && ($time < (time()-($this->config->get('expires')*86400))))
            throw new ExpiredPassword(__('Expired Password'));
    }

    function onSet($password, $current=false) {
        return $this->processPassword($password, $current);
    }

    private function processPassword($password, $current=false) {

        // Current vs. new password
        if ($current
                && !$this->config->get('reuse')
                &&  0 === strcasecmp($passwd, $current)) {
            throw new BadPassword(
                __('New password MUST be different from the current password!'));
        }

        // Password length
        if (mb_strlen($password) < $this->config->get('length')) {
            throw new BadPassword(
                    sprintf(__('Password is too short — must be %d characters'),
                        $this->config->get('length'))
                );
        }

        // Class of characters
        if ($this->config->get('classes')) {
            if (preg_match('/\p{Ll}/u', $password))
                $classes++;
            if (preg_match('/\p{Lu}/u', $password))
                $classes++;
            if (preg_match('/\p{N}/u', $password))
                $classes++;
            if (preg_match('/[\pP\pS\pZ]/u', $password))
                $classes++;

            if ($classes < $this->config->get('classes'))
                throw new BadPassword(sprintf('%s %s',
                            __('Password does not meet complexity requirements.'),
                            __('Add upper, lower case letters, number, and symbols')
                            ));
        }

        // Password strength
        if ($this->config->get('entropy')) {
            // Calculate total possible char count
            if (preg_match('/[a-z]/', $password))
                $chars += 26;
            if (preg_match('/[A-Z]/', $password))
                $chars += 26;
            if (preg_match('/[0-9]/', $password))
                $chars += 10;
            if (preg_match('/[!@#$%^&*()]/', $password))
                $chars += 10;
            if (preg_match('/ /', $password))
                $chars += 1;
            if (preg_match('@[`~_=+[{\]}\\|;:\'",<.>/?-]@', $password))
                $chars += 20;
            // High ASCII / UTF-8
            if (preg_match('/[\x80-\xff]/', $password))
                $chars += 128;

            $entropy = strlen($password) * log($chars) / log(2);

            if ($entropy < $this->config->get('entropy'))
                throw new BadPassword(sprintf('%s %s %s',
                            __('Password is not complex enough.'),
                            __('Try a longer one or use upper case letters, number,and symbols.'),
                            sprintf(__('Score: %d of %d'), $entropy,
                                $this->config->get('entropy'))
                            ));
        }
    }
}

class PasswordManagementPlugin
extends Plugin {
    var $config_class = 'PasswordManagementConfig';

    function bootstrap() {
        PasswordPolicy::register(new PasswordManagementPolicy($this->getConfig()));
    }
}

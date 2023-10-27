<?php

class PasswordComplexityConfig
extends PluginConfig {
    function getOptions() {
        return array(
            'scheme' => new ChoiceField(array(
                'label' => 'Enforcement Configuration Scheme',
                'required' => true,
                'choices' => array(
                    'cl' => 'Classes',
                    'en' => 'Entropy',
                ),
                'default' => 'en',
                'hint' => 'Select the mode of configuration of complexity enforcement',
            )),

            // Character classes
            'length' => new TextboxField(array(
                'required' => true,
                'label' => 'Minimum Length',
                'configuration' => array(
                    'validator' => 'number',
                    'size' => 5,
                ),
                'default' => 6,
                'visibility' => new VisibilityConstraint(
                    new Q(array('scheme__eq' => 'cl')),
                    VisibilityConstraint::HIDDEN
                ),
            )),
            'classes' => new TextboxField(array(
                'required' => true,
                'label' => 'Character classes required',
                'configuration' => array(
                    'validator' => 'number',
                    'size' => 5,
                ),
                'hint' => 'Require this number of character classes: upper, lower, number, and special characters',
                'default' => 3,
                'visibility' => new VisibilityConstraint(
                    new Q(array('scheme__eq' => 'cl'))),
            )),

            // Entropy
            'entropy' => new ChoiceField(array(
                'required' => true,
                'label' => 'Minimum Entropy',
                'choices' => array(
                    '32' => 'Weak (32 bits)',
                    '56' => 'Reasonable (56 bits)',
                    '80' => 'Strong (80 bits)',
                    '108' => 'Insane (108 bits)',
                ),
                'default' => 32,
                'visibility' => new VisibilityConstraint(
                    new Q(array('scheme__eq' => 'en'))),
                'hint' => 'Require this password strength for new passwords. See the wikipedia page for password strength for more reading on entropy',
            )),
        );
    }
}

class PasswordComplexityPolicy
extends PasswordPolicy {
    var $config;

    function __construct($config) {
        $this->config = $config;
    }

    function processPassword($password, $current=false) {
        switch ($this->config->get('scheme')) {
        case 'en':
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
                throw new BadPassword(
                    'Password is not complex enough. Try a longer one or use upper case letters, number,and symbols.'.' '.
                    sprintf('Score: %d of %d', $entropy, $this->config->get('entropy'))
            );
            break;

        case 'cl':
            if (preg_match('/\p{Ll}/u', $password))
                $classes++;
            if (preg_match('/\p{Lu}/u', $password))
                $classes++;
            if (preg_match('/\p{N}/u', $password))
                $classes++;
            if (preg_match('/[\pP\pS\pZ]/u', $password))
                $classes++;

            if (mb_strlen($password) < $this->config->get('length')) {
                throw new BadPassword(
                    sprintf('Password is too short — must be %d characters',
                    $this->config->get('length'))
                );
            }
            if ($classes < $this->config->get('classes')) {
                throw new BadPassword(
                    'Password does not meet complexity requirements. Add upper, lower case letters, number, and symbols'
                );
            }
        }
    }
}

class PasswordComplexityPlugin
extends Plugin {
    var $config_class = 'PasswordComplexityConfig';

    function bootstrap() {
        PasswordPolicy::register(new PasswordComplexityPolicy($this->getConfig()));
    }
}

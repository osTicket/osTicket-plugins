<?php

require_once INCLUDE_DIR . 'class.dynamic_forms.php';
require_once INCLUDE_DIR . 'class.forms.php';

class reCaptchaField extends FormField {
    static $widget = 'reCaptchaWidget';
    static $plugin_config;

    function hasData() {
        // Data in this field is not database-backed
        return false;
    }

    function validateEntry($value) {
        static $validation;

        // ValidateEntry may be called twice, which is a problem
        $id = $this->get('id');
        $captcha = $this->getCaptcha();
        if (!isset($validation[$id])) {
            $I = &$validation[$id];
            if (!($I['valid'] = $captcha->isValid())) {
                if (!$captcha->getError())
                    $captcha->setError();
                $I['error'] = $captcha->getError();
                if ($I['error'] == 'incorrect-captcha-sol')
                    $I['error'] = "Your response doesn't look right. Please try again";
            }
        }
        if (!$validation[$id]['valid'])
            $this->_errors[] = $validation[$id]['error'];
    }

    function getCaptcha() {
        $pconfig = static::$plugin_config;
        $captcha = new ReCaptcha\Captcha(Internationalization::getCurrentLanguage());
        $captcha->setPublicKey($pconfig->get('public'));
        $captcha->setPrivateKey($pconfig->get('private'));
        return $captcha;
    }

    function getConfigurationOptions() {
        return array(
            'theme' => new ChoiceField(array(
                'label' => 'reCaptcha Theme',
                'choices' => array('red' => 'Red', 'white' => 'White',
                    'blackglass'=>'Black Glass', 'clean' => 'Clean'),
                'default' => 'red',
            )),
        );
    }
}

class reCaptchaWidget extends Widget {
    function render() {
        $captcha = $this->field->getCaptcha();
        $fconfig = $this->field->getConfiguration();
        echo $captcha->displayHTML($fconfig['theme']);
    }

    function getValue() {
        // noop — handled by the ReCaptcha library
        return 'junk';
    }
}

require_once 'config.php';

use Symfony\Component\ClassLoader\UniversalClassLoader_osTicket;

class reCaptchaPlugin extends Plugin {
    var $config_class = 'reCaptchaConfig';

    function bootstrap() {
        reCaptchaField::$plugin_config = $this->getConfig();
        FormField::addFieldTypes(__('Verification'), function() {
            return array(
                'recaptcha' => array('Google reCAPTCHA', 'reCaptchaField')
            );
        });

        require_once(INCLUDE_DIR.'UniversalClassLoader.php');
        $loader = new UniversalClassLoader_osTicket();
        $loader->registerNamespaces(array(
            'ReCaptcha' => dirname(__file__) . '/lib',
        ));
        $loader->register();
    }
}

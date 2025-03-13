<?php

require_once INCLUDE_DIR . 'class.dynamic_forms.php';
require_once INCLUDE_DIR . 'class.forms.php';

class hCaptchaField extends FormField {
  static $widget = 'hCaptchaWidget';
  static $plugin_config;

  function getPluginConfig() {
    return static::$plugin_config;
  }

  function validateEntry($value) {
    static $validation = array();

    parent::validateEntry($value);
    // ValidateEntry may be called twice, which is a problem
    $id = $this->get('id');
    $config = $this->getPluginConfig()->getInfo();
    if (!isset($validation[$id])) {
      list($code, $json) = $this->http_post(
        ' https://hcaptcha.com/siteverify ',
        array(
          'secret' => $config['secretKey'],
          'response' => $value,
          'remoteip' => $_SERVER['REMOTE_ADDR'],
        ));
        if ($code !== 200) {
          $response = array(
            'error-codes' => array('no-response'),
          );
        } else {
          $response = JsonDataParser::decode($json);
        }
        $I = &$validation[$id];
        if (!($I['valid'] = $response['success'])) {
          $errors = array();
          foreach ($response['error-codes'] as $code) {
            switch ($code) {
              case 'missing-input-response':
                $errors[] = sprintf(__('%s is a required field'),
                $this->getLabel() ?: __('This'));
              break;
              case 'invalid-input-response':
                $errors[] = "Your response doesn't look right. Please try again";
              break;
              case 'no-response':
                $errors[] = "Unable to communicate with the hCaptcha server";
            }
          }
          $I['errors'] = $errors;
        }
      }
      if (!$validation[$id]['valid']) {
        foreach ($validation[$id]['errors'] as $e) {
          $this->_errors[] = $e;
        }
      }
    }

    protected function http_post($url, array $data) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_USERAGENT, 'osTicket/'.THIS_VERSION);
      curl_setopt($ch, CURLOPT_HEADER, FALSE);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
      if (isset($_SERVER['HTTPS_PROXY']))
      curl_setopt($ch, CURLOPT_PROXY, $_SERVER['HTTPS_PROXY']);

      $result=curl_exec($ch);
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      return array($code, $result);
    }

    function getConfigurationOptions() {
      return array(
        'theme' => new ChoiceField(array(
          'label' => 'hCaptcha Theme',
          'choices' => array('dark' => 'Dark', 'light' => 'Light'),
          'default' => 'light',
        )),
        'size' => new ChoiceField(array(
          'label' => 'hCaptcha Size',
          'choices' => array('compact' => 'Compact', 'normal' => 'Normal'),
          'default' => 'normal',
        )),
      );
    }

    function getMedia() {
      return array(
        'js' => array(
          '//hcaptcha.com/1/api.js?hl='
            . Internationalization::getCurrentLanguage()
        ),
      );
    }
  }

  class hCaptchaWidget extends Widget {
    function render() {
      $fconfig = $this->field->getConfiguration();
      $pconfig = $this->field->getPluginConfig()->getInfo();
      ?>
      <div class="h-captcha"
      data-sitekey="<?php echo $pconfig['siteKey']; ?>"
      data-theme="<?php echo $fconfig['theme'] ?: 'light'; ?>"
      data-size="<?php echo $fconfig['size'] ?: 'normal'; ?>"
      ></div>
      <?php
    }

    function getValue() {
      if (!($data = $this->field->getSource()))
        return null;

      if (!isset($data['h-captcha-response']))
        return null;

      return $data['h-captcha-response'];
    }
  }

  require_once 'config.php';

  class hCaptchaPlugin extends Plugin {
    var $config_class = 'hCaptchaConfig';

    function bootstrap() {
      hCaptchaField::$plugin_config = $this->getConfig();
      FormField::addFieldTypes(__('Verification'), function() {
        return array(
          'hCaptcha' => array('hCaptcha', 'hCaptchaField')
        );
      });
    }
  }

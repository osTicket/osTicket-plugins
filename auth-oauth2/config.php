<?php
require_once INCLUDE_DIR . 'class.plugin.php';
require_once 'oauth2.php';

class OAuth2Config extends PluginConfig {

    public function getName() {
        return $this->get('auth_name');
    }

    public function getServiceName() {
        return $this->get('auth_service');
    }

    public function getClientId() {
        return $this->get('clientId');
    }

    public function getClientSecret() {
        return $this->get('clientSecret');
    }

    public function getScopes() {
        return array_map('trim',
                explode(',', $this->get('scopes', [])));
    }

    public function getAuthorizationUrl() {
        return $this->get('urlAuthorize');
    }

    public function getAccessTokenUrl() {
        return $this->get('urlAccessToken');
    }

    public function getRedirectUri() {
        return $this->get('redirectUri');
    }

    public function getResourceOwnerDetailstUrl() {
        return $this->get('urlResourceOwnerDetails');
    }

    public function getAttributeFor($name, $default=null) {
        return $this->get("attr_$name", $default);
    }

    public function getClientSettings() {
        // Determine scope separator
        $scopeSeparator = ',';
        if (stripos($this->getAuthorizationUrl(), 'google') !== false)
            $scopeSeparator = ' ';

        return [
            'clientId'       => $this->getClientId(),
            'clientSecret'   => $this->getClientSecret(),
            'redirectUri'    => $this->getRedirectUri(),
            'urlAuthorize'   => $this->getAuthorizationUrl(),
            'urlAccessToken' => $this->getAccessTokenUrl(),
            'urlResourceOwnerDetails' => $this->getResourceOwnerDetailstUrl(),
            'scopes' => $this->getScopes(),
            'scopeSeparator' => $scopeSeparator,
        ];
    }

    function translate() {
        return Plugin::translate('auth-oauth2');
    }

    function getOptions() {
        list($__, $_N) = self::translate();
        return array(
            'auth_settings' => new SectionBreakField(array(
                'label' => $__('Settings'),
                'hint' => $__('General settings'),
            )),
            'auth_name' => new TextboxField(array(
                    'label' => $__('Name'),
                    'hint' => $__('IdP Name e.g Google'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 34,
                        'length' => 125
                    )
                )
            ),
            'auth_type' => new ChoiceField(array(
                    'label' => $__('Type'),
                    'hint' => $__('OAuth2 Type'),
                    'required' => true,
                    'choices' => array(
                        'auth' => $__('Authentication'),
                        'autho' => $__('Authorization'),
                        ),
                    'configuration' => array(
                        'disabled' => false,
                    ),
                    'default' => 'auth',
                    )
            ),
            'auth_target' => new ChoiceField(array(
                    'label' => $__('Authentication Target'),
                    'hint' =>  $__('Target Audience'),
                    'required' => true,
                    'choices' => array(
                        'none' => $__('None (Disabled)'),
                        'agents' => $__('Agents Only'),
                        'users' => $__('End Users Only'),
                        'all' => $__('Agents and End Users'),
                        ),
                    'default' => 'none',
	                'visibility' => new VisibilityConstraint(
	                    new Q(array('auth_type__eq' => 'auth')),
	                    VisibilityConstraint::HIDDEN
                        ),
                    )
            ),
            'auth_service' => new TextboxField(array(
                    'label' => $__('Authentication Label'),
                    'hint' => $__('Sign in With label'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 34,
                        'length' => 64
                    ),
	                'visibility' => new VisibilityConstraint(
	                    new Q(array('auth_type__eq' => 'auth')),
	                    VisibilityConstraint::HIDDEN
                        ),
                    )
            ),
            'idp' => new SectionBreakField(array(
                'label' => $__('Identity Provider (IdP) Details'),
                'hint' => $__('Details for third-party identity provider'),
            )),
            'clientId' => new TextboxField(
                array(
                    'label' => $__('Client Id'),
                    'hint' => $__('IdP Client / Application Identifier'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 64,
                        'length' => 255
                    )
                )
            ),
            'clientSecret' => new PasswordField(
                array(
                    'widget' => 'PasswordWidget',
                    'label' => $__('Client Secret'),
                    'hint' => $__('IdP Client Secret'),
                    'required' => !$this->getClientSecret(),
                    'validator' => 'noop',
                    'configuration' => array(
                        'size' => 64,
                        'length' => 255,
                        'key' => $this->getNamespace(),
                    )
                )
            ),
            'redirectUri' => new TextboxField(
                array(
                    'label' => $__('Callback Endpoint'),
                    'hint' => $__('Redirect Uri'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 64,
                        'length' => 255
                    ),
                    'validators' => function($f, $v) {
                        if (!preg_match('[\.*(/api/auth/oauth2)$]isu', $v))
                            $f->addError(__('Must be a valid API endpont'));
                     },
                    'default' => OAuth2Plugin::callback_url(),
                )
            ),
            'urlAuthorize' => new TextboxField(
                array(
                    'label' => $__('Authorization Endpoint'),
                    'hint' => $__('Authorization URL'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 64,
                        'length' => 255
                    ),
                    'default' => '',
                )
            ),
            'urlAccessToken' => new TextboxField(
                array(
                    'label' => $__('Token Endpoint'),
                    'hint' => $__('Access Token URL'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 64,
                        'length' => 255
                    ),
                    'default' => '',
                )
            ),
            'urlResourceOwnerDetails' => new TextboxField(
                array(
                    'label' => $__('Resource Details Endpoint'),
                    'hint' => $__('User Details URL'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 64,
                        'length' => 255
                    ),
                    'default' => '',
                )
            ),
            'scopes' => new TextboxField(
                array(
                    'label' => $__('Scopes'),
                    'hint' => $__('Comma or Space separated scopes depending
                        on IdP requirements'),
                    'configuration' => array(
                        'size' => 64,
                        'length' => 255
                    ),
                )
            ),
            'attr_mapping' => new SectionBreakField(array(
                'label' => $__('User Attributes Mapping'),
                'hint' => $__('Consult your IdP documentation for supported attributes and scope'),
            )),
           'attr_username' => new TextboxField(array(
                'label' => $__('User Identifier'),
                'hint'  => $__('Unique User Identifier - Username or Email address'),
                'required' => true,
                'default' => 'email',

            )),
            'attr_givenname' => new TextboxField(array(
                'label' => $__('Given Name'),
                'hint'  => $__('First name'),
                'default' => 'givenname',

            )),
            'attr_surname' => new TextboxField(array(
                'label' => $__('Surname'),
                'hint'  => $__('Last name'),
                'default' => 'surname',

            )),
            'attr_email' => new TextboxField(array(
                'label' => $__('Email Address'),
                'hint' => $__('Email address required to auto-create User accounts. Agents must already exist.'),
                'default' => 'email',
            )),
        );
    }
}

class BasicOAuth2Config extends OAuth2Config {
    // Only get the basic field options
    function getOptions() {
        return array_intersect_key(parent::getOptions(),
                array_flip(['clientId', 'clientSecret', 'urlAuthorize',
                    'urlAccessToken', 'urlResourceOwnerDetails',
                    'redirectUri', 'scopes']));
    }
}

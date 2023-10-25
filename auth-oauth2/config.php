<?php
require_once INCLUDE_DIR . 'class.plugin.php';
require_once 'oauth2.php';

class OAuth2Config extends PluginConfig {

    public function getAuthType() {
        return  $this->get('auth_type', 'auth');
    }

    public function isAutho() {
        return ($this->getAuthType()
                && !strcasecmp($this->getAuthType(), 'autho'));
    }

    public function isAuthen() {
        return !$this->isAutho();
    }

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
        $scopes =  $this->getScopes();
        $settings = [
            'clientId'       => $this->getClientId(),
            'clientSecret'   => $this->getClientSecret(),
            'redirectUri'    => $this->getRedirectUri(),
            'urlAuthorize'   => $this->getAuthorizationUrl(),
            'urlAccessToken' => $this->getAccessTokenUrl(),
            'urlResourceOwnerDetails' => $this->getResourceOwnerDetailstUrl(),
            'scopes' => $scopes,
        ];

        // Use comma separator when we have more than one scopes - this is
        // because scopes string is comma exploded.
        if ($scopes && count($scopes) > 1)
            $settings['scopeSeparator'] = ',';

        return $settings;
    }

    function translate() {
        return Plugin::translate('auth-oauth2');
    }

    function getAllOptions() {
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
                'label' => $__('OAuth2 Provider (IdP) Details'),
                'hint' => $__('Authorization instances can be added via Email Account interface'),
            )),
            'auth_type' => new ChoiceField(array(
                    'label' => $__('Type'),
                    'hint' => $__('OAuth2 Client Type'),
                    'required' => true,
                    'choices' => array(
                        'auth' => $__('Authentication'),
                        'autho' => $__('Authorization'),
                        ),
                    'configuration' => array(
                        'disabled' => true,
                    ),
                    'default' => $this->getAuthType(),
                    )
            ),
            'redirectUri' => new TextboxField(
                array(
                    'label' => $__('Redirect URI'),
                    'hint' => $__('Callback Endpoint'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 64,
                        'length' => 0
                    ),
                    'validators' => function($f, $v) {
                        if (!preg_match('[\.*(/api/auth/oauth2)$]isu', $v))
                            $f->addError(__('Must be a valid API endpont'));
                     },
                    'default' => OAuth2Plugin::callback_url(),
                )
            ),
            'clientId' => new TextboxField(
                array(
                    'label' => $__('Client Id'),
                    'hint' => $__('Client Identifier (Id)'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 64,
                        'length' => 0,
                        'placeholder' => $__('Client Id')
                    )
                )
            ),
            'clientSecret' => new PasswordField(
                array(
                    'widget' => 'PasswordWidget',
                    'label' => $__('Client Secret'),
                    'hint' => $__('Client Secret'),
                    'required' => !$this->getClientSecret(),
                    'validator' => 'noop',
                    'configuration' => array(
                        'size' => 64,
                        'length' => 0,
                        'key' => $this->getNamespace(),
                        'placeholder' => $this->getClientSecret()
                            ? str_repeat('•', strlen($this->getClientSecret()))
                            : $__('Client Secret'),
                    )
                )
            ),
            'urlAuthorize' => new TextboxField(
                array(
                    'label' => $__('Authorization Endpoint'),
                    'hint' => $__('Authorization URL'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 64,
                        'length' => 0
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
                        'length' => 0
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
                        'length' => 0
                    ),
                    'default' => '',
                )
            ),
            'scopes' => new TextboxField(
                array(
                    'label' => $__('Scopes'),
                    'hint' => $__('Comma or Space separated scopes depending on IdP requirements'),
                    'required' => true,
                    'configuration' => array(
                        'size' => 64,
                        'length' => 0
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
                'configuration' => array(
                    'size' => 64,
                    'length' => 0
                ),
                'visibility' => new VisibilityConstraint(
                    new Q(array('auth_type__eq' => 'auth')),
                    VisibilityConstraint::HIDDEN
                    ),
            )),
            'attr_givenname' => new TextboxField(array(
                'label' => $__('Given Name'),
                'hint'  => $__('First name'),
                'default' => 'givenname',
                'configuration' => array(
                    'size' => 64,
                    'length' => 0
                ),
                'visibility' => new VisibilityConstraint(
                    new Q(array('auth_type__eq' => 'auth')),
                    VisibilityConstraint::HIDDEN
                    ),

            )),
            'attr_surname' => new TextboxField(array(
                'label' => $__('Surname'),
                'hint'  => $__('Last name'),
                'default' => 'surname',
                'configuration' => array(
                    'size' => 64,
                    'length' => 0
                ),
                'visibility' => new VisibilityConstraint(
                    new Q(array('auth_type__eq' => 'auth')),
                    VisibilityConstraint::HIDDEN
                    ),
            )),
            'attr_email' => new TextboxField(array(
                'label' => $__('Email Address'),
                'hint' => $__('Email address required to auto-create User accounts. Agents must already exist.'),
                'default' => 'email',
                'configuration' => array(
                    'size' => 64,
                    'length' => 0
                ),
            )),
        );
    }

    function getOptions() {
        return  $this->getAllOptions();
    }

    function getFields() {
        list($__, $_N) = self::translate();
        switch ($this->getAuthType()) {
            case 'autho':
                // Authorization fields
                $base =  array_flip(['idp', 'auth_type', 'redirectUri', 'clientId', 'clientSecret',
                        'urlAuthorize', 'urlAccessToken',
                        'urlResourceOwnerDetails', 'scopes', 'attr_email',
                ]);
                $fields = array_merge($base, array_intersect_key(
                            $this->getAllOptions(), $base));
                $fields['attr_email'] = new TextboxField([
                        'label' => $__('Email Address Attribute'),
                        'hint' => $__('Please consult your provider docs for the correct attribute to use'),
                        'required' => true,
                ]);
                break;
            case 'auth':
            default:
                $fields = $this->getOptions();
                break;
        }
        return $fields;
    }

    function pre_save(&$config, &$errors) {
        list($__, $_N) = self::translate();
        // Authorization instances can only be managed via Email Account
        // interface at the moment.
        if ($this->isAutho())
            $errors['err'] = $__('Authorization instances can only be managed via Email Account interface at the moment');
        return !count($errors);
    }

    public function getFormOptions() {
        list($__, $_N) = self::translate();
        return [
            'notice' => $this->isAutho()
                ? $__('Authorization instances can only be updated via Email Account interface')
                : ($this->getClientId()
                        ? $__('Be careful - changes might break Authentication of the Target Audience')
                        : ''
                        ),
        ];
    }
}

class OAuth2EmailConfig extends OAuth2Config {

    public function getAuthType() {
        return  $this->get('auth_type', 'autho');
    }

    // Notices are handled at Email Account level
    public function getFormOptions() {
        return [];
    }

    // This is necessay so the parent can reject updates on Autho instances via plugins
    // intervace which is doesn't have re-authorization capabilities at the
    // moment.
    function pre_save(&$config, &$errors) {
        return true;
    }

    function getFields() {
        // Remove fields not needed on the Email interface
        return array_diff_key(parent::getFields(),
                array_flip(['idp', 'auth_type'])
                );
    }
}

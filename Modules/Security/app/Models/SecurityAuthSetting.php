<?php

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityAuthSetting extends Model
{
    protected $table = 'security_auth_settings';

    protected $fillable = [
        'session_lifetime_hours',
        'auth_method',
        'password_min_length',
        'sso_enabled',
        'hide_internal_prompt',
        'auto_user_provisioning',
        'oauth_auto_team_membership',
        'oauth_provider',
        'oauth_google_enabled',
        'oauth_github_enabled',
        'oauth_custom_enabled',
        'oauth_client_id',
        'oauth_client_secret',
        'oauth_authorization_url',
        'oauth_token_url',
        'oauth_resource_url',
        'oauth_redirect_url',
        'oauth_logout_url',
        'oauth_user_identifier',
        'oauth_scopes',
        'oauth_auth_style',
        'ldap_enabled',
        'ldap_host',
        'ldap_port',
        'ldap_anonymous',
        'ldap_bind_dn',
        'ldap_bind_password',
        'ldap_use_starttls',
        'ldap_use_tls',
        'ldap_verify_certificate',
        'ldap_base_dn',
        'ldap_user_filter',
        'ldap_username_attribute',
        'ldap_email_attribute',
        'ldap_group_base_dn',
        'ldap_group_filter',
        'ldap_group_membership_attribute',
        'ldap_assign_admin_by_group',
        'ldap_admin_group_names',
        'ldap_group_role_map',
        'ldap_fallback_email_domain',
        'login_headline',
        'login_slogan',
    ];

    protected function casts(): array
    {
        return [
            'session_lifetime_hours' => 'integer',
            'password_min_length' => 'integer',
            'sso_enabled' => 'boolean',
            'hide_internal_prompt' => 'boolean',
            'auto_user_provisioning' => 'boolean',
            'oauth_auto_team_membership' => 'boolean',
            'oauth_google_enabled' => 'boolean',
            'oauth_github_enabled' => 'boolean',
            'oauth_custom_enabled' => 'boolean',
            'ldap_enabled' => 'boolean',
            'ldap_port' => 'integer',
            'ldap_anonymous' => 'boolean',
            'ldap_use_starttls' => 'boolean',
            'ldap_use_tls' => 'boolean',
            'ldap_verify_certificate' => 'boolean',
            'ldap_assign_admin_by_group' => 'boolean',
            'oauth_client_secret' => 'encrypted',
            'ldap_bind_password' => 'encrypted',
        ];
    }
}

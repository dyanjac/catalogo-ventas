<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_auth_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('session_lifetime_hours')->default(8);
            $table->string('auth_method', 24)->default('internal');
            $table->unsignedTinyInteger('password_min_length')->default(12);
            $table->boolean('sso_enabled')->default(false);
            $table->boolean('hide_internal_prompt')->default(false);
            $table->boolean('auto_user_provisioning')->default(false);
            $table->boolean('oauth_auto_team_membership')->default(false);
            $table->string('oauth_provider', 24)->default('custom');
            $table->boolean('oauth_google_enabled')->default(false);
            $table->boolean('oauth_github_enabled')->default(false);
            $table->boolean('oauth_custom_enabled')->default(false);
            $table->string('oauth_client_id')->nullable();
            $table->text('oauth_client_secret')->nullable();
            $table->string('oauth_authorization_url')->nullable();
            $table->string('oauth_token_url')->nullable();
            $table->string('oauth_resource_url')->nullable();
            $table->string('oauth_redirect_url')->nullable();
            $table->string('oauth_logout_url')->nullable();
            $table->string('oauth_user_identifier', 120)->default('id');
            $table->string('oauth_scopes')->nullable();
            $table->string('oauth_auth_style', 24)->default('auto');
            $table->boolean('ldap_enabled')->default(false);
            $table->string('ldap_host')->nullable();
            $table->unsignedSmallInteger('ldap_port')->default(389);
            $table->boolean('ldap_anonymous')->default(false);
            $table->string('ldap_bind_dn')->nullable();
            $table->text('ldap_bind_password')->nullable();
            $table->boolean('ldap_use_starttls')->default(false);
            $table->boolean('ldap_use_tls')->default(false);
            $table->boolean('ldap_verify_certificate')->default(true);
            $table->string('ldap_base_dn')->nullable();
            $table->string('ldap_user_filter')->nullable();
            $table->string('ldap_username_attribute', 120)->default('uid');
            $table->string('ldap_group_base_dn')->nullable();
            $table->string('ldap_group_filter')->nullable();
            $table->string('ldap_group_membership_attribute', 120)->default('member');
            $table->boolean('ldap_assign_admin_by_group')->default(false);
            $table->string('ldap_admin_group_names')->nullable();
            $table->string('login_headline')->nullable();
            $table->text('login_slogan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_auth_settings');
    }
};

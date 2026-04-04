<?php

namespace Modules\AdminTheme\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class AdminThemeSetting extends Model
{
    use BelongsToOrganization;

    protected $table = 'admin_theme_settings';

    protected $fillable = [
        'organization_id',
        'sidebar_bg',
        'sidebar_gradient_to',
        'sidebar_text',
        'sidebar_group_text',
        'sidebar_group_bg',
        'topbar_bg',
        'topbar_text',
        'user_menu_trigger_bg',
        'user_menu_trigger_text',
        'user_menu_dropdown_bg',
        'user_menu_dropdown_text',
        'user_menu_dropdown_hover_bg',
        'user_menu_dropdown_hover_text',
        'primary_button',
        'primary_button_hover',
        'active_link_bg',
        'active_link_text',
        'card_border',
        'focus_ring',
    ];
}

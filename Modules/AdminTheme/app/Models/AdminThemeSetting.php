<?php

namespace Modules\AdminTheme\Models;

use Illuminate\Database\Eloquent\Model;

class AdminThemeSetting extends Model
{
    protected $table = 'admin_theme_settings';

    protected $fillable = [
        'sidebar_bg',
        'sidebar_gradient_to',
        'sidebar_text',
        'topbar_bg',
        'topbar_text',
        'primary_button',
        'primary_button_hover',
        'active_link_bg',
        'active_link_text',
        'card_border',
        'focus_ring',
    ];
}

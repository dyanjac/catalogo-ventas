<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Immutable snapshots: future palette changes must not alter this migration.
    private const OLD_PALETTE = [
        'sidebar_bg' => '#2f3a20',
        'sidebar_gradient_to' => '#4f5f2f',
        'sidebar_text' => '#ffffff',
        'sidebar_group_text' => '#ffffff',
        'sidebar_group_bg' => '#d4a64a',
        'topbar_bg' => '#ffffff',
        'topbar_text' => '#1f2d3d',
        'user_menu_trigger_bg' => '#ffffff',
        'user_menu_trigger_text' => '#1f2d3d',
        'user_menu_dropdown_bg' => '#ffffff',
        'user_menu_dropdown_text' => '#1f2d3d',
        'user_menu_dropdown_hover_bg' => '#d4a64a',
        'user_menu_dropdown_hover_text' => '#1f2d3d',
        'primary_button' => '#6c7f3e',
        'primary_button_hover' => '#5d6e35',
        'active_link_bg' => '#d4a64a',
        'active_link_text' => '#1f2d3d',
        'card_border' => '#6f7d5c2e',
        'focus_ring' => '#6c7f3e40',
    ];

    private const NEW_PALETTE = [
        'sidebar_bg' => '#1E293B',
        'sidebar_gradient_to' => '#334155',
        'sidebar_text' => '#F8FAFC',
        'sidebar_group_text' => '#F8FAFC',
        'sidebar_group_bg' => '#334155',
        'topbar_bg' => '#FFFFFF',
        'topbar_text' => '#0F172A',
        'user_menu_trigger_bg' => '#FFFFFF',
        'user_menu_trigger_text' => '#0F172A',
        'user_menu_dropdown_bg' => '#FFFFFF',
        'user_menu_dropdown_text' => '#0F172A',
        'user_menu_dropdown_hover_bg' => '#CCFBF1',
        'user_menu_dropdown_hover_text' => '#134E4A',
        'primary_button' => '#0F766E',
        'primary_button_hover' => '#115E59',
        'active_link_bg' => '#CCFBF1',
        'active_link_text' => '#134E4A',
        'card_border' => '#CBD5E1',
        'focus_ring' => '#0F766E',
    ];

    public function up(): void
    {
        $this->replacePalette(self::OLD_PALETTE, self::NEW_PALETTE);
    }

    public function down(): void
    {
        // Data changes are intentionally preserved on rollback: without provenance,
        // reverting could overwrite a palette chosen after this migration.
    }

    private function replacePalette(array $from, array $to): void
    {
        DB::table('admin_theme_settings')->orderBy('id')->chunkById(100, function ($settings) use ($from, $to): void {
            foreach ($settings as $setting) {
                foreach ($from as $key => $expected) {
                    $value = $setting->{$key};
                    // Null columns inherit the historical defaults, including later-added fields.
                    if ($value !== null && trim($value) !== '' && strcasecmp(trim($value), $expected) !== 0) {
                        continue 2;
                    }
                }

                // Keep the update conditional if an administrator edits the row concurrently.
                $query = DB::table('admin_theme_settings')->where('id', $setting->id);
                foreach (array_keys($from) as $key) {
                    $query->where($key, $setting->{$key});
                }
                $query->update($to);

                $suffix = $setting->organization_id ?: 'default';
                Cache::forget('admin_theme_palette_v1:'.$suffix);
                Cache::forget('admin_theme_palette_v2:'.$suffix);
            }
        });
    }
};

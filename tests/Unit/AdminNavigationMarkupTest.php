<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class AdminNavigationMarkupTest extends TestCase
{
    #[DataProvider('adminBladeFiles')]
    public function test_internal_admin_links_use_livewire_navigation(string $path): void
    {
        $contents = file_get_contents($path);

        $this->assertIsString($contents);

        foreach (preg_split('/\R/', $contents) ?: [] as $lineNumber => $line) {
            if (! str_contains($line, "route('admin.")) {
                continue;
            }

            if (! $this->isNavigableLink($line) || $this->requiresFullPageLoad($path, $line)) {
                continue;
            }

            $this->assertStringContainsString(
                'wire:navigate',
                $line,
                sprintf('%s:%d debe usar wire:navigate.', $path, $lineNumber + 1)
            );
        }
    }

    public function test_admin_navigation_does_not_prefetch_on_hover(): void
    {
        foreach (self::adminBladeFiles() as [$path]) {
            $this->assertStringNotContainsString('wire:navigate.hover', (string) file_get_contents($path), $path);
        }
    }

    public function test_downloads_and_cross_layout_links_keep_full_page_navigation(): void
    {
        $cases = [
            [self::repositoryPath('resources/views/admin/partials/sidebar.blade.php'), "route('orders.mine')"],
            [self::repositoryPath('resources/views/admin/orders/show.blade.php'), "route('admin.orders.download.pdf'"],
            [self::repositoryPath('Modules/Billing/resources/views/documents/show.blade.php'), "route('admin.billing.documents.download.pdf'"],
            [self::repositoryPath('Modules/Transport/resources/views/guides/show.blade.php'), "route('admin.transport.guides.xml'"],
            [self::repositoryPath('Modules/Transport/resources/views/guides/show.blade.php'), "route('admin.transport.guides.cdr'"],
        ];

        foreach ($cases as [$path, $routeExpression]) {
            $line = collect(preg_split('/\R/', (string) file_get_contents($path)))
                ->first(fn (string $line): bool => str_contains($line, $routeExpression));

            $this->assertIsString($line, "No se encontró {$routeExpression} en {$path}.");
            $this->assertStringNotContainsString('wire:navigate', $line, $path);
        }
    }

    public function test_sidebar_links_keep_full_page_navigation_to_avoid_preloads(): void
    {
        $contents = (string) file_get_contents(self::repositoryPath('resources/views/admin/partials/sidebar.blade.php'));

        $this->assertStringNotContainsString('wire:navigate', $contents);
        $this->assertStringContainsString("config('app.version')", $contents);
        $this->assertStringContainsString('data-build-version', $contents);
    }

    public function test_build_version_is_only_in_the_sidebar_footer(): void
    {
        $sidebar = (string) file_get_contents(self::repositoryPath('resources/views/admin/partials/sidebar.blade.php'));

        $this->assertMatchesRegularExpression('/admin-sidebar__footer[\s\S]*data-build-version/', $sidebar);
        $this->assertSame(1, substr_count($sidebar, 'data-build-version'));

        foreach (['resources/views/admin/partials/header.blade.php', 'resources/views/layouts/admin.blade.php'] as $path) {
            $contents = (string) file_get_contents(self::repositoryPath($path));

            $this->assertStringNotContainsString('data-build-version', $contents);
            $this->assertStringNotContainsString("config('app.version')", $contents);
        }
    }

    public function test_desktop_sidebar_toggle_is_icon_only(): void
    {
        $contents = (string) file_get_contents(self::repositoryPath('resources/views/admin/partials/header.blade.php'));

        $this->assertStringContainsString('data-admin-sidebar-toggle', $contents);
        $this->assertStringContainsString('admin-topbar__collapse-icon', $contents);
        $this->assertStringNotContainsString('data-admin-sidebar-toggle-label', $contents);
    }

    public function test_sidebar_navigation_owns_a_subtle_scrollbar(): void
    {
        $contents = (string) file_get_contents(self::repositoryPath('resources/css/admin.css'));

        $this->assertStringContainsString('[data-flux-sidebar-nav]', $contents);
        $this->assertStringContainsString('overflow-y: auto', $contents);
        $this->assertStringContainsString('scrollbar-width: thin', $contents);
        $this->assertStringContainsString('::-webkit-scrollbar', $contents);
    }

    /** @return array<string,array{string}> */
    public static function adminBladeFiles(): array
    {
        $roots = [
            self::repositoryPath('resources/views/admin'),
            self::repositoryPath('resources/views/livewire/admin'),
            self::repositoryPath('Modules'),
        ];
        $files = [];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $path = $file->getPathname();

                if (str_contains($path, DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR)
                    && ! str_contains($path, DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $files[$path] = [$path];
            }
        }

        ksort($files);

        return $files;
    }

    private function isNavigableLink(string $line): bool
    {
        return str_contains($line, '<a ')
            || str_contains($line, '<flux:button ')
            || str_contains($line, '<flux:menu.item ')
            || str_contains($line, '<flux:sidebar.item ');
    }

    private function requiresFullPageLoad(string $path, string $line): bool
    {
        return str_ends_with($path, DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'admin'.DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR.'sidebar.blade.php')
            || str_contains($line, 'download')
            || str_contains($line, "route('admin.login')")
            || str_contains($line, "route('admin.transport.guides.xml'")
            || str_contains($line, "route('admin.transport.guides.cdr'")
            || str_contains($line, "route('admin.electronic-documents.pdf.generate'");
    }

    private static function repositoryPath(string $path): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path;
    }
}

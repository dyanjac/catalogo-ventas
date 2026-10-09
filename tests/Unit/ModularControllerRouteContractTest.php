<?php

namespace Tests\Unit;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class ModularControllerRouteContractTest extends TestCase
{
    public function test_modular_routes_reference_public_controller_actions(): void
    {
        $controllerActions = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): mixed => $route->getAction('controller'))
            ->filter(fn ($action): bool => is_string($action) && str_starts_with($action, 'Modules\\'))
            ->unique()
            ->values();

        $this->assertGreaterThan(20, $controllerActions->count());

        foreach ($controllerActions as $action) {
            [$controller, $method] = str_contains($action, '@')
                ? explode('@', $action, 2)
                : [$action, '__invoke'];

            $this->assertTrue(class_exists($controller), "No existe el controlador {$controller}.");
            $this->assertTrue(method_exists($controller, $method), "No existe la acción {$action}.");
            $this->assertTrue((new ReflectionMethod($controller, $method))->isPublic(), "La acción {$action} no es pública.");
        }
    }

    public function test_module_controller_classes_are_loadable_laravel_controllers(): void
    {
        $controllerFiles = glob(base_path('Modules/*/app/Http/Controllers/*.php')) ?: [];

        $this->assertCount(44, $controllerFiles, 'Actualiza este contrato cuando se agreguen o retiren controladores modulares.');

        foreach ($controllerFiles as $controllerFile) {
            $module = basename(dirname($controllerFile, 4));
            $controller = 'Modules\\'.$module.'\\Http\\Controllers\\'.pathinfo($controllerFile, PATHINFO_FILENAME);

            $this->assertTrue(class_exists($controller), "No se puede cargar el controlador {$controller}.");
            $this->assertTrue(is_subclass_of($controller, Controller::class), "{$controller} no extiende el controlador base.");
        }
    }
}

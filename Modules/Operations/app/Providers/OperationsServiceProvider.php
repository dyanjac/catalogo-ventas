<?php

declare(strict_types=1);

namespace Modules\Operations\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Modules\Operations\Console\OperationsDoctorCommand;
use Modules\Operations\Console\ReconcileOperationsCommand;
use Modules\Operations\Console\RecoverEconomicEventsCommand;
use Modules\Operations\Services\OperationalIncidentService;
use Modules\Operations\Services\OperationalReconciliationService;
use Modules\Operations\Services\OperationalRecoveryService;
use Modules\Operations\Services\ReadinessService;

final class OperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__, 2).'/config/config.php', 'operations');
        $this->app->singleton(OperationalIncidentService::class);
        $this->app->singleton(OperationalReconciliationService::class);
        $this->app->singleton(OperationalRecoveryService::class);
        $this->app->singleton(ReadinessService::class);
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Operations', 'database/migrations'));
        $this->loadViewsFrom(module_path('Operations', 'resources/views'), 'operations');
        Blade::componentNamespace('Modules\\Operations\\View\\Components', 'operations');
        $this->registerObservabilityListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcileOperationsCommand::class, RecoverEconomicEventsCommand::class, OperationsDoctorCommand::class]);
        }

        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command('operations:reconcile --all')
                ->cron((string) config('operations.reconciliation.schedule', '*/15 * * * *'))
                ->timezone('UTC')
                ->withoutOverlapping(20)
                ->onOneServer()
                ->runInBackground();
        });
    }

    private function registerObservabilityListeners(): void
    {
        Event::listen(JobProcessing::class, fn (JobProcessing $event) => Log::channel('operations')->info('erp.queue.processing', [
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'job' => $event->job->resolveName(),
            'attempt' => $event->job->attempts(),
        ]));
        Event::listen(JobProcessed::class, fn (JobProcessed $event) => Log::channel('operations')->info('erp.queue.processed', [
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'job' => $event->job->resolveName(),
            'attempt' => $event->job->attempts(),
        ]));
        Event::listen(JobFailed::class, fn (JobFailed $event) => Log::channel('operations')->error('erp.queue.failed', [
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'job' => $event->job->resolveName(),
            'attempt' => $event->job->attempts(),
            'exception' => $event->exception::class,
        ]));

        DB::whenQueryingForLongerThan(500, function (Connection $connection, QueryExecuted $query): void {
            Log::channel('operations')->warning('erp.database.slow_request', [
                'connection' => $connection->getName(),
                'last_query_time_ms' => $query->time,
            ]);
        });
    }
}

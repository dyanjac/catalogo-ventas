<?php

namespace Modules\Security\Livewire;

use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Security\Models\SecurityAuditLog;

class AuditLogScreen extends Component
{
    use WithPagination;

    #[Url(as: 'event', history: true, keep: true)]
    public string $eventType = '';

    #[Url(as: 'result', history: true, keep: true)]
    public string $result = '';

    #[Url(as: 'search', history: true, keep: true)]
    public string $search = '';

    public function updatingEventType(): void
    {
        $this->resetPage();
    }

    public function updatingResult(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['eventType', 'result', 'search']);
        $this->resetPage();
    }

    public function render()
    {
        $logs = SecurityAuditLog::query()
            ->with(['actor:id,name,email', 'target:id,name,email'])
            ->when($this->eventType !== '', fn ($query) => $query->where('event_type', $this->eventType))
            ->when($this->result !== '', fn ($query) => $query->where('result', $this->result))
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);
                $query->where(function ($sub) use ($search) {
                    $sub->where('event_code', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(15);

        return view('security::settings.livewire.audit-log-screen', [
            'logs' => $logs,
            'eventTypes' => SecurityAuditLog::query()->select('event_type')->distinct()->orderBy('event_type')->pluck('event_type'),
        ]);
    }
}

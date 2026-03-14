<?php

namespace App\Livewire\Admin;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Billing\Models\BillingDocument;

class BillingDocumentsIndex extends Component
{
    use WithPagination;

    #[Url(as: 'status', history: true, keep: true)]
    public string $status = '';

    #[Url(as: 'provider', history: true, keep: true)]
    public string $provider = '';

    #[Url(as: 'date_from', history: true, keep: true)]
    public string $dateFrom = '';

    #[Url(as: 'date_to', history: true, keep: true)]
    public string $dateTo = '';

    #[Url(as: 'search', history: true, keep: true)]
    public string $search = '';

    public ?int $selectedDocumentId = null;

    public array $statuses = ['draft', 'queued', 'issued', 'accepted', 'rejected', 'voided', 'error'];

    public function updatingStatus(): void
    {
        $this->handleFilterMutation();
    }

    public function updatingProvider(): void
    {
        $this->handleFilterMutation();
    }

    public function updatingDateFrom(): void
    {
        $this->handleFilterMutation();
    }

    public function updatingDateTo(): void
    {
        $this->handleFilterMutation();
    }

    public function updatingSearch(): void
    {
        $this->handleFilterMutation();
    }

    public function clearFilters(): void
    {
        $this->reset(['status', 'provider', 'dateFrom', 'dateTo', 'search']);
        $this->selectedDocumentId = null;
        $this->resetPage();
    }

    public function selectDocument(int $documentId): void
    {
        $this->selectedDocumentId = $documentId;
    }

    public function render()
    {
        $documents = BillingDocument::query()
            ->with(['order', 'files'])
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->provider !== '', fn (Builder $query) => $query->where('provider', $this->provider))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('issue_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('issue_date', '<=', $this->dateTo))
            ->when($this->search !== '', function (Builder $query) {
                $search = trim($this->search);

                $query->where(function (Builder $sub) use ($search) {
                    $sub->where('series', 'like', '%'.$search.'%')
                        ->orWhere('number', 'like', '%'.$search.'%')
                        ->orWhere('customer_document_number', 'like', '%'.$search.'%')
                        ->orWhereHas('order', fn (Builder $order) => $order->where('id', $search));
                });
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15);

        $selectedDocument = $documents->firstWhere('id', $this->selectedDocumentId) ?? $documents->first();

        if ($selectedDocument && $this->selectedDocumentId === null) {
            $this->selectedDocumentId = $selectedDocument->id;
        }

        return view('livewire.admin.billing-documents-index', [
            'documents' => $documents,
            'selectedDocument' => $selectedDocument,
            'providers' => config('billing.providers', []),
        ]);
    }

    private function handleFilterMutation(): void
    {
        $this->selectedDocumentId = null;
        $this->resetPage();
    }
}

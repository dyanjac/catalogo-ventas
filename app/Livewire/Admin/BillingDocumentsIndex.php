<?php

namespace App\Livewire\Admin;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Billing\Models\BillingDocument;
use Modules\Billing\Services\ElectronicBillingService;
use Modules\Security\Services\SecurityScopeService;

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

    public string $feedbackMessage = '';

    public string $feedbackType = 'success';

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
        $this->clearFeedback();
        $this->resetPage();
    }

    public function selectDocument(int $documentId): void
    {
        $this->selectedDocumentId = $documentId;
        $this->clearFeedback();
    }

    public function redeclareSelected(ElectronicBillingService $electronicBilling, SecurityScopeService $scopeService): void
    {
        $document = $this->selectedDocument($scopeService);

        if (! $document) {
            $this->setFeedback('warning', 'Selecciona un comprobante antes de ejecutar la re-declaracion.');
            return;
        }

        $payload = is_array($document->request_payload) ? $document->request_payload : [];

        if ($payload === [] || ! isset($payload['items']) || ! is_array($payload['items'])) {
            $this->setFeedback('danger', 'El comprobante no tiene payload valido para re-declarar al proveedor.');
            return;
        }

        $result = $electronicBilling->issueOrQueue($document, $payload);

        if ((bool) ($result['queued'] ?? false)) {
            $this->setFeedback('warning', 'Re-declaracion encolada ('.$result['connection'].'/'.$result['queue'].').');
        } elseif (! (bool) ($result['ok'] ?? false)) {
            $this->setFeedback('warning', 'Re-declaracion enviada con error: '.($result['message'] ?? 'Error no especificado.'));
        } else {
            $this->setFeedback('success', 'Re-declaracion enviada correctamente al proveedor configurado.');
        }

        $this->selectedDocumentId = $document->id;
        $this->resetPage();
    }

    public function render(SecurityScopeService $scopeService)
    {
        $query = BillingDocument::query()
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
            });

        $documents = $scopeService
            ->scopeBillingDocuments($query, auth()->user(), 'billing')
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
            'branchScopeDegraded' => $scopeService->scopeLevelForModule(auth()->user(), 'billing') === 'branch' && $scopeService->branchModeIsDegraded('billing'),
        ]);
    }

    private function handleFilterMutation(): void
    {
        $this->selectedDocumentId = null;
        $this->clearFeedback();
        $this->resetPage();
    }

    private function selectedDocument(SecurityScopeService $scopeService): ?BillingDocument
    {
        if (! $this->selectedDocumentId) {
            return null;
        }

        return $scopeService
            ->scopeBillingDocuments(BillingDocument::query()->with(['order', 'files']), auth()->user(), 'billing')
            ->find($this->selectedDocumentId);
    }

    private function setFeedback(string $type, string $message): void
    {
        $this->feedbackType = $type;
        $this->feedbackMessage = $message;
    }

    private function clearFeedback(): void
    {
        $this->feedbackType = 'success';
        $this->feedbackMessage = '';
    }
}

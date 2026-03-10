<?php

namespace Modules\ElectronicDocuments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Billing\Models\BillingDocument;
use Modules\ElectronicDocuments\Models\DocumentTemplate;
use Modules\ElectronicDocuments\Services\InvoicePdfService;

class DocumentTemplateController extends Controller
{
    public function index(): View
    {
        return view('electronicdocuments::templates.index', [
            'templates' => DocumentTemplate::query()->orderBy('document_type')->orderByDesc('is_active')->paginate(20),
            'sampleXmlOptions' => $this->sampleXmlOptions(),
        ]);
    }

    public function create(): View
    {
        return view('electronicdocuments::templates.create', [
            'types' => DocumentTemplate::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);

        DocumentTemplate::query()->create([
            'company_id' => $data['company_id'] ?? null,
            'name' => $data['name'],
            'document_type' => $data['document_type'],
            'xslt_content' => $data['xslt_content'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('admin.electronic-documents.templates.index')
            ->with('success', 'Plantilla creada correctamente.');
    }

    public function edit(DocumentTemplate $template): View
    {
        return view('electronicdocuments::templates.edit', [
            'template' => $template,
            'types' => DocumentTemplate::TYPES,
        ]);
    }

    public function update(Request $request, DocumentTemplate $template): RedirectResponse
    {
        $data = $this->validateTemplate($request);

        $template->update([
            'company_id' => $data['company_id'] ?? null,
            'name' => $data['name'],
            'document_type' => $data['document_type'],
            'xslt_content' => $data['xslt_content'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('admin.electronic-documents.templates.index')
            ->with('success', 'Plantilla actualizada correctamente.');
    }

    public function destroy(DocumentTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('admin.electronic-documents.templates.index')
            ->with('success', 'Plantilla eliminada.');
    }

    public function toggle(DocumentTemplate $template): RedirectResponse
    {
        $template->update([
            'is_active' => ! $template->is_active,
        ]);

        return back()->with('success', 'Estado de plantilla actualizado.');
    }

    public function preview(Request $request, InvoicePdfService $pdfService): View
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:document_templates,id'],
            'xml_path' => ['required', 'string'],
        ]);

        $template = DocumentTemplate::query()->findOrFail($data['template_id']);
        $html = $pdfService->previewTemplateFromXml($data['xml_path'], (string) $template->xslt_content);

        return view('electronicdocuments::templates.preview', [
            'template' => $template,
            'xmlPath' => $data['xml_path'],
            'html' => $html,
        ]);
    }

    /**
     * @return array{name:string,document_type:string,xslt_content:string,company_id?:int,is_active?:bool}
     */
    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:120'],
            'document_type' => ['required', 'in:'.implode(',', DocumentTemplate::TYPES)],
            'xslt_content' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<int,array{label:string,path:string}>
     */
    private function sampleXmlOptions(): array
    {
        return BillingDocument::query()
            ->whereNotNull('xml_path')
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'series', 'number', 'document_type', 'xml_path'])
            ->map(function (BillingDocument $document): array {
                $label = strtoupper((string) $document->document_type).' '.$document->series.'-'.$document->number;

                return [
                    'label' => $label,
                    'path' => (string) $document->xml_path,
                ];
            })
            ->all();
    }
}


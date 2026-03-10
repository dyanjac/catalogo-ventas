@csrf
@if(isset($method) && strtoupper($method) !== 'POST')
    @method($method)
@endif

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Company ID (opcional)</label>
        <input type="number" name="company_id" class="form-control" value="{{ old('company_id', $template->company_id ?? '') }}">
    </div>
    <div class="col-md-5">
        <label class="form-label">Nombre</label>
        <input type="text" name="name" class="form-control" required value="{{ old('name', $template->name ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Tipo documento</label>
        <select name="document_type" class="form-select" required>
            @foreach($types as $type)
                <option value="{{ $type }}" @selected(old('document_type', $template->document_type ?? '') === $type)>
                    {{ strtoupper($type) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Contenido XSLT</label>
        <textarea name="xslt_content" rows="22" class="form-control font-monospace" required>{{ old('xslt_content', $template->xslt_content ?? '') }}</textarea>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                   @checked(old('is_active', $template->is_active ?? true))>
            <label for="is_active" class="form-check-label">Plantilla activa</label>
        </div>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary rounded-pill px-4">Guardar plantilla</button>
    <a href="{{ route('admin.electronic-documents.templates.index') }}" class="btn btn-light border rounded-pill px-4">Cancelar</a>
</div>


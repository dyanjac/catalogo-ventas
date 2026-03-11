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
        <div class="form-text mt-2">
            Parámetros XSLT disponibles:
            <code>$company_logo_data_uri</code>,
            <code>$company_logo_file_uri</code>,
            <code>$company_logo_url</code>,
            <code>$company_name</code>,
            <code>$company_tax_id</code>,
            <code>$company_address</code>,
            <code>$company_phone</code>,
            <code>$company_mobile</code>,
            <code>$company_email</code>,
            <code>$palette_primary</code>,
            <code>$palette_primary_hover</code>,
            <code>$palette_text</code>,
            <code>$palette_border</code>,
            <code>$palette_sidebar_bg</code>,
            <code>$palette_sidebar_gradient_to</code>,
            <code>$palette_sidebar_text</code>,
            <code>$palette_topbar_bg</code>,
            <code>$palette_topbar_text</code>,
            <code>$palette_active_link_bg</code>,
            <code>$palette_active_link_text</code>,
            <code>$palette_focus_ring</code>.
        </div>
        <div class="form-text">
            Ejemplo: <code>&lt;xsl:param name="company_logo_data_uri"/&gt;</code> y
            <code>&lt;img src="{$company_logo_data_uri}" alt="Logo"/&gt;</code>
        </div>
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

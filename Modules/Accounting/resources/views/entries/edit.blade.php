@extends('layouts.admin')

@section('title', 'Editar asiento contable')

@section('content')
<div class="py-2">
    <div class="container-fluid">
        <x-admin.page-header :title="'Editar asiento #' . $entry->id">
            <x-slot:actions>
                <a href="{{ route('admin.accounting.entries.index') }}" class="btn btn-light border rounded-pill px-4">Volver</a>
            </x-slot:actions>
        </x-admin.page-header>

        <form method="POST" action="{{ route('admin.accounting.entries.update', $entry) }}" class="card border border-secondary rounded-3" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-2">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="entry_date" class="form-control" value="{{ old('entry_date', optional($entry->entry_date)->toDateString()) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo</label>
                        <input type="text" name="voucher_type" class="form-control" value="{{ old('voucher_type', $entry->voucher_type) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Serie</label>
                        <input type="text" name="voucher_series" class="form-control" value="{{ old('voucher_series', $entry->voucher_series) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Número</label>
                        <input type="text" name="voucher_number" class="form-control" value="{{ old('voucher_number', $entry->voucher_number) }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select" required>
                            @foreach($statuses as $entryStatus)
                                <option value="{{ $entryStatus }}" @selected(old('status', $entry->status) === $entryStatus)>{{ strtoupper($entryStatus) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Referencia</label>
                        <input type="text" name="reference" class="form-control" value="{{ old('reference', $entry->reference) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Glosa</label>
                        <textarea name="description" class="form-control" rows="2">{{ old('description', $entry->description) }}</textarea>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle" id="entry-lines-table">
                        <thead class="table-light">
                            <tr>
                                <th>Cuenta</th>
                                <th>Nombre cuenta</th>
                                <th class="text-end">Débito</th>
                                <th class="text-end">Crédito</th>
                                <th>Centro costo</th>
                                <th>Descripción</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $oldLines = old('lines');
                                $lines = is_array($oldLines) ? $oldLines : $entry->lines->map(fn ($line) => [
                                    'account_code' => $line->account_code,
                                    'account_name' => $line->account_name,
                                    'debit' => $line->debit,
                                    'credit' => $line->credit,
                                    'cost_center_id' => $line->cost_center_id,
                                    'line_description' => $line->line_description,
                                ])->toArray();
                            @endphp

                            @foreach($lines as $index => $line)
                                <tr>
                                    <td><input type="text" name="lines[{{ $index }}][account_code]" class="form-control" value="{{ $line['account_code'] ?? '' }}" required></td>
                                    <td><input type="text" name="lines[{{ $index }}][account_name]" class="form-control" value="{{ $line['account_name'] ?? '' }}"></td>
                                    <td><input type="number" step="0.01" min="0" name="lines[{{ $index }}][debit]" class="form-control text-end" value="{{ $line['debit'] ?? 0 }}"></td>
                                    <td><input type="number" step="0.01" min="0" name="lines[{{ $index }}][credit]" class="form-control text-end" value="{{ $line['credit'] ?? 0 }}"></td>
                                    <td>
                                        <select name="lines[{{ $index }}][cost_center_id]" class="form-select">
                                            <option value="">-</option>
                                            @foreach($costCenters as $center)
                                                <option value="{{ $center->id }}" @selected((int) ($line['cost_center_id'] ?? 0) === $center->id)>{{ $center->code }} - {{ $center->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" name="lines[{{ $index }}][line_description]" class="form-control" value="{{ $line['line_description'] ?? '' }}"></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)">Quitar</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="button" class="btn btn-light border rounded-pill px-4" onclick="addLine()">Agregar línea</button>

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Adjuntar documentos de respaldo</label>
                        <input type="file" name="attachments[]" class="form-control" multiple>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Adjuntos actuales</label>
                        <div class="border rounded p-2" style="max-height: 180px; overflow: auto;">
                            @forelse($entry->attachments as $attachment)
                                <div class="d-flex justify-content-between align-items-center gap-2 py-1">
                                    <a href="{{ asset('storage/' . $attachment->path) }}" target="_blank">{{ $attachment->original_name }}</a>
                                    <form method="POST" action="{{ route('admin.accounting.entries.attachments.destroy', [$entry, $attachment]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Quitar</button>
                                    </form>
                                </div>
                            @empty
                                <small class="text-muted">Sin adjuntos.</small>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex gap-2">
                <button class="btn btn-primary rounded-pill px-4">Guardar asiento</button>
                <a href="{{ route('admin.accounting.entries.index') }}" class="btn btn-light border rounded-pill px-4">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
    function removeLine(button) {
        const row = button.closest('tr');
        if (row) row.remove();
    }

    function addLine() {
        const tableBody = document.querySelector('#entry-lines-table tbody');
        const index = tableBody.querySelectorAll('tr').length;
        const row = document.createElement('tr');

        row.innerHTML = `
            <td><input type="text" name="lines[${index}][account_code]" class="form-control" required></td>
            <td><input type="text" name="lines[${index}][account_name]" class="form-control"></td>
            <td><input type="number" step="0.01" min="0" name="lines[${index}][debit]" class="form-control text-end" value="0"></td>
            <td><input type="number" step="0.01" min="0" name="lines[${index}][credit]" class="form-control text-end" value="0"></td>
            <td>
                <select name="lines[${index}][cost_center_id]" class="form-select">
                    <option value="">-</option>
                    @foreach($costCenters as $center)
                        <option value="{{ $center->id }}">{{ $center->code }} - {{ $center->name }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="text" name="lines[${index}][line_description]" class="form-control"></td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)">Quitar</button></td>
        `;

        tableBody.appendChild(row);
    }
</script>
@endsection

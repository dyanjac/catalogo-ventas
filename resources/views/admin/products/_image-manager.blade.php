<div class="col-12">
    <x-admin.info-card title="Imagen principal del producto">

        @if($product->exists && $product->primary_image_path)
            <div class="mb-3">
                <img
                    src="{{ asset('storage/' . $product->primary_image_path) }}"
                    alt="{{ $product->name }}"
                    class="img-fluid rounded border"
                    style="max-height: 240px; object-fit: contain;"
                >
            </div>
            <div class="d-flex gap-2 align-items-center mb-3">
                <span class="badge bg-success">Principal</span>
                <small class="text-muted">SKU vinculado: {{ $product->sku ?? '-' }}</small>
            </div>
        @else
            <p class="text-muted mb-3">Aún no hay imagen cargada para este producto.</p>
        @endif

        @if($product->exists)
            <form method="POST" action="{{ route('admin.products.images.store', $product) }}" enctype="multipart/form-data" class="mb-3">
                @csrf
                <label class="form-label">Reemplazar imagen principal</label>
                <input type="file" name="image_file" class="form-control" accept="image/*" required>
                <small class="text-muted d-block">Formato recomendado: JPG o PNG, hasta 4 MB.</small>
                @error('image_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <button type="submit" class="btn btn-primary btn-sm mt-3">Subir imagen</button>
            </form>
        @else
            <div class="mb-3">
                <label class="form-label">Subir imagen</label>
                <input type="file" name="image_file" class="form-control" accept="image/*">
                <small class="text-muted d-block">Formato recomendado: JPG o PNG, hasta 4 MB.</small>
                @error('image_file') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        @endif

        @if($product->exists && $product->mainImage)
            <form method="POST" action="{{ route('admin.products.images.destroy', [$product, $product->mainImage]) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar imagen principal</button>
            </form>
        @endif
    </x-admin.info-card>
</div>


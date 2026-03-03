<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nombre</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Slug</label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $category->slug) }}" placeholder="Se genera automaticamente si se deja vacio">
    </div>
    <div class="col-12">
        <label class="form-label">Descripcion</label>
        <textarea name="description" rows="4" class="form-control" placeholder="Describe la familia de productos">{{ old('description', $category->description) }}</textarea>
    </div>
</div>


<div class="modal fade" id="authModal" tabindex="-1" aria-labelledby="authModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="authModalLabel">Acceso de Usuario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-pills mb-3" id="authTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="login-tab" data-bs-toggle="pill" data-bs-target="#login-pane" type="button" role="tab">Iniciar sesión</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="register-tab" data-bs-toggle="pill" data-bs-target="#register-pane" type="button" role="tab">Crear cuenta</button>
          </li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade show active" id="login-pane" role="tabpanel" aria-labelledby="login-tab">
            <form method="POST" action="{{ route('login.attempt') }}" class="row g-3">
              @csrf
              <div class="col-12">
                <label class="form-label">Correo</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                @error('email', 'login') <small class="text-danger">{{ $message }}</small> @enderror
              </div>
              <div class="col-12">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" required>
                @error('password', 'login') <small class="text-danger">{{ $message }}</small> @enderror
              </div>
              <div class="col-12 form-check">
                <input type="checkbox" class="form-check-input" name="remember" id="rememberLogin" value="1">
                <label class="form-check-label" for="rememberLogin">Recordarme</label>
              </div>
              <div class="col-12 d-grid">
                <button class="btn btn-primary">Ingresar</button>
              </div>
            </form>
          </div>

          <div class="tab-pane fade" id="register-pane" role="tabpanel" aria-labelledby="register-tab">
            <form method="POST" action="{{ route('register') }}" class="row g-3">
              @csrf
              <div class="col-md-6">
                <label class="form-label">Nombre completo</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                @error('name', 'register') <small class="text-danger">{{ $message }}</small> @enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Correo</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                @error('email', 'register') <small class="text-danger">{{ $message }}</small> @enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Celular</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Tipo doc.</label>
                <select name="document_type" class="form-select">
                  <option value="">Seleccione</option>
                  <option value="dni" @selected(old('document_type') === 'dni')>DNI</option>
                  <option value="ruc" @selected(old('document_type') === 'ruc')>RUC</option>
                  <option value="ce" @selected(old('document_type') === 'ce')>CE</option>
                  <option value="pasaporte" @selected(old('document_type') === 'pasaporte')>Pasaporte</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Nro documento</label>
                <input type="text" name="document_number" value="{{ old('document_number') }}" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Ciudad</label>
                <input type="text" name="city" value="{{ old('city') }}" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label">Dirección</label>
                <input type="text" name="address" value="{{ old('address') }}" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" required>
                @error('password', 'register') <small class="text-danger">{{ $message }}</small> @enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" class="form-control" required>
              </div>
              <div class="col-12 d-grid">
                <button class="btn btn-primary">Crear cuenta</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const shouldOpen = @json(session('openAuthModal') || $errors->login->isNotEmpty() || $errors->register->isNotEmpty());
    if (!shouldOpen) return;

    document.addEventListener('DOMContentLoaded', function () {
      const modalElement = document.getElementById('authModal');
      if (!modalElement || typeof bootstrap === 'undefined') return;
      const modal = new bootstrap.Modal(modalElement);
      modal.show();

      const isRegister = @json(session('openAuthModal') === 'register' || $errors->register->isNotEmpty());
      if (isRegister) {
        const registerTab = document.getElementById('register-tab');
        if (registerTab) registerTab.click();
      }
    });
  })();
</script>

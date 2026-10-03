@props(['name', 'label', 'type' => 'text', 'required' => false, 'hint' => null, 'default' => null])

@php
    $fieldId = 'registration-'.$name;
    $descriptionIds = trim(($hint ? $fieldId.'-hint ' : '').($errors->has($name) ? $fieldId.'-error' : ''));
@endphp
<div class="marketing-form__field">
    <label for="{{ $fieldId }}">{{ $label }} @if($required)<span aria-hidden="true">*</span>@endif</label>
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $default) }}"
        @required($required)
        @if($errors->has($name)) aria-invalid="true" @endif
        @if($descriptionIds) aria-describedby="{{ $descriptionIds }}" @endif
        {{ $attributes }}
    >
    @if($hint)<p id="{{ $fieldId }}-hint" class="marketing-form__help">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $fieldId }}-error" class="marketing-form__error">{{ $message }}</p>@enderror
</div>

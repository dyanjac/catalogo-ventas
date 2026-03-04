@props([
    'emptyMessage' => 'No hay registros para mostrar.',
    'colspan' => 1,
])

<div {{ $attributes->merge(['class' => 'table-responsive']) }}>
    <table class="table table-striped align-middle mb-0">
        @isset($head)
            <thead class="table-light">
                {{ $head }}
            </thead>
        @endisset
        <tbody>
            @if(trim((string) $slot) !== '')
                {{ $slot }}
            @else
                <tr>
                    <td colspan="{{ $colspan }}" class="text-center">{{ $emptyMessage }}</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>

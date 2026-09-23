@props([
    'name' => 'direction_codes',
    'selected' => [],
    'required' => false,
])

@php
    $options = \App\Models\User::directionOptions();
    $selectedValues = old($name, $selected);
    $selectedValues = is_array($selectedValues) ? $selectedValues : [];
@endphp

<div {{ $attributes->class('mb-4') }}>
    <div class="mb-2 flex items-center gap-0.5">
        <x-ui.label :required="$required">Направления</x-ui.label>
    </div>

    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
        @foreach ($options as $code => $label)
            <x-ui.checkbox
                name="{{ $name }}[]"
                :value="$code"
                :label="$label"
                :checked="in_array($code, $selectedValues, true)"
            />
        @endforeach
    </div>

    @error($name)
        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
    @enderror
    @error('direction_codes.*')
        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
    @enderror
</div>

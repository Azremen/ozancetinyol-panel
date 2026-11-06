@props([
    'inlinePrefix' => false,
    'inlineSuffix' => false,
])

@php
    $attributeBag = $attributes->except(['id', 'name']);
    $id = $attributes->get('id') ?: 'fi-select-input-' . \Illuminate\Support\Str::uuid()->toString();
    $name = $attributes->get('name') ?: $attributes->get('wire:model') ?: $id;
@endphp

<select
    id="{{ $id }}"
    name="{{ $name }}"
    {{
        $attributeBag->class([
            'fi-select-input',
            'fi-select-input-has-inline-prefix' => $inlinePrefix,
        ])
    }}
>
    {{ $slot }}
</select>

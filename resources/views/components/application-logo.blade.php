@props(['style' => ''])

@switch($style)

    @case('bw')
        <img src="{{ asset('images/zl-bw.png') }}" alt="Logo" {{ $attributes->merge(['class' => 'w-24']) }}>
        @break

    @default
        <img src="{{ asset('images/zl.png') }}" alt="Logo" {{ $attributes->merge(['class' => 'w-24']) }}>
        @break

@endswitch

@php
    $colorClass = "text-{$color}-600";
    $lineColorClass = "bg-{$color}-600";
@endphp

<a href="{{ $href }}"
   class="group block w-fit mx-auto font-mono font-bold text-center mt-10 text-black">
    
    <div class="leading-none">{{ $slot }}</div>

    <div class="relative inline-flex items-center justify-center w-full mt-[-0.5rem]">
        <span class="h-[2px] w-full transition-colors duration-300 {{ $lineColorClass }}"></span>
        <i class="bi bi-caret-right-fill -ml-[6px] text-right {{ $colorClass }}"></i>
    </div>
</a>

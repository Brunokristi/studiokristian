@php
    $textColor = "text-{$color}";
    $bgColor = "bg-{$color}";
@endphp


<a href="{{ $href }}"
   class="group block w-fit mx-auto font-mono font-bold text-center mt-10 text-black text-tiny">
    
    <div class="leading-none {{ $textColor }}">{{ $slot }}</div>

    <div class="relative inline-flex items-center justify-center w-full mt-[-0.5rem]">
        <span class="h-[1px] w-full bg-[#133EB4]"></span>
        <i class="bi bi-caret-right-fill -ml-[6px] text-right {{ $textColor }}"></i>
    </div>
</a>

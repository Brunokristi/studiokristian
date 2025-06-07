<section class="relative w-full h-[500px] overflow-hidden text-center mt-20 flex items-center justify-center">
    <img src="{{ asset('images/background.png') }}"
         alt="Workflow Background"
         class="absolute top-1/2 left-1/2 
                w-auto h-full object-cover 
                -translate-x-1/2 -translate-y-1/2 z-0" />


    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 
                w-[280px] h-[280px] text-white font-bold font-mono text-large 
                z-10 pointer-events-none flex flex-col items-center justify-center gap-4 text-xs text-center leading-snug px-4">

        <img src="{{ asset('images/text.svg') }}"
             alt="Frame text"
             class="w-full h-auto" />
    </div>

    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 
                w-[120px] h-[280px] text-white font-mono text-small font-light
                z-20 pointer-events-none flex flex-col items-center justify-center gap-4">
        <p class="font-sans text-left">
            With a personalized workflow<br />
            and regular updates, you’ll<br />
            always know exactly what’s going on.
        </p>
    </div>
</section>

<section>
    <x-arrow-link href="/contact" color="bg">
        Peek inside the process
    </x-arrow-link>
</section>

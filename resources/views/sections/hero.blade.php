<section class="relative w-full h-[200px] mt-16">
    <svg id="responsive-scoop" class="absolute inset-0 w-full h-full" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="scoopGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#133EB4" />
                <stop offset="100%" stop-color="#000000" />
            </linearGradient>
        </defs>
        <path id="scoopPath" fill="url(#scoopGradient)" />
    </svg>

    <div class="relative z-10 flex items-center justify-center h-full text-white text-center">
        <div>
            <h2 class="text-huge font-bold font-mono">FUNCTION</h2>
            <p class="text-small font-sans mt-4 text-left">Web solutions that perform,<br />convert and scale.</p>
        </div>
    </div>
</section>

<section class="relative w-full h-[200px] -mt-1">
    <svg id="responsive-scoop-design" class="absolute inset-0 w-full h-full" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="scoopGradientDesign" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#000000" />
                <stop offset="100%" stop-color="#133EB4" />
            </linearGradient>
        </defs>
        <path id="scoopPathDesign" fill="url(#scoopGradientDesign)" />
    </svg>

    <div class="relative z-10 flex items-center justify-center h-full text-white text-center">
        <div>
            <h2 class="text-huge font-bold font-mono">DESIGN</h2>
            <p class="text-small font-sans mt-4 text-right">Designs that sell, not just<br />sit pretty.</p>
        </div>
    </div>

    <x-arrow-link href="/contact" color="blue">
        Lets build your next weapon
    </x-arrow-link>



</div>

</section>

<!-- SCRIPT to draw both -->
<script>
    function drawScoop() {
        const width = window.innerWidth;
        const height = 200;
        const scoop = 150;

        // LEFT SCOOP (FUNCTION)
        const dLeft = `
            M${scoop},0
            C${scoop - 150},0 ${scoop - 150},${height} ${scoop},${height}
            H${width}
            V0
            Z
        `;

        const svgLeft = document.getElementById('responsive-scoop');
        svgLeft.setAttribute('viewBox', `0 0 ${width} ${height}`);
        document.getElementById('scoopPath').setAttribute('d', dLeft);

        // RIGHT SCOOP (DESIGN)
        const dRight = `
            M0,0
            H${width - scoop}
            C${width - scoop + 150},0 ${width - scoop + 150},${height} ${width - scoop},${height}
            H0
            V0
            Z
        `;

        const svgRight = document.getElementById('responsive-scoop-design');
        svgRight.setAttribute('viewBox', `0 0 ${width} ${height}`);
        document.getElementById('scoopPathDesign').setAttribute('d', dRight);
    }

    window.addEventListener('DOMContentLoaded', drawScoop);
    window.addEventListener('resize', drawScoop);
</script>

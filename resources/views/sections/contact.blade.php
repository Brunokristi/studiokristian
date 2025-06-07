<section class="mt-20">
    <x-arrow-link href="/contact" color="bg">
        Add us to your contacts    
    </x-arrow-link>
</section>

<section>
    <div id="card-container" class="w-64 h-96 mx-auto my-10 perspective-[1000px] cursor-grab select-none touch-none">
        <div id="card"
        class="relative w-full h-full"
        style="transform-style: preserve-3d; will-change: transform; transition: transform 0.3s ease-out;">


            <div class="absolute w-full h-full shadow-xl"
                style="backface-visibility: hidden; transform: rotateY(0deg);">
                <div class="bg-gradient-to-tr from-accent to-black text-white font-mono font-bold w-full h-full relative">
                    <div class="absolute top-4 left-4 text-sm">studio</div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <img src="{{ asset('images/logo_white.png') }}"
                            class="w-12 pointer-events-none select-none"
                            draggable="false"
                            alt="Front logo" />
                    </div>
                    <div class="absolute bottom-4 right-4 text-sm">kristian</div>
                </div>
            </div>

            <div class="absolute w-full h-full shadow-xl"
                style="backface-visibility: hidden; transform: rotateY(180deg);">
                <div class="bg-gradient-to-tr from-black to-accent text-white font-mono font-bold w-full h-full flex items-center justify-center">
                    <div class="text-center">
                        <img src="{{ asset('images/logo_white.png') }}"
                            class="w-12 pointer-events-none select-none"
                            draggable="false"
                            alt="Back logo" />
                        <p class="text-sm mt-2">Your Back Info Here</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<section>
    <x-arrow-link href="/contact" color="accent">
        Text us right away    
    </x-arrow-link>
</section>

<style>
  .select-none {
    user-select: none;
  }
</style>

<script>
  const card = document.getElementById('card');
  const container = document.getElementById('card-container');

  let isDragging = false;
  let startX = 0;
  let rotationY = 0;
  let targetRotationY = 0;
  let autoRotate = true;

  function animate() {
    rotationY += (targetRotationY - rotationY) * 0.1;
    card.style.transform = `rotateY(${rotationY}deg)`;
    requestAnimationFrame(animate);
  }

  animate(); // Start the animation loop

  setInterval(() => {
    if (!autoRotate) return;
    targetRotationY += 0.5;
  }, 30);

  container.addEventListener('mousedown', (e) => {
    isDragging = true;
    autoRotate = false;
    startX = e.clientX;
  });

  document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    const deltaX = e.clientX - startX;
    targetRotationY = rotationY + deltaX * 0.5;
  });

  document.addEventListener('mouseup', (e) => {
    if (!isDragging) return;
    isDragging = false;
    const deltaX = e.clientX - startX;
    targetRotationY = rotationY + deltaX * 0.5;
  });

  container.addEventListener('touchstart', (e) => {
    isDragging = true;
    autoRotate = false;
    startX = e.touches[0].clientX;
  });

  container.addEventListener('touchmove', (e) => {
    if (!isDragging) return;
    const deltaX = e.touches[0].clientX - startX;
    targetRotationY = rotationY + deltaX * 0.5;
  });

  container.addEventListener('touchend', (e) => {
    if (!isDragging) return;
    isDragging = false;
    const deltaX = e.changedTouches[0].clientX - startX;
    targetRotationY = rotationY + deltaX * 0.5;
  });
</script>

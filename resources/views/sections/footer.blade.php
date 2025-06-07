<section class="relative w-full h-[100px] overflow-hidden">
  <svg viewBox="0 0 100 50" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"
       class="absolute inset-0 w-full h-full">
    <path d="M0,0 L50,40 L100,0 L100,50 L0,50 Z" fill="black" />
  </svg>
</section>

<footer class="bg-black text-white py-10 text-sm font-mono">
  <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-4 text-center md:text-left">
    
    <div>
      <p>&copy; {{ date('Y') }} Studio Kristian. All rights reserved.</p>
    </div>

    <div class="flex gap-6">
      <a href="/privacy" class="hover:underline">Privacy</a>
      <a href="/terms" class="hover:underline">Terms</a>
      <a href="/contact" class="hover:underline">Contact</a>
    </div>

  </div>
</footer>

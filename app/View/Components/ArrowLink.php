<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ArrowLink extends Component
{
    public string $href;
    public string $color;

    public function __construct(string $href, string $color = 'blue')
    {
        $this->href = $href;
        $this->color = $color;
    }


    public function render()
    {
        return view('components.arrow-link');
    }
}

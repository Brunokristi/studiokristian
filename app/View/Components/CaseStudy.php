<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class CaseStudy extends Component
{
    public string $title;
    public string $text;
    public string $href;

    public function __construct(string $title, string $text, string $href)
    {
        $this->title = $title;
        $this->text = $text;
        $this->href = $href;
    }


    public function render(): View|Closure|string
    {
        return view('components.case-study');
    }
}

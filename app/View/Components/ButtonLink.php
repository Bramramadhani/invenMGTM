<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ButtonLink extends Component
{
    public $icon, $url, $title;

    /**
     * Create a new component instance.
     */
    public function __construct($icon, $url, $title)
    {
        $this->icon = $icon;
        $this->url = $url;
        $this->title = $title;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.button-link');
    }
}

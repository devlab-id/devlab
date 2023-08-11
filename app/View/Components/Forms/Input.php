<?php

namespace App\View\Components\Forms;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Visus\Cuid2\Cuid2;

class Input extends Component
{
    public function __construct(
        public string|null $id = null,
        public string|null $name = null,
        public string|null $type = 'text',
        public string|null $value = null,
        public string|null $label = null,
        public bool        $required = false,
        public bool        $disabled = false,
        public bool        $readonly = false,
        public string|null $helper = null,
        public bool        $allowToPeak = true,
        public string      $defaultClass = "input input-sm bg-coolgray-200 rounded text-white w-full disabled:bg-coolgray-200/50 disabled:border-none placeholder:text-coolgray-500 read-only:text-neutral-500 read-only:bg-coolgray-200/50"
    ) {
    }

    public function render(): View|Closure|string
    {
        if (is_null($this->id)) $this->id = new Cuid2(7);
        if (is_null($this->name)) $this->name = $this->id;

        // $this->label = Str::title($this->label);
        return view('components.forms.input');
    }
}

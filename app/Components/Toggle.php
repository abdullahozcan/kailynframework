<?php

namespace App\Components;

use Kailyn\Component\Attributes\Reactive;
use Kailyn\Component\Component;

class Toggle extends Component
{
    #[Reactive]
    public bool $on = false;

    public string $label = 'Toggle';

    public function toggle(): void
    {
        $this->on = !$this->on;
    }
}

<?php

namespace App\Components;

use Kailyn\Component\Attributes\Reactive;
use Kailyn\Component\Component;

class Counter extends Component
{
    #[Reactive]
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function decrement(): void
    {
        $this->count--;
    }

    public function reset(): void
    {
        $this->count = 0;
    }

    public function add(int $amount): void
    {
        $this->count += $amount;
    }
}

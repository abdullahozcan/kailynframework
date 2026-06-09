<?php

namespace App\Components;

use Kailyn\Component\Attributes\Reactive;
use Kailyn\Component\Component;

class Counter extends Component
{
    #[Reactive]
    public int $count = 0;

    protected array $watchers = [
        'count' => 'onCountChanged',
    ];

    public function mount(array $props = []): void
    {
        if (isset($props['start'])) {
            $this->count = (int) $props['start'];
        }
    }

    public function updatedCount(int $old, int $new): void
    {
        if ($new < 0) {
            $this->addError('count', 'Count cannot be negative');
        }
    }

    protected function onCountChanged(int $old, int $new): void
    {
        // logged via watcher
    }

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

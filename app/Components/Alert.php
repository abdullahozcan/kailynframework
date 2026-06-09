<?php

namespace App\Components;

use Kailyn\Component\Attributes\Reactive;
use Kailyn\Component\Component;

class Alert extends Component
{
    #[Reactive]
    public string $message = 'Hello from alert!';

    public function update(string $message): void
    {
        $this->message = $message;
    }
}

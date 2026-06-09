<?php

namespace App\Components;

use Kailyn\Component\Attributes\Action;
use Kailyn\Component\Attributes\Reactive;
use Kailyn\Component\Component;

class Alert extends Component
{
    #[Reactive]
    public string $message = 'Hello from alert!';

    #[Action]
    public function update(string $message): void
    {
        $this->message = $message;
    }
}

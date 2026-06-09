<?php

namespace App\Components;

use Kailyn\Component\Attributes\Reactive;
use Kailyn\Component\Component;

class Autocomplete extends Component
{
    #[Reactive]
    public string $query = '';

    private array $items = [
        'Apple', 'Banana', 'Cherry', 'Date', 'Elderberry',
        'Fig', 'Grape', 'Honeydew', 'Kiwi', 'Lemon',
        'Mango', 'Nectarine', 'Orange', 'Papaya', 'Quince',
    ];

    public function updatedQuery(): void {}

    public function select(string $value): string
    {
        $this->query = $value;
        return $value;
    }

    public function search(): array
    {
        if (empty($this->query)) {
            return [];
        }

        $q = strtolower($this->query);
        return array_values(array_filter($this->items, fn($item) => str_contains(strtolower($item), $q)));
    }

    public function getResultsProperty(): array
    {
        return $this->search();
    }
}

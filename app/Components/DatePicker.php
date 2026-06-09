<?php

namespace App\Components;

use Kailyn\Component\Attributes\Action;
use Kailyn\Component\Attributes\Computed;
use Kailyn\Component\Attributes\Reactive;
use Kailyn\Component\Component;

class DatePicker extends Component
{
    #[Reactive]
    public ?string $selected = null;

    #[Reactive]
    public int $year;

    #[Reactive]
    public int $month;

    public function __construct()
    {
        $this->year = (int) date('Y');
        $this->month = (int) date('m');
    }

    #[Action]
    public function selectDate(string $date): void
    {
        $this->selected = $date;
    }

    #[Action]
    public function prevMonth(): void
    {
        $this->month--;
        if ($this->month < 1) {
            $this->month = 12;
            $this->year--;
        }
    }

    #[Action]
    public function nextMonth(): void
    {
        $this->month++;
        if ($this->month > 12) {
            $this->month = 1;
            $this->year++;
        }
    }

    #[Computed]
    public function getCalendarProperty(): array
    {
        $firstDay = mktime(0, 0, 0, $this->month, 1, $this->year);
        $daysInMonth = (int) date('t', $firstDay);
        $startOffset = (int) date('w', $firstDay);
        $weeks = [];
        $week = array_fill(0, $startOffset, null);

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $week[] = sprintf('%04d-%02d-%02d', $this->year, $this->month, $d);
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        if (count($week) > 0) {
            $weeks[] = $week;
        }

        return $weeks;
    }
}

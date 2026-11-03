<?php

namespace App\Filament\Pages;

use App\Services\PartnerFunnelReport;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PartnerFunnel extends Page
{
    protected static ?string $title = 'Воронка партнёров';

    protected string $view = 'filament.pages.partner-funnel';

    public string $from;

    public string $to;

    #[\Livewire\Attributes\Locked]
    public array $period = [];

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
        $this->applyPeriod();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function applyPeriod(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->period = $this->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

    }

    public function rows(): Collection
    {
        abort_unless(static::canAccess(), 403);

        return app(PartnerFunnelReport::class)->rows($this->period['from'], Carbon::parse($this->period['to'])->addDay()->toDateString());
    }
}

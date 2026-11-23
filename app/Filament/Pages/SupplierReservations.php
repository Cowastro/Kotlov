<?php

namespace App\Filament\Pages;

use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Models\User;
use App\Services\Orders\SupplierOrderRequestWorkflow;
use App\Services\Orders\SupplierReservationDesk;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class SupplierReservations extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Резервы поставщиков';

    protected static ?string $title = 'Резервы поставщиков';

    protected static ?string $slug = 'supplier-reservations';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.supplier-reservations';

    #[Url]
    public string $search = '';

    #[Url]
    public string $state = 'active';

    #[Url]
    public string $supplier = '';

    #[Url]
    public string $manager = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->is_active && auth()->user()?->isManager();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }
        $count = app(SupplierReservationDesk::class)->query(auth()->user())->whereIn('state', ['draft', 'waiting', 'rejected'])->count();

        return $count ? (string) $count : null;
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'state', 'supplier', 'manager'])) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'state', 'supplier', 'manager');
        $this->resetPage();
    }

    public function recordResponseAction(): Action
    {
        return Action::make('recordResponse')->label('Ответ поставщика')
            ->modalHeading('Зафиксировать ответ поставщика')->modalSubmitActionLabel('Сохранить ответ')
            ->modalDescription('Отметьте фактический ответ. Письмо поставщику не отправляется, остатки и резерв в 1С не изменяются.')
            ->schema([
                Select::make('status')->label('Результат')->options(['acknowledged' => 'Резерв подтверждён', 'rejected' => 'Поставщик отказал'])->required(),
                Textarea::make('note')->label('Что подтвердил поставщик: количество, срок, условия')->required()->maxLength(3000),
            ])->action(function (array $data, array $arguments): void {
                abort_unless(static::canAccess(), 403);
                $id = (int) ($arguments['request'] ?? 0);
                abort_unless(app(SupplierReservationDesk::class)->query(auth()->user())->where('kind', 'request')->where('id', $id)->exists(), 404);
                app(SupplierOrderRequestWorkflow::class)->recordEmailResponse(SupplierOrderRequest::findOrFail($id), $data['status'], auth()->user(), $data['note']);
                Notification::make()->success()->title('Ответ поставщика сохранён')->send();
            });
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $desk = app(SupplierReservationDesk::class);
        $base = $desk->query(auth()->user(), $this->search);
        $suppliers = Supplier::whereIn('id', (clone $base)->select('supplier_id'))->orderBy('name')->pluck('name', 'id');
        $managers = User::whereIn('id', (clone $base)->select('manager_id'))->orderBy('name')->pluck('name', 'id');
        if ($this->supplier !== '') {
            $base->where('supplier_id', $this->supplier);
        }
        if ($this->manager !== '') {
            $base->where('manager_id', $this->manager);
        }
        $counts = (clone $base)->selectRaw('state, count(*) as aggregate')->groupBy('state')->pluck('aggregate', 'state');
        if ($this->state === 'active') {
            $base->whereIn('state', ['draft', 'waiting', 'confirmed', 'rejected']);
        } elseif ($this->state === 'archive') {
            $base->whereIn('state', ['fulfilled', 'cancelled', 'closed']);
        } elseif (isset(SupplierReservationDesk::STATES[$this->state])) {
            $base->where('state', $this->state);
        }
        $page = $base->orderByDesc('sort_at')->orderBy('kind')->orderByDesc('id')->paginate(20);

        return ['rows' => $desk->rows(collect($page->items()), auth()->user()), 'pagination' => $page,
            'counts' => $counts, 'suppliers' => $suppliers, 'managers' => $managers];
    }
}

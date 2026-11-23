<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VamExpressDrafts\VamExpressDraftForm;
use App\Filament\Resources\VamExpressDrafts\VamExpressDraftResource;
use App\Services\Shipping\VamExpressDrafts;
use App\Services\Shipping\VamExpressException;
use App\Services\Shipping\VamExpressQuote;
use App\Services\Shipping\VamExpressReference;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class VamExpressCalculator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $title = 'Предварительный расчёт ВАМ Экспресс';

    protected static ?string $navigationLabel = 'Калькулятор ВАМ';

    protected static ?string $slug = 'vam-express-calculator';

    protected string $view = 'filament.pages.vam-express-calculator';

    public ?array $data = [];

    #[Locked]
    public string $draftKey = '';

    #[Locked]
    public ?array $quote = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->is_active && auth()->user()?->isManager();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Доставки';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->draftKey = (string) Str::uuid();
        $defaults = VamExpressDrafts::defaults();
        try {
            $defaults = array_replace($defaults, app(VamExpressReference::class)->sender());
        } catch (VamExpressException $e) {
            Notification::make()->warning()->title($e->getMessage())->send();
        }
        $this->form->fill($defaults);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(VamExpressDraftForm::pricing())->statePath('data');
    }

    public function updatedData(): void
    {
        $this->quote = null;
    }

    public function calculate(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->quote = null;
        try {
            $this->quote = app(VamExpressQuote::class)->estimate($this->form->getState());
        } catch (VamExpressException $e) {
            Notification::make()->warning()->title($e->getMessage())->send();
        } catch (ValidationException $e) {
            Notification::make()->warning()->title('Заполните параметры расчёта')->body(implode(' ', array_slice($e->validator->errors()->all(), 0, 3)))->send();
        }
    }

    public function saveDraft(): void
    {
        abort_unless(static::canAccess(), 403);
        $payload = $this->form->getState();
        $key = hash('sha256', $this->draftKey.json_encode($payload, JSON_THROW_ON_ERROR));
        $draft = app(VamExpressDrafts::class)->create($payload, auth()->user(), creationKey: $key);
        if ($this->quote && $this->quote['fingerprint'] === VamExpressQuote::fingerprint($payload)) {
            app(VamExpressDrafts::class)->attachQuote($draft, $this->quote, $draft->version, auth()->user());
        }
        $this->redirect(VamExpressDraftResource::getUrl('edit', ['record' => $draft]));
    }
}

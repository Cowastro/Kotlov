<?php

namespace App\Filament\Pages;

use App\Services\PhoneVerificationService;
use App\Services\SmsByClient;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use RuntimeException;

class SmsByDiagnostics extends Page
{
    protected static ?string $title = 'Диагностика SMS.BY';

    protected static ?string $slug = 'sms-by-diagnostics';

    protected string $view = 'filament.pages.sms-by-diagnostics';

    public string $phone = '';

    public string $code = '';

    #[Locked]
    public ?array $connection = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public function checkConnection(SmsByClient $sms): void
    {
        abort_unless(static::canAccess(), 403);
        $this->resetErrorBag('connection');
        $this->connection = null;

        try {
            $this->connection = $sms->diagnostics();
        } catch (RuntimeException $exception) {
            $this->addError('connection', $exception->getMessage());
        }
    }

    public function sendTest(SmsByClient $sms, PhoneVerificationService $verification): void
    {
        abort_unless(static::canAccess(), 403);
        $this->resetErrorBag('phone');
        $this->validate(['phone' => ['required', 'string', 'max:30']]);
        $phone = $verification->normalize($this->phone);

        if (RateLimiter::hit('sms-by-test:'.auth()->id(), 60) > 1) {
            $this->addError('phone', 'Следующий тест можно отправить через минуту.');

            return;
        }

        try {
            $sent = $sms->sendTestCode($phone);
        } catch (RuntimeException $exception) {
            $this->addError('phone', $exception->getMessage());

            return;
        }

        session()->put($this->testKey(), [
            'phone' => '…'.substr($phone, -4),
            'sms_id' => $sent['sms_id'],
            'parts' => $sent['parts'],
            'status' => 'Принято SMS.BY',
            'hash' => Hash::make($sent['code']),
            'expires_at' => now()->addMinutes(5)->timestamp,
            'attempts' => 0,
            'confirmed' => false,
        ]);
        $this->reset('code');
        $this->resetErrorBag(['code', 'delivery']);
    }

    public function checkDelivery(SmsByClient $sms): void
    {
        abort_unless(static::canAccess(), 403);
        $this->resetErrorBag('delivery');
        $test = session()->get($this->testKey());
        if (empty($test['sms_id'])) {
            return;
        }

        try {
            session()->put($this->testKey().'.status', $sms->deliveryStatus($test['sms_id']));
        } catch (RuntimeException $exception) {
            $this->addError('delivery', $exception->getMessage());
        }
    }

    public function confirmCode(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->resetErrorBag('code');
        $this->validate(['code' => ['required', 'digits:6']]);
        $test = session()->get($this->testKey());
        if (empty($test['hash']) || $test['expires_at'] <= now()->timestamp || $test['attempts'] >= 5) {
            $this->addError('code', 'Код больше недействителен. Отправьте новый тест.');

            return;
        }

        session()->increment($this->testKey().'.attempts');
        if (! Hash::check($this->code, $test['hash'])) {
            $this->reset('code');
            $this->addError('code', 'Неверный код.');

            return;
        }

        session()->forget($this->testKey().'.hash');
        session()->put($this->testKey().'.confirmed', true);
        $this->reset('code');
    }

    protected function getViewData(): array
    {
        return ['test' => session()->get($this->testKey())];
    }

    private function testKey(): string
    {
        return 'sms_by_test_'.auth()->id();
    }
}

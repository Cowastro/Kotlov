<?php

namespace App\Filament\Pages;

use App\Models\LeadChannel;
use App\Services\LeadChannelApi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class LeadChannelSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $title = 'Каналы обращений';

    protected static ?string $slug = 'lead-channels';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.lead-channel-settings';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public function configureChannelAction(): Action
    {
        return Action::make('configureChannel')
            ->authorize(fn (): bool => static::canAccess())
            ->modalHeading(fn (array $arguments): string => 'Настроить '.LeadChannel::LABELS[$this->channel($arguments)->provider])
            ->modalSubmitActionLabel('Сохранить настройки')
            ->fillForm(fn (array $arguments): array => $this->channel($arguments)->only([
                'enabled', 'account_id', 'api_version', 'public_url',
            ]))
            ->schema(function (array $arguments): array {
                $channel = $this->channel($arguments);

                return [
                    Toggle::make('enabled')->label('Принимать обращения'),
                    TextInput::make('account_id')
                        ->label(match ($channel->provider) {
                            'whatsapp' => 'Phone Number ID',
                            'instagram' => 'ID профессионального аккаунта',
                            default => 'URI бота Viber',
                        })
                        ->visible($channel->provider !== 'telegram')
                        ->maxLength(100)
                        ->regex($channel->usesMeta() ? '/^\d+$/D' : '/^[A-Za-z0-9_.-]+$/D'),
                    TextInput::make('api_version')->label('Версия Graph API')
                        ->visible($channel->usesMeta())->placeholder('v25.0')->regex('/^v\d{1,3}\.\d{1,2}$/D'),
                    TextInput::make('access_token')->label('Токен доступа')->password()->maxLength(4096)
                        ->regex($channel->provider === 'telegram' ? '/^\d+:[A-Za-z0-9_-]+$/D' : '/^[!-~]+$/D')
                        ->helperText($channel->access_token ? 'Токен сохранён. Заполните для замены.' : null),
                    TextInput::make('webhook_secret')->label($channel->usesMeta() ? 'App Secret' : 'Секрет вебхука')
                        ->visible($channel->provider !== 'viber')->password()->maxLength(256)
                        ->regex($channel->usesMeta() ? '/^[!-~]+$/D' : '/^[A-Za-z0-9_-]{16,256}$/D')
                        ->helperText($channel->webhook_secret ? 'Секрет сохранён. Заполните для замены.' : null),
                    TextInput::make('verify_token')->label('Verify Token для Meta')->visible($channel->usesMeta())
                        ->password()->maxLength(256)->regex('/^[A-Za-z0-9_-]{16,256}$/D')
                        ->helperText($channel->verify_token ? 'Токен сохранён. Заполните для замены.' : 'Придумайте токен и укажите его также в настройках Meta.'),
                    TextInput::make('public_url')->label('Ссылка «Написать» для сайта')->maxLength(500)
                        ->helperText('Оставьте пустой, если канал предназначен только для CRM.')
                        ->rules([fn () => function (string $attribute, mixed $value, \Closure $fail) use ($channel): void {
                            if (! is_string($value) || ! $this->validPublicUrl($channel->provider, $value)) {
                                $fail('Укажите ссылку на чат в выбранном мессенджере.');
                            }
                        }]),
                ];
            })
            ->action(function (array $arguments, array $data, Action $action): void {
                abort_unless(static::canAccess(), 403);
                $channel = $this->channel($arguments);
                foreach (['access_token', 'webhook_secret', 'verify_token'] as $key) {
                    $data[$key] = filled($data[$key] ?? null) ? $data[$key] : $channel->{$key};
                }

                if (($data['enabled'] ?? false) && ! $this->completeSettings($channel, $data)) {
                    Notification::make()->title('Заполните настройки подключения')->danger()->send();
                    $action->halt();
                }

                $channel->fill($data)->save();
                Notification::make()->title('Настройки канала сохранены')->success()->send();
            });
    }

    public function registerWebhookAction(): Action
    {
        return Action::make('registerWebhook')
            ->authorize(fn (): bool => static::canAccess())
            ->requiresConfirmation()
            ->modalHeading('Зарегистрировать вебхук')
            ->modalDescription('Мессенджер начнёт отправлять события этого бота на адрес CRM. Текущий вебхук бота будет заменён.')
            ->action(function (array $arguments, LeadChannelApi $api): void {
                abort_unless(static::canAccess(), 403);
                $channel = $this->channel($arguments);
                abort_unless(! $channel->usesMeta() && $channel->acceptsMessages() && filled($channel->access_token), 422);

                if (str_starts_with($channel->webhookUrl(), 'https://') && $api->registerWebhook($channel)) {
                    Notification::make()->title('Вебхук зарегистрирован')->success()->send();

                    return;
                }

                Notification::make()->title('Вебхук не зарегистрирован')
                    ->body('Проверьте токен бота и доступность адреса CRM по HTTPS.')
                    ->danger()->send();
            });
    }

    private function channel(array $arguments): LeadChannel
    {
        $provider = $arguments['provider'] ?? '';
        abort_unless(is_string($provider) && isset(LeadChannel::LABELS[$provider]), 404);

        return LeadChannel::settings($provider);
    }

    private function completeSettings(LeadChannel $channel, array $data): bool
    {
        return filled($data['access_token']) && match ($channel->provider) {
            'telegram' => filled($data['webhook_secret']),
            'viber' => filled($data['account_id'] ?? null),
            default => filled($data['account_id'] ?? null) && filled($data['api_version'] ?? null)
                && filled($data['verify_token']) && filled($data['webhook_secret']),
        };
    }

    private function validPublicUrl(string $provider, string $url): bool
    {
        if ($provider === 'viber' && preg_match('~^viber://pa\?chatURI=[A-Za-z0-9_.-]+$~D', $url)) {
            return true;
        }

        $hosts = match ($provider) {
            'telegram' => ['t.me'],
            'viber' => ['viber.com', 'www.viber.com', 'vb.me', 'invite.viber.com'],
            'instagram' => ['instagram.com', 'www.instagram.com', 'ig.me'],
            'whatsapp' => ['wa.me', 'api.whatsapp.com'],
        };

        return filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && parse_url($url, PHP_URL_USER) === null
            && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), $hosts, true);
    }
}

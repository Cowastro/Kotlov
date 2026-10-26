<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(EditProfile::class, isSimple: false)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.admin.layout-fixes'),
            )
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('Что нового')
                    ->icon('heroicon-o-megaphone')
                    ->sort(100)
                    ->url(fn (): string => route('admin.releases.index')),
                NavigationItem::make('Резервы поставщиков')
                    ->group('Продажи')
                    ->icon('heroicon-o-envelope')
                    ->sort(2)
                    ->url(fn (): string => CrmMailThreadResource::getUrl('index', [
                        'mailbox' => (string) self::orderMailboxId(),
                        'tab' => 'all',
                    ], panel: 'admin'))
                    ->badge(fn (): ?string => ($count = self::activeOrderMailboxThreads()) > 0 ? (string) $count : null, 'warning')
                    ->visible(fn (): bool => self::orderMailboxId() !== null)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.crm-mail-threads.*')
                        && (int) request()->query('mailbox') === self::orderMailboxId()),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private static function orderMailboxId(): ?int
    {
        return CrmMailbox::query()
            ->whereRaw('LOWER(email) = ?', ['order@kotlov.by'])
            ->where('is_enabled', true)
            ->value('id');
    }

    private static function activeOrderMailboxThreads(): int
    {
        $mailboxId = self::orderMailboxId();

        return $mailboxId
            ? CrmMailThread::query()
                ->where('mailbox_id', $mailboxId)
                ->whereNotIn('status', ['closed', 'spam'])
                ->count()
            : 0;
    }
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\ProtectPublicForm;
use App\Models\InstallerApplication;
use App\Services\InstallerApplicationTelegramNotifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class InstallerRecruitmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('installer_applications');
        Schema::create('installer_applications', function (Blueprint $table) {
            $table->id();
            $table->string('contact_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('company_name')->nullable();
            $table->integer('experience_years')->nullable();
            $table->json('specializations')->nullable();
            $table->text('message')->nullable();
            $table->string('source', 50)->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamp('telegram_notified_at')->nullable();
            $table->string('status')->default('new');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_installers_catalog_contains_quick_recruitment_form(): void
    {
        $template = file_get_contents(resource_path('views/pages/installers.blade.php'));

        $this->assertStringContainsString('Получайте клиентов в своём регионе', $template);
        $this->assertStringContainsString('Заявки без комиссии', $template);
        $this->assertStringContainsString('name="_source" value="installers-catalog"', $template);
        $this->assertStringContainsString('<x-form-protection />', $template);
    }

    public function test_quick_application_is_attributed_and_returns_to_catalog(): void
    {
        $this->withoutMiddleware([
            ProtectPublicForm::class,
            \App\Http\Middleware\HandleRedirects::class,
        ]);

        $response = $this->post(route('partners.apply-installer'), [
            '_source' => 'installers-catalog',
            'contact_name' => 'Тестовый монтажник',
            'phone' => '+375 29 123-45-67',
            'city' => 'Гродно',
        ]);

        $response->assertRedirect(route('installers.index') . '#installer-join');
        $response->assertSessionHas('installer_success');

        $this->assertDatabaseHas(InstallerApplication::class, [
            'contact_name' => 'Тестовый монтажник',
            'city' => 'Гродно',
            'source' => 'installers-catalog',
            'status' => 'new',
        ]);
    }

    public function test_outreach_link_opens_messenger_campaign_landing(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\HandleRedirects::class);

        $this->get(route('installers.outreach'))
            ->assertRedirect('/become-installer?ref=messenger#apply');
    }

    public function test_messenger_application_is_attributed_separately(): void
    {
        $this->withoutMiddleware([
            ProtectPublicForm::class,
            \App\Http\Middleware\HandleRedirects::class,
        ]);

        $response = $this->post(route('partners.apply-installer'), [
            '_source' => 'outreach-messenger',
            'contact_name' => 'Монтажник из Telegram',
            'phone' => '+375 29 765-43-21',
            'city' => 'Брест',
        ]);

        $response->assertRedirect(route('become-installer', ['ref' => 'messenger']) . '#apply');
        $this->assertDatabaseHas(InstallerApplication::class, [
            'contact_name' => 'Монтажник из Telegram',
            'source' => 'outreach-messenger',
        ]);
    }

    public function test_category_application_is_attributed_separately(): void
    {
        $this->withoutMiddleware([
            ProtectPublicForm::class,
            \App\Http\Middleware\HandleRedirects::class,
        ]);

        $this->post(route('partners.apply-installer'), [
            '_source' => 'category-cta',
            'contact_name' => 'Монтажник из каталога',
            'phone' => '+375 44 123-45-67',
        ])->assertRedirect(route('become-installer', ['ref' => 'category']) . '#apply');

        $this->assertDatabaseHas(InstallerApplication::class, [
            'contact_name' => 'Монтажник из каталога',
            'source' => 'category-cta',
        ]);
    }

    public function test_real_protected_form_saves_every_application_field(): void
    {
        config(['services.turnstile.enabled' => false]);
        $this->withoutMiddleware(\App\Http\Middleware\HandleRedirects::class);

        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)')
            ->post(route('partners.apply-installer'), [
                '_source' => 'outreach-messenger',
                '_hpf' => '',
                'form_started_at' => time() - 10,
                'contact_name' => 'Иван Тестовый',
                'phone' => '+375 29 111-22-33',
                'email' => 'installer-test@example.com',
                'city' => 'Могилёв',
                'company_name' => 'Тест Монтаж',
                'experience_years' => 12,
                'specializations' => ['kotly', 'dymohody', 'teplye_poly'],
                'message' => 'Монтаж котельных и систем отопления под ключ.',
            ]);

        $response->assertRedirect(route('become-installer', ['ref' => 'messenger']) . '#apply');
        $response->assertSessionHas('installer_success');

        $application = InstallerApplication::query()
            ->where('email', 'installer-test@example.com')
            ->firstOrFail();

        $this->assertSame('Иван Тестовый', $application->contact_name);
        $this->assertSame('+375 29 111-22-33', $application->phone);
        $this->assertSame('Могилёв', $application->city);
        $this->assertSame('Тест Монтаж', $application->company_name);
        $this->assertSame(12, $application->experience_years);
        $this->assertSame(['kotly', 'dymohody', 'teplye_poly'], $application->specializations);
        $this->assertSame('Монтаж котельных и систем отопления под ключ.', $application->message);
        $this->assertSame('outreach-messenger', $application->source);
        $this->assertSame('new', $application->status);
    }

    public function test_new_application_triggers_telegram_notification(): void
    {
        $this->withoutMiddleware([
            ProtectPublicForm::class,
            \App\Http\Middleware\HandleRedirects::class,
        ]);

        $notifier = Mockery::mock(InstallerApplicationTelegramNotifier::class);
        $notifier->shouldReceive('send')
            ->once()
            ->withArgs(fn (InstallerApplication $application) =>
                $application->exists
                && $application->contact_name === 'Монтажник с уведомлением'
                && $application->source === 'category-cta'
            )
            ->andReturn(true);
        $this->app->instance(InstallerApplicationTelegramNotifier::class, $notifier);

        $this->post(route('partners.apply-installer'), [
            '_source' => 'category-cta',
            'contact_name' => 'Монтажник с уведомлением',
            'phone' => '+375 33 123-45-67',
        ])->assertRedirect(route('become-installer', ['ref' => 'category']) . '#apply');
    }
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\ProtectPublicForm;
use App\Models\InstallerApplication;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
}

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
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\ProtectPublicForm;
use App\Models\InstallRequest;
use App\Services\InstallRequestTelegramNotifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class HeatPumpLeadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('install_requests');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('install_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('installer_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('installer_profile_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->text('description')->nullable();
            $table->json('project_details')->nullable();
            $table->string('specialization')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->date('preferred_date')->nullable();
            $table->string('status')->default('new');
            $table->decimal('price_agreed', 10, 2)->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamp('telegram_notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_heat_pump_form_saves_structured_project_details(): void
    {
        $this->withoutMiddleware([
            ProtectPublicForm::class,
            HandleRedirects::class,
        ]);

        $notifier = Mockery::mock(InstallRequestTelegramNotifier::class);
        $notifier->shouldReceive('send')
            ->once()
            ->withArgs(fn (InstallRequest $request) => $request->exists
                && $request->source === 'heat_pump_installation'
                && $request->project_details['property_area'] === 180
                && $request->project_details['heating_system'] === 'mixed'
                && $request->project_details['needs_hot_water'] === true
            )
            ->andReturn(true);
        $this->app->instance(InstallRequestTelegramNotifier::class, $notifier);

        $response = $this->post(route('install-requests.store'), [
            'customer_name' => 'Иван Тестовый',
            'customer_phone' => '+375 29 111-22-33',
            'customer_email' => 'heatpump@example.com',
            'city' => 'Борисов',
            'specialization' => 'heatpump',
            'source' => 'heat_pump_installation',
            'property_area' => 180,
            'heating_system' => 'mixed',
            'flow_temperature' => '46_to_60',
            'power_supply' => '380',
            'needs_hot_water' => '1',
            'description' => 'Новый утеплённый дом.',
        ]);

        $response->assertRedirect(route('heat-pumps.installation').'#heat-pump-request');
        $response->assertSessionHas('success');

        $request = InstallRequest::query()->firstOrFail();

        $this->assertSame('heat_pump_installation', $request->source);
        $this->assertSame('heatpump', $request->specialization);
        $this->assertSame(180, $request->project_details['property_area']);
        $this->assertSame('mixed', $request->project_details['heating_system']);
        $this->assertSame('46_to_60', $request->project_details['flow_temperature']);
        $this->assertSame('380', $request->project_details['power_supply']);
        $this->assertTrue($request->project_details['needs_hot_water']);
    }
}

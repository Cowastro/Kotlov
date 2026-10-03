<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\ProtectPublicForm;
use App\Models\InstallRequest;
use App\Services\InstallRequestTelegramNotifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class HeatPumpLeadTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->createSqliteTestTables();
        }
    }

    private function createSqliteTestTables(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('role')->default('customer');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->default(0);
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('brand_id')->nullable();
                $table->string('name');
                $table->string('slug')->unique();
                $table->decimal('price', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_archived')->default(false);
                $table->string('availability_status')->default('check');
                $table->boolean('in_stock')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->decimal('rating', 3, 2)->default(0);
                $table->json('specs')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('install_requests')) {
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

    public function test_catalog_heat_pump_form_preserves_selected_product_and_source(): void
    {
        $this->withoutMiddleware([
            ProtectPublicForm::class,
            HandleRedirects::class,
        ]);

        DB::table('categories')->insert([
            'id' => 77,
            'name' => 'Тепловые насосы',
            'slug' => 'test-heat-pumps',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('products')->insert([
            'id' => 77,
            'category_id' => 77,
            'name' => 'Тепловой насос KOTLOV GE 12 кВт R290',
            'slug' => 'test-kotlov-ge-12-r290',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notifier = Mockery::mock(InstallRequestTelegramNotifier::class);
        $notifier->shouldReceive('send')
            ->once()
            ->withArgs(fn (InstallRequest $request) => $request->exists
                && $request->source === 'heat_pump_catalog'
                && $request->product_id === 77
                && $request->project_details['property_area'] === 140
            )
            ->andReturn(true);
        $this->app->instance(InstallRequestTelegramNotifier::class, $notifier);

        $response = $this->post(route('install-requests.store'), [
            'customer_name' => 'Анна Тестовая',
            'customer_phone' => '+375 29 222-33-44',
            'city' => 'Минск',
            'specialization' => 'heatpump',
            'source' => 'heat_pump_catalog',
            'product_id' => 77,
            'property_area' => 140,
            'heating_system' => 'underfloor',
        ]);

        $response->assertRedirect(route('heat-pumps.installation').'#heat-pump-request');
        $response->assertSessionHas('success');

        $request = InstallRequest::query()->firstOrFail();

        $this->assertSame('heat_pump_catalog', $request->source);
        $this->assertSame(77, $request->product_id);
        $this->assertSame(140, $request->project_details['property_area']);
    }
}

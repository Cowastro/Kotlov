<?php

namespace Tests\Feature;

use App\Models\InstallerProfile;
use App\Models\InstallerWork;
use App\Models\User;
use App\Http\Controllers\InstallerAccountController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstallerAccountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\HandleRedirects::class);

        Schema::disableForeignKeyConstraints();

        try {
            Schema::dropIfExists('installer_works');
            Schema::dropIfExists('installer_profiles');
            Schema::dropIfExists('users');

            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role')->default('client');
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });

            Schema::create('installer_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique();
                $table->string('contact_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('additional_phone')->nullable();
                $table->string('email')->nullable();
                $table->string('website')->nullable();
                $table->string('telegram')->nullable();
                $table->string('viber')->nullable();
                $table->string('whatsapp')->nullable();
                $table->string('company_name')->nullable();
                $table->string('photo')->nullable();
                $table->text('bio')->nullable();
                $table->unsignedInteger('experience_years')->default(0);
                $table->decimal('price_from', 10, 2)->nullable();
                $table->string('city')->nullable();
                $table->string('region')->nullable();
                $table->json('work_regions')->nullable();
                $table->json('work_cities')->nullable();
                $table->unsignedInteger('work_radius_km')->nullable();
                $table->boolean('nationwide')->default(false);
                $table->string('slug')->nullable();
                $table->string('short_description')->nullable();
                $table->string('logo')->nullable();
                $table->json('gallery')->nullable();
                $table->json('certificate_files')->nullable();
                $table->string('certificate_photo')->nullable();
                $table->boolean('is_published')->default(false);
                $table->string('status')->default('pending');
                $table->boolean('is_verified')->default(false);
                $table->decimal('rating', 4, 2)->default(0);
                $table->unsignedInteger('reviews_count')->default(0);
                $table->unsignedInteger('orders_count')->default(0);
                $table->json('specializations')->nullable();
                $table->timestamps();
            });

            Schema::create('installer_works', function (Blueprint $table) {
                $table->id();
                $table->foreignId('installer_profile_id');
                $table->unsignedBigInteger('blog_post_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('work_type')->nullable();
                $table->string('city')->nullable();
                $table->string('region')->nullable();
                $table->string('equipment_type')->nullable();
                $table->string('brand')->nullable();
                $table->json('photos')->nullable();
                $table->date('completed_at')->nullable();
                $table->boolean('is_published')->default(true);
                $table->timestamps();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function test_guest_is_redirected_from_installer_cabinet(): void
    {
        $this->get(route('account.installer-profile'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_preview_an_installer_cabinet_without_impersonation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $installer = User::factory()->create(['role' => 'installer']);
        $profile = $this->profile($installer);
        $request = Request::create(route('admin.installer-cabinet-preview', $profile));
        $request->setUserResolver(fn () => $admin);

        $view = app(InstallerAccountController::class)->preview($request, $profile);

        $this->assertSame('pages.account-installer-profile', $view->name());
        $this->assertTrue($view->getData()['previewMode']);
        $this->assertTrue($view->getData()['profile']->is($profile));
        $this->assertFalse(auth()->check());
    }

    public function test_user_without_installer_profile_cannot_open_installer_cabinet(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(ModelNotFoundException::class);

        $this->actingAs(User::factory()->create())
            ->get(route('account.installer-profile'));
    }

    public function test_installer_can_update_only_their_public_profile_fields(): void
    {
        $user = User::factory()->create();
        $profile = $this->profile($user, ['is_verified' => true, 'status' => 'active']);

        $this->actingAs($user)
            ->put(route('account.installer-profile.update'), [
                'contact_name' => 'Алексей Максимов',
                'phone' => '+375 29 000-00-00',
                'company_name' => 'ООО «Отопление плюс»',
                'experience_years' => 25,
                'region' => 'Минск',
                'work_regions_text' => 'Минск, Минская область',
                'work_cities_text' => 'Минск; Смолевичи',
                'specializations' => ['heating', 'heatpump'],
                'is_verified' => false,
                'status' => 'blocked',
            ])
            ->assertRedirect();

        $profile->refresh();

        $this->assertSame('Алексей Максимов', $profile->contact_name);
        $this->assertSame(['Минск', 'Минская область'], $profile->work_regions);
        $this->assertSame(['Минск', 'Смолевичи'], $profile->work_cities);
        $this->assertTrue($profile->is_verified);
        $this->assertSame('active', $profile->status);
    }

    public function test_installer_can_add_work_with_photos(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $profile = $this->profile($user);

        $this->actingAs($user)
            ->post(route('account.installer-works.store'), [
                'title' => 'Монтаж теплового насоса 16 кВт',
                'description' => 'Обвязка, тёплый пол и запуск автоматики.',
                'work_type' => 'heatpump',
                'city' => 'Смолевичи',
                'region' => 'Минская область',
                'completed_at' => now()->toDateString(),
                'is_published' => 1,
                'photos' => [UploadedFile::fake()->image('object.webp', 1200, 800)],
            ])
            ->assertRedirect();

        $work = InstallerWork::query()->firstOrFail();
        $this->assertSame($profile->id, $work->installer_profile_id);
        $this->assertTrue($work->is_published);
        $this->assertCount(1, $work->photos);
        Storage::disk('public')->assertExists($work->photos[0]);
    }

    public function test_installer_cannot_update_another_installers_work(): void
    {
        $owner = User::factory()->create();
        $ownerProfile = $this->profile($owner);
        $other = User::factory()->create();
        $this->profile($other);

        $work = InstallerWork::create([
            'installer_profile_id' => $ownerProfile->id,
            'title' => 'Чужая работа',
            'is_published' => true,
        ]);

        $this->withoutExceptionHandling();
        $this->expectException(ModelNotFoundException::class);

        try {
            $this->actingAs($other)->put(route('account.installer-works.update', $work), [
                'title' => 'Попытка изменить',
                'is_published' => 1,
            ]);
        } finally {
            $this->assertSame('Чужая работа', $work->fresh()->title);
        }
    }

    private function profile(User $user, array $attributes = []): InstallerProfile
    {
        return InstallerProfile::create(array_merge([
            'user_id' => $user->id,
            'contact_name' => $user->name,
            'phone' => '+375 29 111-11-11',
            'slug' => 'installer-' . $user->id,
            'status' => 'active',
            'is_published' => true,
            'is_verified' => false,
            'specializations' => ['heating'],
        ], $attributes));
    }
}

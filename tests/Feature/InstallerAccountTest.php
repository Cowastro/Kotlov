<?php

namespace Tests\Feature;

use App\Models\InstallerProfile;
use App\Models\InstallerWork;
use App\Models\User;
use App\Http\Controllers\InstallerAccountController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstallerAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\HandleRedirects::class);
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
        $this->expectException(NotFoundHttpException::class);

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

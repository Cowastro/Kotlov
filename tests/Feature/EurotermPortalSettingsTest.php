<?php

namespace Tests\Feature;

use App\Filament\Resources\SupplierSyncRuns\Pages\ListSupplierSyncRuns;
use App\Models\User;
use App\Services\Suppliers\EurotermPortalCredentials;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EurotermPortalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_save_encrypted_credentials_without_exposing_existing_password(): void
    {
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Livewire::test(ListSupplierSyncRuns::class)
            ->callAction('eurotermPortal', ['username' => 'portal-login', 'password' => 'secret-password'])
            ->assertHasNoActionErrors();
        $credentials = app(EurotermPortalCredentials::class);
        $this->assertSame('secret-password', $credentials->get()['password']);
        $this->assertTrue($credentials->due());
        $stored = Storage::disk('local')->get('suppliers/euroterm/credentials.enc');
        $this->assertStringNotContainsString('secret-password', $stored);
        $this->assertStringNotContainsString('portal-login', $stored);
        Livewire::test(ListSupplierSyncRuns::class)->mountAction('eurotermPortal')
            ->assertSchemaStateSet(['username' => 'portal-login', 'password' => '']);
        $credentials->save('portal-login', '');
        $this->assertSame('secret-password', $credentials->get()['password']);
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));
        Livewire::test(ListSupplierSyncRuns::class)->assertActionHidden('eurotermPortal');
    }
}

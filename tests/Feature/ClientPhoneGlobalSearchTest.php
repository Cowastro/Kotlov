<?php

namespace Tests\Feature;

use App\Filament\GlobalSearch\ClientPhoneGlobalSearchProvider;
use App\Models\ContactRequest;
use App\Models\CrmContact;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPhoneGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_is_found_by_phone_in_any_spelling(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Filament::setCurrentPanel('admin');

        $counterparty = CrmCounterparty::query()->create(['name' => 'ООО Тестовая Баня', 'type' => 'legal', 'status' => 'active']);
        $contact = CrmContact::query()->create(['counterparty_id' => $counterparty->id, 'full_name' => 'Иван Петров']);
        CrmContactPoint::query()->create(['contact_id' => $contact->id, 'type' => 'phone', 'value' => '8 (029) 123-45-67']);
        ContactRequest::query()->create(['name' => 'Пётр', 'phone' => '+375 (29) 123-45-67', 'message' => 'Перезвоните']);

        $provider = new ClientPhoneGlobalSearchProvider;
        foreach (['+375 29 123-45-67', '80291234567', '291234567', '123-45-67'] as $query) {
            $categories = $provider->getResults($query)->getCategories();
            $this->assertSame('ООО Тестовая Баня', $categories['Контрагенты'][0]->title, $query);
            $this->assertSame('Иван Петров', $categories['Контактные лица'][0]->title, $query);
            $this->assertSame('Пётр', $categories['Обращения'][0]->title, $query);
        }

        $this->assertNull(ClientPhoneGlobalSearchProvider::phoneDigits('Теплов'));
        $this->assertNull(ClientPhoneGlobalSearchProvider::phoneDigits('120'));
        $this->assertNotNull($provider->getResults('Тестовая'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\B2bAccessRequestNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_entity_registration_creates_pending_b2b_application(): void
    {
        $notifier = $this->mock(B2bAccessRequestNotifier::class);
        $notifier->shouldReceive('notify')->once()->withArgs(
            fn (User $user): bool => $user->email === 'office@teplo.by',
        );

        $response = $this->post(route('register'), [
            '_form' => 'register',
            'account_type' => 'legal',
            'company_name' => 'ООО «Теплосервис»',
            'company_inn' => '190123456',
            'name' => 'Иван Петров',
            'phone' => '+375291112233',
            'email' => 'office@teplo.by',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'privacy_accepted' => '1',
        ]);

        $response->assertRedirect('/account');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'office@teplo.by',
            'phone' => '+375291112233',
            'client_type' => 'wholesale',
            'b2b_approved' => false,
            'company_name' => 'ООО «Теплосервис»',
        ]);
        $this->assertDatabaseHas('b2b_clients', [
            'status' => 'pending',
            'company_name' => 'ООО «Теплосервис»',
            'unp' => '190123456',
            'contact_email' => 'office@teplo.by',
        ]);
    }

    public function test_individual_registration_creates_retail_account_without_b2b_application(): void
    {
        $notifier = $this->mock(B2bAccessRequestNotifier::class);
        $notifier->shouldNotReceive('notify');

        $this->post(route('register'), [
            '_form' => 'register',
            'account_type' => 'retail',
            'name' => 'Анна Иванова',
            'phone' => '+375292223344',
            'email' => 'anna@example.by',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'privacy_accepted' => '1',
        ])->assertRedirect('/account');

        $user = User::query()->where('email', 'anna@example.by')->firstOrFail();
        $this->assertSame('retail', $user->client_type->value);
        $this->assertFalse($user->b2b_approved);
        $this->assertDatabaseMissing('b2b_clients', ['user_id' => $user->id]);
    }

    public function test_registration_requires_explicit_privacy_consent(): void
    {
        $this->post(route('register'), [
            '_form' => 'register',
            'account_type' => 'retail',
            'name' => 'Анна Иванова',
            'phone' => '+375292223344',
            'email' => 'privacy@example.by',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertSessionHasErrors('privacy_accepted');

        $this->assertDatabaseMissing('users', ['email' => 'privacy@example.by']);
    }

    public function test_registration_forms_show_legal_and_individual_account_types(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeText('Юридическое лицо')
            ->assertSeeText('Физическое лицо')
            ->assertSeeText('Компания / ИП')
            ->assertSeeText('обработки персональных данных');
    }

    public function test_sms_code_verifies_phone_during_registration(): void
    {
        config()->set('services.sms_by', [
            'enabled' => true,
            'base_url' => 'https://app.sms.by',
            'token' => 'test-token',
            'password_object_id' => '123',
            'alphaname_id' => '456',
            'message' => 'Код подтверждения на Kotlov.by: %CODE%',
            'timeout' => 10,
            'verification_ttl' => 300,
        ]);

        Http::fake([
            'app.sms.by/*' => Http::response([
                'status' => 'ok',
                'parts' => 1,
                'len' => 39,
                'sms_id' => 2208471,
                'code' => '123456',
            ]),
        ]);

        $send = $this->postJson(route('registration.phone.send-code'), [
            'phone' => '+375 (29) 222-33-44',
        ]);

        $send->assertOk()
            ->assertJsonPath('message', 'Код отправлен на указанный номер.')
            ->assertJsonStructure(['verification_token', 'expires_in']);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://app.sms.by/api/v1/sendSmsMessageWithCode')
                && ($query['token'] ?? null) === 'test-token'
                && ($query['phone'] ?? null) === '375292223344'
                && ($query['password_object_id'] ?? null) === '123';
        });

        $notifier = $this->mock(B2bAccessRequestNotifier::class);
        $notifier->shouldNotReceive('notify');

        $this->post(route('register'), [
            '_form' => 'register',
            'account_type' => 'retail',
            'name' => 'Анна Иванова',
            'phone' => '+375 (29) 222-33-44',
            'email' => 'verified@example.by',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'privacy_accepted' => '1',
            'phone_verification_token' => $send->json('verification_token'),
            'phone_code' => '123456',
        ])->assertRedirect('/account');

        $this->assertNotNull(User::query()->where('email', 'verified@example.by')->firstOrFail()->phone_verified_at);
    }

    public function test_registration_rejects_an_incorrect_sms_code(): void
    {
        config()->set('services.sms_by', [
            'enabled' => true,
            'base_url' => 'https://app.sms.by',
            'token' => 'test-token',
            'password_object_id' => '123',
            'alphaname_id' => '456',
            'message' => 'Код: %CODE%',
            'timeout' => 10,
            'verification_ttl' => 300,
        ]);

        Http::fake([
            'app.sms.by/*' => Http::response([
                'status' => 'ok',
                'sms_id' => 2208472,
                'code' => '654321',
            ]),
        ]);

        $send = $this->postJson(route('registration.phone.send-code'), [
            'phone' => '+375291112244',
        ])->assertOk();

        $this->post(route('register'), [
            '_form' => 'register',
            'account_type' => 'retail',
            'name' => 'Тестовый клиент',
            'phone' => '+375291112244',
            'email' => 'wrong-code@example.by',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'privacy_accepted' => '1',
            'phone_verification_token' => $send->json('verification_token'),
            'phone_code' => '111111',
        ])->assertSessionHasErrors('phone_code');

        $this->assertDatabaseMissing('users', ['email' => 'wrong-code@example.by']);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Mail\NewB2bAccessRequestMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class B2bAccessRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2b_request_notifies_the_office_and_service_mailbox(): void
    {
        Mail::fake();
        config()->set('services.b2b.notification_email', 'order@kotlov.by');

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $applicant = User::factory()->create([
            'role' => 'client',
            'is_active' => true,
            'client_type' => ClientType::Retail,
            'b2b_approved' => false,
            'phone' => '+375291112233',
        ]);

        $this->actingAs($applicant)
            ->post(route('account.b2b-request'), [
                'client_type' => ClientType::Wholesale->value,
                'company_name' => 'ООО Тестовый партнёр',
                'company_inn' => '123456789',
                'b2b_comment' => 'Проверка B2B-каталога.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(ClientType::Wholesale, $applicant->fresh()->client_type);
        $this->assertFalse($applicant->fresh()->b2b_approved);
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertStringContainsString(
            'ООО Тестовый партнёр',
            $manager->notifications()->firstOrFail()->data['body'],
        );

        Mail::assertSent(
            NewB2bAccessRequestMail::class,
            fn (NewB2bAccessRequestMail $mail): bool => $mail->hasTo('order@kotlov.by')
                && $mail->applicant->is($applicant),
        );
    }

    public function test_mail_failure_does_not_lose_the_b2b_request(): void
    {
        config()->set('services.b2b.notification_email', 'order@kotlov.by');
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));

        $applicant = User::factory()->create([
            'role' => 'client',
            'client_type' => ClientType::Retail,
            'b2b_approved' => false,
        ]);

        $this->actingAs($applicant)
            ->post(route('account.b2b-request'), [
                'client_type' => ClientType::Wholesale->value,
                'company_name' => 'ООО Заявка сохранена',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('ООО Заявка сохранена', $applicant->fresh()->company_name);
        $this->assertSame(ClientType::Wholesale, $applicant->fresh()->client_type);
    }
}

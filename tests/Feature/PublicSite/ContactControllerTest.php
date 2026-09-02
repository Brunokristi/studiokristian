<?php

namespace Tests\Feature\PublicSite;

use App\Notifications\ContactRequestReceivedNotification;
use App\Notifications\NewContactRequestNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    public function test_it_sends_admin_notification_for_a_valid_request(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/contact', [
            'name' => 'Jane Doe',
            'service' => '1',
            'contactMethod' => 'call',
            'phone' => '+421900000000',
            'message' => 'Hello there.',
            'locale' => 'en',
            'website' => '',
        ]);

        $response->assertOk();

        Notification::assertSentOnDemand(
            NewContactRequestNotification::class,
            fn ($notification, $channels, $notifiable) =>
                $notifiable->routes['mail'] === config('app.contact_email')
        );
    }

    public function test_it_also_emails_the_customer_when_contact_method_is_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/contact', [
            'name' => 'Jane Doe',
            'service' => 'other',
            'contactMethod' => 'email',
            'email' => 'jane@example.com',
            'locale' => 'sk',
            'website' => '',
        ]);

        $response->assertOk();

        Notification::assertSentOnDemand(NewContactRequestNotification::class);
        Notification::assertSentOnDemand(
            ContactRequestReceivedNotification::class,
            fn ($notification, $channels, $notifiable) =>
                $notifiable->routes['mail'] === 'jane@example.com'
        );
    }

    public function test_it_requires_email_when_contact_method_is_email(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Jane Doe',
            'contactMethod' => 'email',
            'locale' => 'en',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_honeypot_field_silently_blocks_submission(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/contact', [
            'name' => 'Bot',
            'contactMethod' => 'email',
            'email' => 'bot@example.com',
            'website' => 'https://spam.example.com',
        ]);

        $response->assertUnprocessable();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GoogleAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.analytics'))->assertRedirect(route('login'));
    }

    #[TestWith(['student'])]
    #[TestWith(['teacher'])]
    public function test_non_admin_cannot_view_analytics(string $role): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->get(route('admin.analytics'))->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_admin_sees_menu_and_setup_instructions_without_credentials(): void
    {
        Http::preventStrayRequests();
        config(['services.google_analytics.credentials_path' => 'missing-analytics-credentials.json']);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.analytics'))->assertOk()
            ->assertSee('Google Analytics')->assertSee('Connect your Analytics reports')
            ->assertDontSee('Engagement rate');

        Http::assertNothingSent();
    }

    public function test_disabled_admin_cannot_view_analytics(): void
    {
        $this->actingAs(User::factory()->admin()->disabled()->create());

        $this->get(route('admin.analytics'))->assertRedirect(route('login'))->assertSessionHasErrors('identity');
    }

    public function test_invalid_date_range_is_rejected_before_requesting_google(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.analytics', ['days' => 365]))->assertSessionHasErrors('days');

        Http::assertNothingSent();
    }

    public function test_reports_are_displayed_escaped_and_cached_for_the_selected_period(): void
    {
        $this->freezeTime();
        $this->credentials();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
            'https://analyticsdata.googleapis.com/v1beta/properties/563226858:batchRunReports' => Http::response(['reports' => [
                ['rows' => [['metricValues' => [['value' => '120'], ['value' => '180'], ['value' => '450'], ['value' => '0.625']]]]],
                $this->reportRow('20261109', '450'),
                $this->reportRow('<script>alert(1)</script>', '80'),
                $this->reportRow('Organic Search', '60'),
                $this->reportRow('Bangladesh', '100'),
            ]]),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.analytics', ['days' => 7]))->assertOk()
            ->assertSee('62.5%')->assertSee('450')->assertSee('Bangladesh')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('admin.analytics', ['days' => 7]))->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), ':batchRunReports')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['requests'][0]['dateRanges'][0] === ['startDate' => '6daysAgo', 'endDate' => 'today']);
        $this->travel(6)->minutes();
        $this->get(route('admin.analytics', ['days' => 7]))->assertOk();
        Http::assertSentCount(3);
    }

    #[TestWith([403])]
    #[TestWith([500])]
    public function test_google_failure_displays_a_safe_error_instead_of_zero_statistics(int $status): void
    {
        $this->credentials();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'secret-test-token']),
            'https://analyticsdata.googleapis.com/*' => Http::response(['error' => ['message' => 'sensitive upstream detail']], $status),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.analytics'))->assertOk()->assertSee('Reports temporarily unavailable')
            ->assertDontSee('secret-test-token')->assertDontSee('sensitive upstream detail')->assertDontSee('Engagement rate');

        Http::assertSentCount(2);
    }

    public function test_connection_failure_displays_a_retry_message(): void
    {
        $this->credentials();
        Http::preventStrayRequests();
        Http::fake(['https://oauth2.googleapis.com/token' => Http::failedConnection()]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.analytics'))->assertOk()->assertSee('Reports temporarily unavailable')->assertSee('Try again');

        Http::assertSentCount(1);
    }

    public function test_empty_reports_are_shown_as_connected_with_no_data(): void
    {
        $this->credentials();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'https://analyticsdata.googleapis.com/*' => Http::response(['reports' => [[], [], [], [], []]]),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.analytics'))->assertOk()->assertSee('No page views reported yet')->assertSee('0.0%');

        Http::assertSentCount(2);
    }

    public function test_invalid_credentials_display_a_safe_error_without_calling_google(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('analytics.json', '{invalid json');
        config(['services.google_analytics.credentials_path' => Storage::disk('local')->path('analytics.json')]);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.analytics'))->assertOk()->assertSee('Reports temporarily unavailable');

        Http::assertNothingSent();
    }

    private function credentials(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('openssl.cnf', "[req]\ndistinguished_name = req_distinguished_name\n[req_distinguished_name]\n");
        $options = ['config' => Storage::disk('local')->path('openssl.cnf'), 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $privateKey, null, $options);
        Storage::disk('local')->put('analytics.json', json_encode([
            'client_email' => 'analytics@example.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ], JSON_THROW_ON_ERROR));
        config([
            'services.google_analytics.property_id' => '563226858',
            'services.google_analytics.credentials_path' => Storage::disk('local')->path('analytics.json'),
        ]);
    }

    /** @return array{rows: list<array{dimensionValues: list<array{value: string}>, metricValues: list<array{value: string}>}>} */
    private function reportRow(string $label, string $value): array
    {
        return ['rows' => [['dimensionValues' => [['value' => $label]], 'metricValues' => [['value' => $value]]]]];
    }
}

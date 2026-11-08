<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAnalytics
{
    public function configured(): bool
    {
        $path = config('services.google_analytics.credentials_path');

        return preg_match('/^[0-9]+$/', (string) config('services.google_analytics.property_id')) === 1
            && is_string($path) && is_file($path) && is_readable($path);
    }

    /** @return array{summary: array<string, float>, daily: list<array<string, string>>, pages: list<array<string, string>>, sources: list<array<string, string>>, countries: list<array<string, string>>, updated_at: string} */
    public function report(int $days): array
    {
        if (! $this->configured() || ! in_array($days, [7, 30, 90], true)) {
            throw new RuntimeException('Google Analytics configuration or date range is invalid.');
        }

        $credentials = json_decode(file_get_contents(config('services.google_analytics.credentials_path')), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($credentials) || ! is_string($credentials['client_email'] ?? null) || ! is_string($credentials['private_key'] ?? null)) {
            throw new RuntimeException('Google Analytics credentials are invalid.');
        }

        $identity = hash('sha256', $credentials['client_email'].$credentials['private_key']);
        $property = (string) config('services.google_analytics.property_id');

        return Cache::remember('google-analytics:report:'.$property.':'.$identity.':'.$days.':'.now()->toDateString(), 300, function () use ($credentials, $identity, $property, $days): array {
            $token = $this->accessToken($credentials, $identity);
            $dates = [['startDate' => ($days - 1).'daysAgo', 'endDate' => 'today']];
            $summaryMetrics = ['activeUsers', 'sessions', 'screenPageViews', 'engagementRate'];
            $requests = [[
                'dateRanges' => $dates,
                'metrics' => array_map(fn (string $name): array => ['name' => $name], $summaryMetrics),
            ]];
            foreach (['date' => 'screenPageViews', 'pagePath' => 'screenPageViews', 'sessionDefaultChannelGroup' => 'sessions', 'country' => 'activeUsers'] as $dimension => $metric) {
                $requests[] = [
                    'dateRanges' => $dates,
                    'dimensions' => [['name' => $dimension]],
                    'metrics' => [['name' => $metric]],
                    'limit' => $dimension === 'date' ? $days : 10,
                    'orderBys' => [$dimension === 'date'
                        ? ['dimension' => ['dimensionName' => 'date']]
                        : ['metric' => ['metricName' => $metric], 'desc' => true]],
                ];
            }

            $response = Http::withToken($token)->acceptJson()->connectTimeout(3)->timeout(15)
                ->post('https://analyticsdata.googleapis.com/v1beta/properties/'.$property.':batchRunReports', ['requests' => $requests]);
            if ($response->status() === 401) {
                Cache::forget('google-analytics:token:'.$identity);
            }
            $reports = $response->throw()->json('reports');
            if (! is_array($reports) || count($reports) !== 5) {
                throw new RuntimeException('Google Analytics returned an invalid report.');
            }

            $summary = [];
            foreach ($summaryMetrics as $index => $metric) {
                $summary[$metric] = (float) ($reports[0]['rows'][0]['metricValues'][$index]['value'] ?? 0);
            }

            return [
                'summary' => $summary,
                'daily' => $this->rows($reports[1]),
                'pages' => $this->rows($reports[2]),
                'sources' => $this->rows($reports[3]),
                'countries' => $this->rows($reports[4]),
                'updated_at' => now()->format('M j, Y H:i T'),
            ];
        });
    }

    /** @param array{client_email: string, private_key: string} $credentials */
    private function accessToken(array $credentials, string $identity): string
    {
        return Cache::remember('google-analytics:token:'.$identity, 3000, function () use ($credentials): string {
            $issuedAt = now()->timestamp;
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ], JSON_THROW_ON_ERROR));
            $unsigned = $header.'.'.$claims;
            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Google Analytics credentials could not be signed.');
            }

            $token = Http::asForm()->connectTimeout(3)->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $unsigned.'.'.$this->base64Url($signature),
                ])->throw()->json('access_token');
            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Google Analytics did not return an access token.');
            }

            return $token;
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @param  array{rows?: list<array{dimensionValues: list<array{value: string}>, metricValues: list<array{value: string}>}>}  $report
     * @return list<array<string, string>>
     */
    private function rows(array $report): array
    {
        return array_map(fn (array $row): array => [
            'label' => (string) ($row['dimensionValues'][0]['value'] ?? '(not set)'),
            'value' => (string) ($row['metricValues'][0]['value'] ?? '0'),
        ], $report['rows'] ?? []);
    }
}

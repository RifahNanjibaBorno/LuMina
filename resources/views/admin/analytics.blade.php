@extends('layouts.workspace')
@section('title', 'Google Analytics')
@section('subtitle', 'Website traffic and engagement from Google Analytics.')
@section('content')
<section class="card mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h2>Website overview</h2><p class="muted">Property {{ $propertyId }} · G-8587LF3RJ0 · GTM-NSRWMGWC</p></div>
        <form action="{{ route('admin.analytics') }}" method="get" class="flex flex-wrap items-center gap-3">
            <label for="analytics-days">Date range</label>
            <select id="analytics-days" name="days" class="w-auto max-w-full">
                @foreach([7, 30, 90] as $period)<option value="{{ $period }}" @selected($days === $period)>Last {{ $period }} days</option>@endforeach
            </select>
            <button class="button button-primary" type="submit">Apply</button>
        </form>
    </div>
</section>
@if(! $configured)
<section class="card">
    <h2>Connect your Analytics reports</h2>
    <p class="muted">Your website tag collects visits. To display private reports here, complete this one-time server setup.</p>
    <ol class="list-decimal space-y-3 pl-6 mt-4">
        <li>Enable the Google Analytics Data API in your Google Cloud project and create a service account with a JSON key.</li>
        <li>In Google Analytics → Admin → Property access management, add the service account email as a Viewer for property {{ $propertyId }}.</li>
        <li>Save the JSON key on the server at <code>storage/app/private/google-analytics.json</code>. Keep this file private and out of Git.</li>
        <li>Set <code>GOOGLE_ANALYTICS_PROPERTY_ID={{ $propertyId }}</code> in the server environment. For a different key location, set <code>GOOGLE_ANALYTICS_CREDENTIALS_PATH</code> to its absolute private path.</li>
        <li>Run <code>php artisan config:clear</code>, then reload this page.</li>
    </ol>
    <p class="muted mt-4">Your measurement ID alone cannot grant access to reports. No visitor statistics are displayed until the connection is ready.</p>
    <a class="button mt-4" href="https://developers.google.com/analytics/devguides/reporting/data/v1/quickstart" target="_blank" rel="noopener noreferrer">Google setup instructions</a>
</section>
@elseif($error)
<section class="card" role="alert"><h2>Reports temporarily unavailable</h2><p>{{ $error }}</p><a class="button mt-4" href="{{ route('admin.analytics', ['days' => $days]) }}">Try again</a></section>
@else
<div class="stats-grid">
    <x-stat label="Active users" :value="number_format($report['summary']['activeUsers'])" description="Visitors who engaged with your site" />
    <x-stat label="Sessions" :value="number_format($report['summary']['sessions'])" description="Visits in the selected period" />
    <x-stat label="Page views" :value="number_format($report['summary']['screenPageViews'])" description="Includes repeat page views" />
    <x-stat label="Engagement rate" :value="number_format($report['summary']['engagementRate'] * 100, 1).'%'" description="Percentage of engaged sessions" />
</div>
<p class="muted mb-6">Last {{ $days }} days, including today, in your Analytics property time zone. Retrieved {{ $report['updated_at'] }}. Reports are cached for five minutes; recent visits may take time to appear.</p>
@if($report['summary']['screenPageViews'] == 0)
<section class="card mb-6"><h2>No page views reported yet</h2><p class="muted">The connection is working. Check your live tag installation and allow Google Analytics time to process visits.</p></section>
@endif
<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    @foreach([
        ['Daily traffic', 'Date (YYYYMMDD)', 'Page views', 'daily'],
        ['Top pages', 'Page path', 'Page views', 'pages'],
        ['Traffic sources', 'Channel', 'Sessions', 'sources'],
        ['Countries', 'Country', 'Active users', 'countries'],
    ] as [$title, $dimension, $metric, $key])
    <section class="card min-w-0">
        <h2>{{ $title }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full"><thead><tr><th class="text-left">{{ $dimension }}</th><th class="text-right">{{ $metric }}</th></tr></thead>
                <tbody>@forelse($report[$key] as $row)<tr><td class="break-all">{{ $row['label'] }}</td><td class="text-right">{{ number_format((float) $row['value']) }}</td></tr>@empty<tr><td colspan="2" class="muted">No data for this period.</td></tr>@endforelse</tbody>
            </table>
        </div>
    </section>
    @endforeach
</div>
@endif
@endsection

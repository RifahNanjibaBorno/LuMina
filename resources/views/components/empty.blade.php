@props(['title' => 'Nothing here yet', 'description' => 'Your academic journey starts here.'])
<div class="empty-state"><strong>{{ $title }}</strong><p>{{ $description }}</p>{{ $slot }}</div>

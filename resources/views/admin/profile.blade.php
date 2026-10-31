@extends('layouts.workspace')
@section('title', $user->name)
@section('subtitle', ucfirst($user->role).' profile and uploaded resources')
@section('content')
@php($profile = $user->profile ?? [])
<section class="card course-detail">
    <div class="result-heading"><h2>Profile</h2><a class="button" href="{{ route('users.index') }}">Back to users</a></div>
    <dl class="detail-metadata">
        <div><dt>Name</dt><dd>{{ $user->name }}</dd></div>
        <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
        <div><dt>University ID</dt><dd>{{ $user->university_id ?: 'Not provided' }}</dd></div>
        <div><dt>Role</dt><dd>{{ ucfirst($user->role) }}</dd></div>
        @foreach(['phone' => 'Phone', 'department' => 'Department', 'semester' => 'Semester', 'batch' => 'Batch'] as $key => $label)
            <div><dt>{{ $label }}</dt><dd>{{ $profile[$key] ?? 'Not provided' }}</dd></div>
        @endforeach
    </dl>
    @foreach(['bio' => 'About', 'education' => 'Education', 'skills' => 'Skills', 'projects' => 'Projects', 'certifications' => 'Certifications', 'achievements' => 'Achievements', 'experience' => 'Experience'] as $key => $label)
        @if(!empty($profile[$key]))<h3>{{ $label }}</h3><p class="preserve-lines">{{ $profile[$key] }}</p>@endif
    @endforeach
</section>
<section class="card course-detail">
    <h2>Uploaded resources</h2>
    <div class="table-scroll"><table class="display" data-datatable>
        <thead><tr><th>Title</th><th>Course</th><th>Type</th><th>Uploaded</th><th data-orderable="false" data-searchable="false">Files</th></tr></thead>
        <tbody>@foreach($items as $item)<tr>
            <td><a href="{{ route('items.show', $item) }}">{{ $item->title }}</a></td>
            <td>{{ $item->course->title }}</td><td>{{ ucfirst($item->type) }}</td><td data-order="{{ $item->created_at->timestamp }}">{{ $item->created_at->format('M j, Y') }}</td>
            <td>@foreach($item->uploadedFiles() as $attachment)<a class="button" href="{{ route('items.download', ['item' => $item, 'attachment' => $loop->index]) }}">{{ $attachment['name'] }}</a>@endforeach</td>
        </tr>@endforeach</tbody>
    </table></div>
</section>
@endsection

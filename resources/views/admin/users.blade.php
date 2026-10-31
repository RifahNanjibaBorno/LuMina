@extends('layouts.workspace')
@section('title', auth()->user()->role === 'teacher' ? 'Students' : 'User management')
@section('content')
@can('admin')
<div class="form-actions users-page-actions"><a class="button button-primary" href="{{ route('users.create') }}">Create user</a></div>
@endcan
<section class="card user-list users-data-card">
<div class="table-scroll"><table id="users-table" class="display" data-datatable><thead><tr><th>Name</th><th>University ID / Email</th><th>Role</th><th>Status</th><th data-orderable="false" data-searchable="false">Actions</th></tr></thead><tbody>@foreach($users as $member)<tr><td><a href="{{ route('users.show', $member) }}">{{ $member->name }}</a></td><td>{{ $member->university_id }}<small>{{ $member->email }}</small></td><td>{{ ucfirst($member->role) }}</td><td><span class="badge {{ $member->is_active ? 'badge-green' : 'badge-red' }}">{{ $member->is_active ? 'Active' : 'Disabled' }}</span></td><td><a href="{{ route('users.show', $member) }}">View profile &amp; uploads</a> @can('admin')<a href="{{ route('users.edit', $member) }}">Manage</a>@endcan @if($member->portfolio_public && $member->is_active)<a href="{{ route('portfolio.public', $member->portfolio_slug) }}" target="_blank" rel="noopener">Portfolio</a>@endif</td></tr>@endforeach</tbody></table></div></section>
@endsection

@extends('layouts.workspace')
@section('title', request()->routeIs('projects.index') ? 'Projects' : 'Academic Repository')
@section('content')
<div class="result-heading"><h2>Repository results</h2><div><small>{{ $items->count() }} items found</small>@auth<a class="button" href="{{ route('items.create') }}">Upload new</a>@endauth</div></div>
<section class="card repository-data-card"><div class="table-scroll">
<table id="repository-table" class="display" data-datatable>
    <thead><tr><th>Title</th><th>Course</th><th>Uploaded by</th><th>Type</th><th>Uploaded</th><th data-orderable="false" data-searchable="false">Actions</th></tr></thead>
    <tbody>
    @foreach($items as $item)
        <tr>
            <td><a href="{{ route('items.show', $item) }}">{{ $item->title }}</a></td>
            <td>{{ $item->course->title }}<small>{{ $item->course->semester }}</small></td>
            <td>{{ $item->user->name }}</td>
            <td><span class="badge badge-{{ $item->type }}">{{ ucfirst($item->type) }}</span></td>
            <td data-order="{{ $item->created_at->timestamp }}">{{ $item->created_at->format('M j, Y') }}</td>
            <td><div class="repository-table-actions"><a class="button button-primary" href="{{ route('items.show', $item) }}">View details</a>
                @auth
                @foreach($item->uploadedFiles() as $attachment)
                    <a class="button" href="{{ route('items.download', ['item' => $item, 'attachment' => $loop->index]) }}">Download</a>
                @endforeach
                @endauth
            </div></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></section>
@endsection

@foreach(['status' => 'success', 'success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $type)
    @if(session($key))
        <div class="notice {{ $type === 'error' ? 'notice-error' : 'notice-success' }}" role="{{ $type === 'error' ? 'alert' : 'status' }}" data-toast-type="{{ $type }}">{{ session($key) }}</div>
    @endif
@endforeach
@if($errors->any())
    <div class="notice notice-error" role="alert" data-toast-type="error">
        <strong>Please check the following:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

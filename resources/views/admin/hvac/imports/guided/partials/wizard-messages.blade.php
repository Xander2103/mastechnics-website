@if (! empty($state['profile_warning']))
    <div class="form-error-list">
        <ul><li>{{ $state['profile_warning'] }}</li></ul>
    </div>
@endif

@if ($errors->any())
    <div class="form-error-list">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

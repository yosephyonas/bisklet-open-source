@if (session('success'))
    <div class="admin-alert admin-alert--success" role="alert" data-dismissable>
        <span class="admin-alert__icon" aria-hidden="true">✓</span>
        <p class="admin-alert__text">{{ session('success') }}</p>
        <button type="button" class="admin-alert__close" aria-label="Dismiss" data-alert-close>&times;</button>
    </div>
@endif

@if (session('error'))
    <div class="admin-alert admin-alert--error" role="alert" data-dismissable>
        <span class="admin-alert__icon" aria-hidden="true">!</span>
        <p class="admin-alert__text">{{ session('error') }}</p>
        <button type="button" class="admin-alert__close" aria-label="Dismiss" data-alert-close>&times;</button>
    </div>
@endif

@if ($errors->any())
    <div class="admin-alert admin-alert--info" role="alert" data-dismissable>
        <span class="admin-alert__icon" aria-hidden="true">!</span>
        <div class="admin-alert__text">
            <p class="font-semibold">We found {{ $errors->count() }} issue(s) with your submission:</p>
            <ul class="admin-alert__list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="admin-alert__close" aria-label="Dismiss" data-alert-close>&times;</button>
    </div>
@endif

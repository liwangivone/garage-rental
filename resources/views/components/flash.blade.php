@if (session('status'))
    <div class="mb-5 border-l-4 border-olive bg-olive/10 px-4 py-3 text-sm text-charcoal-800" role="status">{{ session('status') }}</div>
@endif
@if (session('error'))
    <div class="mb-5 border-l-4 border-brick bg-brick/10 px-4 py-3 text-sm text-charcoal-800" role="alert">{{ session('error') }}</div>
@endif

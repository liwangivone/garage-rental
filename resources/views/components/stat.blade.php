@props(['label', 'value', 'hint' => null, 'accent' => false])
<div class="card relative p-5">
    @if ($accent)<span class="absolute inset-y-0 left-0 w-1 bg-ember-500"></span>@endif
    <p class="text-xs font-semibold uppercase tracking-wider text-charcoal-500">{{ $label }}</p>
    <p class="mt-2 font-display text-3xl font-semibold tracking-wide text-charcoal-900">{{ $value }}</p>
    @if ($hint)<p class="mt-1 text-xs text-charcoal-500">{{ $hint }}</p>@endif
</div>

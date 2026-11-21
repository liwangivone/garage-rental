@props(['rows', 'key' => 'revenue', 'money' => true])
@php
    $max = max($rows->max($key), 0);
    $dense = $rows->count() > 14;
@endphp
<div class="flex h-52 items-end gap-1 border-b border-cream-300 px-1 pb-0 {{ $dense ? '' : 'gap-3' }}" role="img" aria-label="Grafik">
    @foreach ($rows as $row)
        @php $pct = $max > 0 ? max(($row[$key] / $max) * 100, $row[$key] > 0 ? 3 : 0) : 0; @endphp
        <div class="group flex h-full min-w-0 flex-1 flex-col justify-end" title="{{ $row['label'] }}: {{ $money ? \App\Support\Format::rupiah($row[$key]) : $row[$key] }}">
            <div class="w-full rounded-t-sm {{ $row[$key] > 0 ? 'bg-ember-500 group-hover:bg-ember-600' : 'bg-cream-200' }}" style="height: {{ $row[$key] > 0 ? $pct : 1 }}%"></div>
        </div>
    @endforeach
</div>
<div class="mt-1 flex gap-1 px-1 {{ $dense ? '' : 'gap-3' }}">
    @foreach ($rows as $row)
        <span class="min-w-0 flex-1 truncate text-center {{ $dense ? 'text-[10px]' : 'text-xs' }} text-charcoal-500">{{ $row['label'] }}</span>
    @endforeach
</div>

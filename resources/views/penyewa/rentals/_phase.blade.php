@php $phase = $rental->phase(); @endphp
<span @class(['badge', 'badge-ember' => $phase === 'active', 'badge-olive' => $phase === 'upcoming', 'badge-muted' => $phase === 'finished'])>
    {{ ['active' => 'Berlangsung', 'upcoming' => 'Akan datang', 'finished' => 'Selesai'][$phase] }}
</span>

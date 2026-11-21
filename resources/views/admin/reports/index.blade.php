@php
    $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Illuminate\Support\Carbon::create(2000, $m, 1)->translatedFormat('F')]);
    $periodLabel = match ($view) {
        'monthly' => "Tahun {$year}",
        'daily' => $months[$month].' '.$year,
        default => ($month ? $months[$month].' ' : 'Tahun ').$year,
    };
    $firstColumn = ['monthly' => 'Bulan', 'daily' => 'Tanggal', 'type' => 'Tipe garasi', 'penyewa' => 'Penyewa'][$view];
@endphp
<x-layouts.admin title="Laporan Sewa" :subtitle="$periodLabel">
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($views as $key => $label)
            <a href="{{ route('admin.reports.index', ['view' => $key, 'year' => $year, 'month' => $month]) }}" @class(['btn btn-sm', 'btn-dark' => $view === $key, 'btn-ghost' => $view !== $key])>{{ $label }}</a>
        @endforeach
    </div>

    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-4 p-4">
        <input type="hidden" name="view" value="{{ $view }}">
        <div>
            <label for="year" class="label">Tahun</label>
            <select id="year" name="year" class="input">
                @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
            </select>
        </div>
        @if ($view !== 'monthly')
            <div>
                <label for="month" class="label">Bulan</label>
                <select id="month" name="month" class="input">
                    @if ($view !== 'daily')<option value="">Semua bulan</option>@endif
                    @foreach ($months as $m => $name)<option value="{{ $m }}" @selected($m === $month)>{{ $name }}</option>@endforeach
                </select>
            </div>
        @endif
        <button class="btn btn-primary">Tampilkan</button>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Jumlah sewa" :value="$report['count']" :hint="$periodLabel.' · pembayaran terverifikasi'" />
        <x-stat label="Pendapatan sewa" :value="\App\Support\Format::rupiah($report['rental'])" hint="Termasuk perpanjangan, di tanggal mulai sewa" />
        <x-stat label="Biaya listrik" :value="\App\Support\Format::rupiah($report['electricity'])" />
        <x-stat label="Total" :value="\App\Support\Format::rupiah($report['revenue'])" hint="Sewa + listrik" accent />
    </div>

    <section class="card mb-6 p-5">
        <h2 class="card-title mb-4">Total {{ strtolower($views[$view]) }}</h2>
        @if ($report['rows']->isEmpty() || $report['revenue'] == 0)
            <p class="py-10 text-center text-sm text-charcoal-500">Belum ada data sewa pada periode ini.</p>
        @else
            <x-bar-chart :rows="$report['rows']->take(20)" />
        @endif
    </section>

    <section class="card table-wrap">
        <table class="table">
            <thead><tr><th>{{ $firstColumn }}</th><th class="text-right">Jumlah sewa</th><th class="text-right">Sewa</th><th class="text-right">Listrik</th><th class="text-right">Total</th></tr></thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="text-right">{{ $row['count'] }}</td>
                        <td class="text-right">@rupiah($row['rental'])</td>
                        <td class="text-right">@rupiah($row['electricity'])</td>
                        <td class="text-right font-medium">@rupiah($row['revenue'])</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-charcoal-500">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-cream-100 font-semibold">
                    <td class="px-4 py-3 uppercase tracking-wider">Total</td>
                    <td class="px-4 py-3 text-right">{{ $report['count'] }}</td>
                    <td class="px-4 py-3 text-right">@rupiah($report['rental'])</td>
                    <td class="px-4 py-3 text-right">@rupiah($report['electricity'])</td>
                    <td class="px-4 py-3 text-right">@rupiah($report['revenue'])</td>
                </tr>
            </tfoot>
        </table>
    </section>
</x-layouts.admin>

<x-layouts.admin title="Buat Garasi" subtitle="Buat sekelompok garasi dengan kode otomatis per tipe">
    <form method="POST" action="{{ route('admin.garages.store') }}" class="card max-w-xl space-y-5 p-6">
        @csrf
        @php
            $oldType = old('type', 'bicycle');
            $codes = $nextCodes->all();
        @endphp
        <div>
            <label for="type" class="label">Tipe garasi</label>
            <select id="type" name="type" class="input" required>
                @foreach (\App\Enums\GarageType::cases() as $t)
                    <option value="{{ $t->value }}" data-next="{{ $codes[$t->value] }}" @selected($oldType === $t->value)>{{ $t->label() }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-charcoal-500">Kode berikutnya: <strong id="next-code" class="font-display text-sm tracking-wider text-charcoal-800">{{ $codes[$oldType] }}</strong> (S sepeda, C mobil, E mobil listrik)</p>
            @error('type')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="quantity" class="label">Jumlah garasi (maks. 999)</label>
            <input id="quantity" name="quantity" type="number" min="1" max="999" value="{{ old('quantity') }}" required class="input">
            @error('quantity')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="daily_price" class="label">Harga sewa harian (Rp)</label>
            <input id="daily_price" name="daily_price" type="number" min="1" step="1" value="{{ old('daily_price') }}" required class="input">
            <p class="mt-1 text-xs text-charcoal-500">Harga bulanan otomatis 25 × harga harian: <strong id="monthly-preview">—</strong></p>
            @error('daily_price')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div id="kwh-field" class="hidden">
            <label for="electricity_price_per_kwh" class="label">Harga isi listrik per kWh (Rp)</label>
            <input id="electricity_price_per_kwh" name="electricity_price_per_kwh" type="number" min="1" step="1" value="{{ old('electricity_price_per_kwh') }}" class="input">
            @error('electricity_price_per_kwh')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3 pt-2">
            <button class="btn btn-primary">Buat Garasi</button>
            <a href="{{ route('admin.garages.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>

    <script>
        (() => {
            const type = document.getElementById('type');
            const daily = document.getElementById('daily_price');
            const fmt = (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID');
            const sync = () => {
                document.getElementById('next-code').textContent = type.selectedOptions[0].dataset.next;
                const kwh = document.getElementById('kwh-field');
                const electric = type.value === 'electric_car';
                kwh.classList.toggle('hidden', !electric);
                document.getElementById('electricity_price_per_kwh').required = electric;
                document.getElementById('monthly-preview').textContent = daily.value ? fmt(daily.value * 25) : '—';
            };
            type.addEventListener('change', sync);
            daily.addEventListener('input', sync);
            sync();
        })();
    </script>
</x-layouts.admin>

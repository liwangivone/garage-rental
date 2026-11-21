@php
    $oldVehicle = old('vehicle_id', $vehicles->isNotEmpty() ? $vehicles->first()->id : 'new');
    $oldType = old('vehicle_type', 'car');
    $savedMap = $vehicles->mapWithKeys(fn ($v) => [$v->id => ['type' => $v->type->value, 'battery' => $v->battery_capacity_kwh]]);
@endphp
<x-layouts.customer title="Sewa Garasi Baru" subtitle="Isi data kendaraan dan periode sewa, lalu pilih garasi yang tersedia">
    <form id="rental-form" method="POST" action="{{ route('penyewa.rentals.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- 1. Kendaraan --}}
        <section class="card p-5">
            <h2 class="card-title mb-4">1. Kendaraan</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="vehicle_id" class="label">Kendaraan</label>
                    <select id="vehicle_id" name="vehicle_id" class="input">
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->id }}" @selected((string) $oldVehicle === (string) $v->id)>{{ $v->type->label() }} · {{ $v->brand }} {{ $v->model }} {{ $v->plate_number ? '('.$v->plate_number.')' : '' }}</option>
                        @endforeach
                        <option value="new" @selected($oldVehicle === 'new')>+ Kendaraan baru</option>
                    </select>
                    @error('vehicle_id')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="field-error" data-quote-error="vehicle_id"></p>
                </div>

                <div id="new-vehicle" class="contents">
                    <div>
                        <label for="vehicle_type" class="label">Tipe kendaraan</label>
                        <select id="vehicle_type" name="vehicle_type" class="input">
                            @foreach ($vehicleTypes as $t)
                                <option value="{{ $t->value }}" @selected($oldType === $t->value)>{{ $t->label() }}</option>
                            @endforeach
                        </select>
                        @error('vehicle_type')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div id="plate-row">
                        <label for="plate_number" class="label">Nomor plat</label>
                        <input id="plate_number" name="plate_number" value="{{ old('plate_number') }}" maxlength="15" class="input uppercase" placeholder="DD 1234 AB">
                        @error('plate_number')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="brand" class="label">Merek</label>
                        <input id="brand" name="brand" value="{{ old('brand') }}" maxlength="100" class="input" placeholder="Toyota">
                        @error('brand')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="model" class="label">Tipe</label>
                        <input id="model" name="model" value="{{ old('model') }}" maxlength="100" class="input" placeholder="Avanza">
                        @error('model')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div id="battery-row">
                        <label for="battery_capacity_kwh" class="label">Kapasitas baterai (kWh)</label>
                        <input id="battery_capacity_kwh" name="battery_capacity_kwh" type="number" step="0.1" min="1" max="500" value="{{ old('battery_capacity_kwh') }}" class="input" placeholder="58">
                        @error('battery_capacity_kwh')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div id="current-battery-row" class="sm:col-span-2 hidden">
                    <label for="battery_current_kwh" class="label">Sisa baterai saat ini (kWh)</label>
                    <input id="battery_current_kwh" name="battery_current_kwh" type="number" step="0.1" min="0" max="500" value="{{ old('battery_current_kwh') }}" class="input max-w-xs" placeholder="15">
                    <p class="mt-1 text-xs text-charcoal-500">Wajib untuk garasi mobil listrik. Biaya listrik = (kapasitas baterai − sisa baterai) × harga per kWh.<span id="capacity-hint"></span></p>
                    @error('battery_current_kwh')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="field-error" data-quote-error="battery_current_kwh"></p>
                </div>
            </div>
        </section>

        {{-- 2. Periode --}}
        <section class="card p-5">
            <h2 class="card-title mb-4">2. Periode sewa</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <span class="label">Tipe sewa</span>
                    <div class="flex gap-3">
                        @foreach (['daily' => 'Harian', 'monthly' => 'Bulanan (1 bulan)'] as $val => $label)
                            <label class="flex cursor-pointer items-center gap-2 border border-cream-300 bg-white px-4 py-2 text-sm has-[:checked]:border-ember-500 has-[:checked]:bg-ember-100">
                                <input type="radio" name="rental_type" value="{{ $val }}" class="accent-ember-500" @checked(old('rental_type', 'daily') === $val)> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('rental_type')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-charcoal-500">Sewa lebih dari satu bulan: buat sewa bulanan, lalu sewa berikutnya mulai setelah sewa pertama selesai.</p>
                </div>
                <div id="days-row">
                    <label for="days" class="label">Jumlah hari</label>
                    <input id="days" name="days" type="number" min="1" max="30" value="{{ old('days', 1) }}" class="input">
                    @error('days')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="field-error" data-quote-error="days"></p>
                </div>
                <div>
                    <label for="start_date" class="label">Tanggal mulai</label>
                    <input id="start_date" name="start_date" type="date" min="{{ today()->toDateString() }}" max="{{ today()->addDays(\App\Services\RentalBookingService::MAX_ADVANCE_DAYS)->toDateString() }}" value="{{ old('start_date', today()->toDateString()) }}" required class="input">
                    <p class="mt-1 text-xs text-charcoal-500">Maksimal {{ \App\Services\RentalBookingService::MAX_ADVANCE_DAYS }} hari dari hari ini.</p>
                    @error('start_date')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="field-error" data-quote-error="start_date"></p>
                </div>
                <div class="sm:col-span-2">
                    <span class="label">Tanggal selesai (otomatis)</span>
                    <p id="end-date" class="border border-dashed border-cream-300 bg-cream-100 px-3 py-2 text-sm text-charcoal-600">—</p>
                </div>
            </div>
        </section>

        {{-- 3. Garasi --}}
        <section class="card p-5">
            <h2 class="card-title mb-4">3. Garasi</h2>
            <div class="max-w-sm">
                <label for="garage_type" class="label">Tipe garasi</label>
                <select id="garage_type" name="garage_type" class="input"></select>
                @error('garage_type')<p class="field-error">{{ $message }}</p>@enderror
                <p class="field-error" data-quote-error="garage_type"></p>
            </div>
            <div class="mt-4">
                <span class="label">Garasi tersedia pada tanggal tersebut</span>
                <div id="garage-list" class="grid max-h-72 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3">
                    <p class="col-span-full text-sm text-charcoal-500">Lengkapi kendaraan dan periode untuk melihat garasi yang tersedia.</p>
                </div>
                @error('garage_id')<p class="field-error">{{ $message }}</p>@enderror
                <p class="field-error" data-quote-error="garage_id"></p>
            </div>
        </section>

        {{-- 4. Biaya & bukti bayar --}}
        <section class="card p-5">
            <h2 class="card-title mb-4">4. Biaya & bukti pelunasan</h2>
            <div class="grid gap-6 md:grid-cols-2">
                <div id="cost-box" class="border border-cream-200 bg-cream-100 p-4 text-sm">
                    <p class="text-charcoal-500">Pilih garasi untuk melihat total biaya.</p>
                </div>
                <div>
                    <label for="payment_proof" class="label">Bukti pelunasan (gambar)</label>
                    <input id="payment_proof" name="payment_proof" type="file" accept="image/png,image/jpeg,image/webp" required class="input file:mr-3 file:border-0 file:bg-charcoal-800 file:px-3 file:py-1 file:text-xs file:font-semibold file:uppercase file:text-cream-50">
                    <p class="mt-1 text-xs text-charcoal-500">Format JPG, PNG, atau WEBP, maksimal 4 MB.</p>
                    @error('payment_proof')<p class="field-error">{{ $message }}</p>@enderror
                    <img id="proof-preview" alt="Pratinjau bukti" class="mt-3 hidden max-h-40 border border-cream-200 bg-white object-contain">
                </div>
            </div>
        </section>

        <div class="flex gap-3">
            <button class="btn btn-primary">Simpan sewa</button>
            <a href="{{ route('penyewa.dashboard') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>

    <script>
        (() => {
            const quoteUrl = @json(route('penyewa.rentals.quote'));
            const allowed = @json($allowed);
            const labels = @json($garageLabels);
            const saved = @json($savedMap);
            const oldGarage = @json(old('garage_id'));
            const oldGarageType = @json(old('garage_type'));

            const $ = (id) => document.getElementById(id);
            const vehicleSel = $('vehicle_id'), typeSel = $('vehicle_type'), garageTypeSel = $('garage_type');
            const list = $('garage-list');
            let selectedGarage = oldGarage ? String(oldGarage) : '';
            let timer, seq = 0;

            const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const rentalType = () => document.querySelector('input[name=rental_type]:checked')?.value;
            const isNew = () => vehicleSel.value === 'new';
            const vehicleType = () => isNew() ? typeSel.value : (saved[vehicleSel.value]?.type);

            function syncVehicle() {
                const t = vehicleType();
                $('new-vehicle').classList.toggle('hidden', !isNew());
                $('new-vehicle').classList.toggle('contents', isNew());
                $('plate-row').classList.toggle('hidden', t === 'bicycle');
                $('battery-row').classList.toggle('hidden', t !== 'electric_car');
                $('current-battery-row').classList.toggle('hidden', t !== 'electric_car');
                const cap = isNew() ? $('battery_capacity_kwh').value : saved[vehicleSel.value]?.battery;
                $('capacity-hint').textContent = cap ? ` Kapasitas penuh: ${parseFloat(cap)} kWh.` : '';

                const opts = allowed[t] || [];
                const current = garageTypeSel.value || oldGarageType;
                garageTypeSel.innerHTML = opts.length > 1 ? '<option value="">Pilih tipe garasi</option>' : '';
                opts.forEach((o) => garageTypeSel.insertAdjacentHTML('beforeend', `<option value="${o}">${esc(labels[o])}</option>`));
                garageTypeSel.value = opts.includes(current) ? current : (opts.length === 1 ? opts[0] : '');
            }

            function syncRental() {
                $('days-row').classList.toggle('hidden', rentalType() !== 'daily');
            }

            const schedule = () => { clearTimeout(timer); timer = setTimeout(fetchQuote, 250); };

            async function fetchQuote() {
                const params = new URLSearchParams({
                    rental_type: rentalType() || '', days: $('days').value, start_date: $('start_date').value,
                    garage_type: garageTypeSel.value, garage_id: selectedGarage,
                });
                if (isNew()) {
                    params.set('vehicle_type', typeSel.value);
                    params.set('battery_capacity_kwh', $('battery_capacity_kwh').value);
                } else {
                    params.set('vehicle_id', vehicleSel.value);
                }
                params.set('battery_current_kwh', $('battery_current_kwh').value);
                const mine = ++seq;
                try {
                    const res = await fetch(`${quoteUrl}?${params}`, { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    if (mine === seq) render(data);
                } catch (e) { /* abaikan; server tetap memvalidasi saat submit */ }
            }

            function render(d) {
                document.querySelectorAll('[data-quote-error]').forEach((el) => { el.textContent = d.errors[el.dataset.quoteError] || ''; });

                const parts = d.breakdown ? [d.breakdown.monthly ? `${d.breakdown.monthly} bulan` : '', d.breakdown.daily ? `${d.breakdown.daily} hari` : ''].filter(Boolean).join(' + ') : '';
                $('end-date').textContent = d.end_date_label
                    ? `${d.end_date_label} · ${d.duration_days} hari${d.breakdown.monthly ? ` (${parts})` : ''}`
                    : '—';
                if (d.max_daily_days) $('days').max = d.max_daily_days;

                const ids = d.garages.map((g) => String(g.id));
                if (selectedGarage && !ids.includes(selectedGarage) && !d.errors.garage_id) selectedGarage = '';

                if (!garageTypeSel.value || !d.end_date) {
                    list.innerHTML = '<p class="col-span-full text-sm text-charcoal-500">Lengkapi kendaraan dan periode untuk melihat garasi yang tersedia.</p>';
                } else if (!d.garages.length) {
                    list.innerHTML = '<p class="col-span-full text-sm text-brick">Tidak ada garasi tersedia pada tanggal tersebut.</p>';
                } else {
                    list.innerHTML = d.garages.map((g) => `
                        <label class="flex cursor-pointer items-center gap-3 border border-cream-300 bg-white px-3 py-2 text-sm has-[:checked]:border-ember-500 has-[:checked]:bg-ember-100">
                            <input type="radio" name="garage_id" value="${g.id}" class="accent-ember-500" ${String(g.id) === selectedGarage ? 'checked' : ''}>
                            <span><span class="block font-display text-lg font-semibold tracking-wider">${esc(g.code)}</span>
                            <span class="block text-xs text-charcoal-500">${esc(g.daily_price)}/hari${g.electricity_price ? ' · listrik ' + esc(g.electricity_price) + '/kWh' : ''}</span></span>
                        </label>`).join('');
                }

                const box = $('cost-box');
                if (d.costs) {
                    box.innerHTML = `
                        <dl class="space-y-2">
                            <div class="flex justify-between"><dt class="text-charcoal-500">Biaya sewa</dt><dd>${esc(d.costs.rental_cost)}</dd></div>
                            ${d.costs.charge_kwh !== null ? `<div class="flex justify-between"><dt class="text-charcoal-500">Isi listrik (${esc(d.costs.charge_kwh)} kWh)</dt><dd>${esc(d.costs.electricity_cost)}</dd></div>` : ''}
                            <div class="flex items-baseline justify-between border-t border-cream-300 pt-2"><dt class="font-semibold uppercase tracking-wider">Total bayar</dt>
                            <dd class="font-display text-2xl font-semibold text-ember-600">${esc(d.costs.total)}</dd></div>
                        </dl>`;
                } else {
                    box.innerHTML = '<p class="text-charcoal-500">Pilih garasi untuk melihat total biaya.</p>';
                }
            }

            list.addEventListener('change', (e) => {
                if (e.target.name === 'garage_id') { selectedGarage = e.target.value; schedule(); }
            });
            vehicleSel.addEventListener('change', () => { syncVehicle(); schedule(); });
            typeSel.addEventListener('change', () => { syncVehicle(); schedule(); });
            garageTypeSel.addEventListener('change', () => { selectedGarage = ''; schedule(); });
            ['battery_capacity_kwh', 'battery_current_kwh', 'days', 'start_date'].forEach((id) => $(id).addEventListener('input', schedule));
            $('battery_capacity_kwh').addEventListener('input', syncVehicle);
            document.querySelectorAll('input[name=rental_type]').forEach((r) => r.addEventListener('change', () => { syncRental(); schedule(); }));

            $('payment_proof').addEventListener('change', (e) => {
                const f = e.target.files[0], img = $('proof-preview');
                if (f && f.type.startsWith('image/')) { img.src = URL.createObjectURL(f); img.classList.remove('hidden'); }
                else { img.classList.add('hidden'); }
            });

            syncVehicle(); syncRental(); fetchQuote();
        })();
    </script>
</x-layouts.customer>

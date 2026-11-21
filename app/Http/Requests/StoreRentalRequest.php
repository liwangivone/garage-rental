<?php

namespace App\Http\Requests;

use App\Enums\GarageType;
use App\Enums\RentalType;
use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plate_number')) {
            $this->merge(['plate_number' => strtoupper(preg_replace('/\s+/', ' ', trim($this->input('plate_number'))))]);
        }
        if ($this->input('vehicle_id') === 'new') {
            $this->merge(['vehicle_id' => null]);
        }
    }

    public function rules(): array
    {
        $isNew = ! $this->filled('vehicle_id');
        $type = VehicleType::tryFrom((string) $this->input('vehicle_type'));

        return [
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('user_id', $this->user()->id)],
            'vehicle_type' => [Rule::requiredIf($isNew), 'nullable', Rule::enum(VehicleType::class)],
            'plate_number' => [
                Rule::requiredIf($isNew && $type?->requiresPlateNumber()),
                'nullable', 'string', 'max:15',
            ],
            'brand' => [Rule::requiredIf($isNew), 'nullable', 'string', 'max:100'],
            'model' => [Rule::requiredIf($isNew), 'nullable', 'string', 'max:100'],
            'battery_capacity_kwh' => [Rule::requiredIf($isNew && $type?->requiresBatteryCapacity()), 'nullable', 'numeric', 'min:1', 'max:500'],
            // Wajib & maksimal kapasitas dicek di RentalBookingService (kapasitas bisa dari kendaraan tersimpan).
            'battery_current_kwh' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'rental_type' => ['required', Rule::enum(RentalType::class)],
            'days' => [Rule::requiredIf($this->input('rental_type') === RentalType::Daily->value), 'nullable', 'integer', 'min:1', 'max:30'],
            'start_date' => ['required', 'date'],
            'garage_type' => ['required', Rule::enum(GarageType::class)],
            'garage_id' => ['required', 'integer'],
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /** Data kendaraan baru, atau [] bila memakai kendaraan tersimpan. */
    public function vehicleData(): array
    {
        if ($this->filled('vehicle_id')) {
            return [];
        }

        $type = VehicleType::from($this->input('vehicle_type'));

        return [
            'type' => $type,
            'plate_number' => $type->requiresPlateNumber() ? $this->input('plate_number') : null,
            'brand' => $this->input('brand'),
            'model' => $this->input('model'),
            'battery_capacity_kwh' => $type->requiresBatteryCapacity() ? $this->input('battery_capacity_kwh') : null,
        ];
    }
}

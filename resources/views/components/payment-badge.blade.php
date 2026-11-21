@props(['status'])
<span @class([
    'badge',
    'badge-ember' => $status === \App\Enums\PaymentStatus::Pending,
    'badge-olive' => $status === \App\Enums\PaymentStatus::Approved,
    'badge-brick' => $status === \App\Enums\PaymentStatus::Rejected,
])>{{ $status->label() }}</span>

<?php

namespace App\Console\Commands;

use App\Models\Garage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('garages:sync-status')]
#[Description("Sinkronkan status garasi dengan tanggal sewa (used hanya saat sewa berjalan)")]
class SyncGarageStatus extends Command
{
    public function handle(): int
    {
        Garage::syncAllStatuses();
        $this->info('Status garasi disinkronkan.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\TreatmentReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('isms:remind-treatments')]
#[Description('Hantar peringatan dalaman tarikh sasaran tindakan rawatan tanpa pendua.')]
class SendTreatmentReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TreatmentReminders $reminders): int
    {
        $this->info($reminders->send().' peringatan baharu dihantar.');

        return self::SUCCESS;
    }
}

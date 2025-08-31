<?php

namespace App\Console\Commands;

use App\Models\Barcode;
use Illuminate\Console\Command;

class MarkBarcodesAssigned extends Command
{
    protected $signature = 'barcodes:mark-assigned 
                          {--count=40000 : Number of barcodes to mark as assigned}
                          {--up-to= : Mark all barcodes up to this barcode number as assigned}';

    protected $description = 'Mark barcodes as assigned by count or up to a specific barcode number';

    public function handle()
    {
        $count = $this->option('count');
        $upTo = $this->option('up-to');

        if ($upTo) {
            // Mark all barcodes up to the specified barcode number
            $this->info("Marking all barcodes up to '{$upTo}' as assigned...");

            $updated = Barcode::where('barcode', '<=', $upTo)
                ->update([
                    'is_assigned' => true,
                    'updated_at' => now(),
                ]);

            $this->info("✅ Successfully marked {$updated} barcodes up to '{$upTo}' as assigned");
        } else {
            // Mark first N barcodes by count
            $this->info("Marking first {$count} barcodes as assigned...");

            $updated = Barcode::orderBy('barcode')
                ->limit($count)
                ->update([
                    'is_assigned' => true,
                    'updated_at' => now(),
                ]);

            $this->info("✅ Successfully marked {$updated} barcodes as assigned");
        }

        return Command::SUCCESS;
    }
}

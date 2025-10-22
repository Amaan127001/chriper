<?php

namespace App\Console\Commands;

use App\Models\Chirp;
use Illuminate\Console\Command;

class DeleteAllChirps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chirps:delete-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all chirps from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = Chirp::count();
        Chirp::truncate();

        $this->info("Successfully deleted {$count} chirps.");

        return Command::SUCCESS;
    }
}

<?php

namespace Ssntpl\CloudStorage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class SyncIndividualFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Set the maximum number of tries
    public $tries = 3;

    protected $path;

    protected $toDisk;

    /**
     * Create a new job instance.
     */
    public function __construct($path, $toDisk)
    {
        $this->path = $path;
        $this->toDisk = $toDisk;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Storage::disk('cloud')->syncToDisk($this->path, $this->toDisk);
    }

    public function backoff()
    {
        $interval = 1; // Set the interval in seconds for all retries

        return array_fill(0, $this->tries - 1, $interval);
    }
}

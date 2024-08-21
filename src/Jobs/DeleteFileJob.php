<?php

namespace Ssntpl\FlysystemCloud\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DeleteFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Set the maximum number of tries
    public $tries = 3;
    protected $path;

    /**
     * Create a new job instance.
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Execute the job.
     * @return void
     */
    public function handle()
    {
        Storage::disk('cloud')->deleteFromCache($this->path);
    }

    public function backoff()
    {
        $interval = 1; // Set the interval in seconds for all retries
        return array_fill(0, $this->tries - 1, $interval);
    }
}

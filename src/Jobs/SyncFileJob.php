<?php

namespace Ssntpl\CloudStorage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ssntpl\CloudStorage\CloudStorageAdapter;

class SyncFileJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Set the maximum number of tries
    public $tries = 3;
    protected $path;

    protected $fromDisk;

    protected $toDisk;

    /**
     * Create a new job instance.
     */
    public function __construct($path, $fromDisk, $toDisk)
    {
        $this->path = $path;
        $this->fromDisk = $fromDisk;
        $this->toDisk = $toDisk;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CloudStorageAdapter::syncToDisk($this->path, $this->fromDisk, $this->toDisk);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->path.'_'.$this->toDisk.'_sync';
    }

    public function backoff()
    {
        $interval = 1; // Set the interval in seconds for all retries

        return array_fill(0, $this->tries - 1, $interval);
    }
}

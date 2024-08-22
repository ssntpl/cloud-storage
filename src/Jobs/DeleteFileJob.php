<?php

namespace Ssntpl\CloudStorage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ssntpl\CloudStorage\CloudStorageAdapter;

class DeleteFileJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Set the maximum number of tries
    public $tries = 3;

    protected $path;
    protected $fromdisk;
    protected $remoteDisks;

    /**
     * Create a new job instance.
     */
    public function __construct($path, $fromDisk, $remoteDisks =null)
    {
        $this->path = $path;
        $this->fromdisk = $fromDisk;
        $this->remoteDisks = $remoteDisks;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CloudStorageAdapter::deleteFromDisk($this->path, $this->fromdisk, $this->remoteDisks);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->path.'_'.$this->fromdisk.'_delete';
    }

    public function backoff()
    {
        $interval = 1; // Set the interval in seconds for all retries

        return array_fill(0, $this->tries - 1, $interval);
    }
}

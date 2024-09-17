<?php

namespace Ssntpl\CloudStorage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ActionSyncJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const MOVE = 'move';

    const APPEND = 'append';

    const PREPEND = 'prepend';

    const MAKE_DIRECTORY = 'makeDirectory';

    const DELETE_DIRECTORY = 'deleteDirectory';

    const SET_VISIBILITY = 'setVisibility';

    // Set the maximum number of tries
    public $tries = 2;
    protected $path;

    protected $disk;

    protected $action;

    protected $data;

    /**
     * Create a new job instance.
     * @param string $action
     * @param string $disk
     * @param string $path
     * @param string $data
     */
    public function __construct($action, $disk, $path, $data = null)
    {
        $this->action = $action;
        $this->disk = $disk;
        $this->path = $path;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        switch ($this->action){
            case self::MOVE:
                Storage::disk($this->disk)->move($this->path,$this->data);
                break;

            case self::APPEND:
                Storage::disk($this->disk)->append($this->path,$this->data);
                break;

            case self::PREPEND:
                Storage::disk($this->disk)->prepend($this->path,$this->data);
                break;

            case self::SET_VISIBILITY:
                Storage::disk($this->disk)->setVisibility($this->path,$this->data);
                break;

            case self::MAKE_DIRECTORY:
                Storage::disk($this->disk)->makeDirectory($this->path);
                break;

            case self::DELETE_DIRECTORY:
                Storage::disk($this->disk)->deleteDirectory($this->path);
                break;

        } 

    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        if ($this->data){
            return $this->path.'_'.$this->disk.'_'.$this->action.'_'.$this->data;
        }
        return $this->path.'_'.$this->disk.'_'.$this->action;
    }

    public function backoff()
    {
        $interval = 1; // Set the interval in seconds for all retries

        return array_fill(0, $this->tries - 1, $interval);
    }
}

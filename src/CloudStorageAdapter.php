<?php

namespace Ssntpl\CloudStorage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Ssntpl\CloudStorage\Jobs\DeleteFileJob;
use Ssntpl\CloudStorage\Jobs\SyncFileJob;
use Ssntpl\CloudStorage\Jobs\ActionSyncJob;
use Exception;
use Illuminate\Support\Facades\Log;

class CloudStorageAdapter implements Filesystem
{
    protected $cacheDisk;

    protected $remoteDisks;

    protected $cacheTime;

    protected $queue;

    protected $connection;

    public function __construct($config)
    {
        $this->connection = $config['connection']??null;
        $this->queue = $config['queue']??null;
        $this->cacheDisk = $config['cache_disk'];
        $this->cacheTime = $config['cache_time'];
        $this->remoteDisks = is_array($config['remote_disks'])? $config['remote_disks']: explode(',',$config['remote_disks']);
    }

    private function diskP($disk)
    {
        return Storage::disk($disk);
    }

    private function setInCacheDisk($path, $deleteCache = false)
    {
        $res = true;
        if (! $this->checkExistance($this->cacheDisk, $path)) {
            $res = false;
            $deleteCache = false;
            foreach ($this->remoteDisks as $remoteDisk) {
                if ($this->checkExistance($remoteDisk, $path)) {
                    $res = $this->diskP($this->cacheDisk)->writeStream($path, $this->diskP($remoteDisk)->readStream($path));
                    $deleteCache = true;
                    break;
                }
            }
        } 
        if ($deleteCache && $this->cacheTime != 0) {
            DeleteFileJob::dispatch($path, $this->cacheDisk, $this->remoteDisks)->onConnection($this->connection)->onQueue($this->queue)->delay(now()->addHours($this->cacheTime));
        }

        return $res;
    }

    public function sync($path, $fromDisk)
    {
        if ($this->setInCacheDisk($path, true)) {
            foreach ($this->remoteDisks as $remoteDisk) {
                if (! $this->checkExistance($remoteDisk, $path)) {
                    SyncFileJob::dispatch($path, $fromDisk, $remoteDisk)->onConnection($this->connection)->onQueue($this->queue);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * sync file fromDisk to toDisk with path. If want to delete the file from fromDisk set the value true of deleteFromDisk
     * @param  string  $path
     * @param  string  $fromDisk
     * @param  string  $toDisk
     * @param  bool  $deleteFromDisk
     * @return bool|\Exception
     */
    public static function syncToDisk($path, $fromDisk, $toDisk, $deleteFromDisk = false)
    {
        $res = Storage::disk($toDisk)->writeStream($path, Storage::disk($fromDisk)->readStream($path));
        if ($deleteFromDisk && $res){
            Storage::disk($fromDisk)->delete($path);
        }
        
        if (!$res){
            throw new Exception('unable to sync file into disk');
        }
        return $res;
    }


    /**
     * Delete file from disk if file exist at any given disks(ifSyscedDisks), If ifSyncedDisks are null delete file from disk without check.
     * @param string $path
     * @param string $disk
     * @param array $ifExistDisk
     * @return bool
     */
    public static function deleteFromDisk($path, $fromDisk, $ifSyncedDisks = null)
    {
        $isExist = self::checkExistance($fromDisk, $path);
        if ( $isExist && $ifSyncedDisks) {
            foreach ($ifSyncedDisks as $disk) {
                if (self::checkExistance($disk, $path)) {                    
                    return Storage::disk($fromDisk)->delete($path);
                }
            }
            return false;
        } elseif ($isExist){
            return Storage::disk($fromDisk)->delete($path);
        }
        return false;
    }

    /**
     * Summary of url
     * @param string $path
     */
    public function url($path)
    {
        if (! $this->checkExistance($this->cacheDisk, $path)) {
            foreach ($this->remoteDisks as $remoteDisk) {
                if ($this->checkExistance($remoteDisk,$path)) {
                    return $this->diskP($remoteDisk)->url($path);
                }
            }
        } else {
            return $this->diskP($this->cacheDisk)->url($path);
        }
    }

    private static function checkExistance($disk, $path){
        try {
            return Storage::disk($disk)->exists($path);
        } catch (\Throwable $exception) {
            Log::error("Unable to check file existence on ".$disk);
            return false;
        }
    }

    // Implement all required methods from the Filesystem interface
    public function put($path, $contents, $options = [])
    {
        $res = $this->diskP($this->cacheDisk)->put($path, $contents, $options);
        if ($this->remoteDisks && $res) {
            $this->sync($path, $this->cacheDisk);
        }

        return $res;
    }

    public function path($path)
    {
        if ($this->setInCacheDisk($path)) {
            return $this->diskP($this->cacheDisk)->path($path);
        }
    }

    public function putFile($path, $file = null, $options = [])
    {
        $res = $this->diskP($this->cacheDisk)->putFile($path, $file, $options);
        if ($this->remoteDisks && $res) {
            $this->sync($path, $this->cacheDisk);
        }

        return $res;
    }

    public function putFileAs($path, $file, $name = null, $options = [])
    {
        $res = $this->diskP($this->cacheDisk)->putFileAs($path, $file, $name, $options);
        if ($this->remoteDisks && $res) {
            $this->sync("$path/$name", $this->cacheDisk);
        }

        return $res;
    }

    public function exists($path)
    {
        if ($this->checkExistance($this->cacheDisk, $path)) {
            return true;
        } else {
            foreach ($this->remoteDisks as $remoteDisk) {
                if ($this->checkExistance($remoteDisk,$path)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function get($path)
    {
        if ($this->setInCacheDisk($path)) {
            return $this->diskP($this->cacheDisk)->get($path);
        }
    }

    public function readStream($path)
    {
        if ($this->setInCacheDisk($path)) {
            return $this->diskP($this->cacheDisk)->readStream($path);
        }
    }

    public function writeStream($path, $resource, array $options = [])
    {
        $res = $this->diskP($this->cacheDisk)->writeStream($path, $resource, $options);
        if ($this->remoteDisks && $res) {
            $this->sync($path, $this->cacheDisk);
        }

        return $res;
    }

    public function copy($from, $to)
    {
        if ($this->setInCacheDisk($from)) {
            $res = $this->diskP($this->cacheDisk)->copy($from, $to);
            if ($this->remoteDisks && $res) {
                $this->sync($to, $this->cacheDisk);

                return $res;
            }
        }

        return false;
    }

    public function move($from, $to)
    {
        foreach ($this->remoteDisks as $remoteDisk) {
            ActionSyncJob::dispatch(ActionSyncJob::MOVE, $remoteDisk, $from, $to)->onConnection($this->connection)->onQueue($this->queue);
        }
        $this->diskP($this->cacheDisk)->move($from, $to);

        return true;
    }

    public function append($path, $data)
    {
        foreach ($this->remoteDisks as $remoteDisk) {
            ActionSyncJob::dispatch(ActionSyncJob::APPEND, $remoteDisk, $path, $data)->onConnection($this->connection)->onQueue($this->queue);
        }
        $this->diskP($this->cacheDisk)->append($path, $data);

        return true;
    }

    public function prepend($path, $data)
    {
        foreach ($this->remoteDisks as $remoteDisk) {
            ActionSyncJob::dispatch(ActionSyncJob::PREPEND, $remoteDisk, $path, $data)->onConnection($this->connection)->onQueue($this->queue);
        }
        $this->diskP($this->cacheDisk)->prepend($path, $data);

        return true;
    }

    public function setVisibility($path, $visibility)
    {
        foreach ($this->remoteDisks as $remoteDisk) {
            ActionSyncJob::dispatch(ActionSyncJob::SET_VISIBILITY, $remoteDisk, $path, $visibility)->onConnection($this->connection)->onQueue($this->queue);
        }
        $this->diskP($this->cacheDisk)->setVisibility($path, $visibility);

        return true;
    }
    
    public function makeDirectory($path)
    {
        foreach ($this->remoteDisks as $remoteDisk) {
            ActionSyncJob::dispatch(ActionSyncJob::MAKE_DIRECTORY, $remoteDisk, $path)->onConnection($this->connection)->onQueue($this->queue);
        }
        $this->diskP($this->cacheDisk)->makeDirectory($path);

        return true;
    }

    public function deleteDirectory($directory)
    {
        foreach ($this->remoteDisks as $remoteDisk) {
            ActionSyncJob::dispatch(ActionSyncJob::DELETE_DIRECTORY, $remoteDisk, $directory)->onConnection($this->connection)->onQueue($this->queue);
        }
        $this->diskP($this->cacheDisk)->deleteDirectory($directory);

        return true;
    }

    public function allDirectories($directory = null)
    {
        return $this->diskP($this->remoteDisks[0])->allDirectories($directory);
    }

    public function allFiles($directory = null)
    {
        return $this->diskP($this->remoteDisks[0])->allFiles($directory);
    }

    public function files($directory = null, $recursive = false)
    {
        return $this->diskP($this->remoteDisks[0])->files($directory, $recursive);
    }

    public function getVisibility($path)
    {
        if ($this->setInCacheDisk($path)) {
            return $this->diskP($this->cacheDisk)->getVisibility($path);
        }
    }

    public function lastModified($path)
    {
        return $this->diskP($this->remoteDisks[0])->lastModified($path);
    }

    public function size($path)
    {
        if ($this->setInCacheDisk($path)) {
            return $this->diskP($this->cacheDisk)->size($path);
        }
    }

    public function directories($directory = null, $recursive = false)
    {
        return $this->diskP($this->remoteDisks[0])->directories($directory, $recursive);
    }

    public function delete($paths)
    {        
        foreach ($this->remoteDisks as $remoteDisk) {
            DeleteFileJob::dispatch($paths, $remoteDisk)->onConnection($this->connection)->onQueue($this->queue);
        }
        return $this->diskP($this->cacheDisk)->delete($paths);
    }
}

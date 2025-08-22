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

    protected $disks;

    protected $readDisks;

    protected $writeDisks;

    protected $queue;

    protected $connection;

    protected $url;

    public function __construct($config)
    {
        $this->connection = $config['connection'] ?? null;
        $this->queue = $config['queue'] ?? null;
        $this->url = $config['url'] ?? null;
        $this->disks = is_array($config['disks'] ?? [])? $config['disks'] ?? []: explode(',',$config['disks']);
        if (count($this->disks) === 0 && isset($config['remote_disks']) && !empty($config['remote_disks'])) {
            $this->disks = is_array($config['remote_disks'] ?? [])? $config['remote_disks'] ?? []: explode(',',$config['remote_disks']);
        }
        for ($i = 0; $i < count($this->disks); $i++) {
            if (is_string($this->disks[$i])) {
                $this->disks[$i] = config("filesystems.disks")[$this->disks[$i]] ?? [];
            }
        }
        $this->writeDisks = array_filter($this->disks, fn($disk) => ($disk['write_enabled'] ?? true) === true);
        $this->readDisks = $this->disks;

        usort($this->writeDisks, function ($a, $b) {
            $aPriority = $a['write_priority'] ?? PHP_INT_MAX;
            $bPriority = $b['write_priority'] ?? PHP_INT_MAX;
            return $aPriority <=> $bPriority;
        });
        usort($this->readDisks, function ($a, $b) {
            $aPriority = $a['read_priority'] ?? PHP_INT_MAX;
            $bPriority = $b['read_priority'] ?? PHP_INT_MAX;
            return $aPriority <=> $bPriority;
        });
    }

    private function diskP($disk)
    {
        return Storage::build($disk);
    }

    public function sync($path, $fromDisk, $index)
    {
        foreach ($this->writeDisks as $idx => $disk) {
            if ($idx === $index) {
                if (($fromDisk['retention'] ?? 0) > 0) {
                    DeleteFileJob::dispatch($path, $fromDisk)->delay(now()->addDays($fromDisk['retention']))->onConnection($fromDisk['connection'] ?? null)->onQueue($fromDisk['queue'] ?? null);
                }
                continue; // Skip syncing to the same disk
            }
            SyncFileJob::dispatch($path, $fromDisk, $disk)->onConnection($this->connection)->onQueue($this->queue);
        }

        return true;
    }

    public static function syncToDisk($path, $fromDisk, $toDisk)
    {
        $res = false;
        $stream = Storage::build($fromDisk)->readStream($path);
        if ($stream) {
            try {
                $res = Storage::build($toDisk)->writeStream($path, $stream);
            } finally {
                if (isset($stream)) {
                    fclose($stream);
                }
            }
            
            if (!$res){
                throw new Exception('unable to sync file into disk');
            } else if ($res && ($toDisk['retention'] ?? 0) > 0) {
                DeleteFileJob::dispatch($path, $toDisk)->delay(now()->addDays($toDisk['retention']))->onConnection($toDisk['connection'] ?? null)->onQueue($toDisk['queue'] ?? null);
            }
        }

        return $res;
    }

    public static function deleteFromDisk($path, $fromDisk)
    {
        $isExist = self::checkExistance($fromDisk, $path);
        if ($isExist){
            return Storage::build($fromDisk)->delete($path);
        }
        return false;
    }

    public function url($path)
    {
        if ($this->url) {
            return $this->url.$path;
        }
        
        foreach ($this->readDisks as $disk) {
            if ($this->checkExistance($disk,$path)) {
                return $this->diskP($disk)->url($path);
            }
        }
        return null;
    }

    private static function checkExistance($disk, $path){
        try {
            return Storage::build($disk)->exists($path);
        } catch (\Throwable $exception) {
            Log::error("Unable to check file existence on ".$disk);
            return false;
        }
    }

    // Implement all required methods from the Filesystem interface
    public function put($path, $contents, $options = [])
    {
        foreach ($this->writeDisks as $index => $disk) {
            $res = $this->diskP($disk)->put($path, $contents, $options);
            if ($res && $this->checkExistance($disk,$path)) {
                $this->sync($path, $disk, $index);
                return $res;
            }
        }

        return false;
    }

    public function path($path)
    {
        foreach ($this->readDisks as $disk) {
            if ($this->checkExistance($disk,$path)) {
                return $this->diskP($disk)->path($path);
            }
        }
        return null;
    }

    public function putFile($path, $file = null, $options = [])
    {
        foreach ($this->writeDisks as $index => $disk) {
            $res = $this->diskP($disk)->putFile($path, $file, $options);
            if ($res && $this->checkExistance($disk,$path)) {
                $this->sync($res, $disk, $index);
                return $res;
            }
        }

        return false;
    }

    public function putFileAs($path, $file, $name = null, $options = [])
    {
        foreach ($this->writeDisks as $index => $disk) {
            $res = $this->diskP($disk)->putFileAs($path, $file, $name, $options);
            if ($res && $this->checkExistance($disk,$path)) {
                $this->sync($res, $disk, $index);
                return $res;
            }
        }

        return false;
    }

    public function exists($path)
    {
        foreach ($this->readDisks as $disk) {
            if ($this->checkExistance($disk,$path)) {
                return true;
            }
        }

        return false;
    }

    public function get($path)
    {
        foreach ($this->readDisks as $disk) {
            if ($this->checkExistance($disk,$path)) {
                return $this->diskP($disk)->get($path);
            }
        }
        return null;
    }

    public function readStream($path)
    {
        foreach ($this->readDisks as $disk) {
            if ($this->checkExistance($disk,$path)) {
                return $this->diskP($disk)->readStream($path);
            }
        }
        return null;
    }

    public function writeStream($path, $resource, array $options = [])
    {
        foreach ($this->writeDisks as $index => $disk) {
            $res = $this->diskP($disk)->writeStream($path, $resource, $options);
            if ($res && $this->checkExistance($disk,$path)) {
                $this->sync($path, $disk, $index);
                return $res;
            }
        }

        return false;
    }

    public function copy($from, $to)
    {
        foreach ($this->writeDisks as $index => $disk) {
            $res = $this->diskP($disk)->copy($from, $to);
            if ($res && $this->checkExistance($disk,$to)) {
                $this->sync($to, $disk, $index);
                return $res;
            }
        }

        return false;
    }

    public function move($from, $to)
    {
        foreach ($this->writeDisks as $disk) {
            ActionSyncJob::dispatch(ActionSyncJob::MOVE, $disk, $from, $to)->onConnection($this->connection)->onQueue($this->queue);
        }
        
        return true;
    }

    public function append($path, $data)
    {
        foreach ($this->writeDisks as $disk) {
            ActionSyncJob::dispatch(ActionSyncJob::APPEND, $disk, $path, $data)->onConnection($this->connection)->onQueue($this->queue);
        }
        
        return true;
    }

    public function prepend($path, $data)
    {
        foreach ($this->writeDisks as $disk) {
            ActionSyncJob::dispatch(ActionSyncJob::PREPEND, $disk, $path, $data)->onConnection($this->connection)->onQueue($this->queue);
        }
        
        return true;
    }

    public function setVisibility($path, $visibility)
    {
        foreach ($this->writeDisks as $disk) {
            ActionSyncJob::dispatch(ActionSyncJob::SET_VISIBILITY, $disk, $path, $visibility)->onConnection($this->connection)->onQueue($this->queue);
        }
        
        return true;
    }
    
    public function makeDirectory($path)
    {
        foreach ($this->writeDisks as $disk) {
            ActionSyncJob::dispatch(ActionSyncJob::MAKE_DIRECTORY, $disk, $path)->onConnection($this->connection)->onQueue($this->queue);
        }

        return true;
    }

    public function deleteDirectory($directory)
    {
        foreach ($this->writeDisks as $disk) {
            ActionSyncJob::dispatch(ActionSyncJob::DELETE_DIRECTORY, $disk, $directory)->onConnection($this->connection)->onQueue($this->queue);
        }

        return true;
    }

    public function allDirectories($directory = null)
    {
        return $this->diskP($this->readDisks[0])->allDirectories($directory);
    }

    public function allFiles($directory = null)
    {
        return $this->diskP($this->readDisks[0])->allFiles($directory);
    }

    public function files($directory = null, $recursive = false)
    {
        return $this->diskP($this->readDisks[0])->files($directory, $recursive);
    }

    public function getVisibility($path)
    {
        foreach ($this->readDisks as $disk) {
            if ($this->checkExistance($disk,$path)) {
                return $this->diskP($disk)->getVisibility($path);
            }
        }
        return '';
    }

    public function lastModified($path)
    {
        foreach ($this->readDisks as $disk) {
            if ($this->checkExistance($disk,$path)) {
                return $this->diskP($disk)->lastModified($path);
            }
        }
        return 0;
    }

    public function size($path)
    {
        foreach ($this->readDisks as $disk) {
            if ($this->checkExistance($disk,$path)) {
                return $this->diskP($disk)->size($path);
            }
        }
        return 0;
    }

    public function directories($directory = null, $recursive = false)
    {
        return $this->diskP($this->readDisks[0] ?? '')->directories($directory, $recursive);
    }

    public function delete($paths)
    {        
        foreach ($this->writeDisks as $disk) {
            DeleteFileJob::dispatch($paths, $disk)->onConnection($this->connection)->onQueue($this->queue);
        }
        return true;
    }
}

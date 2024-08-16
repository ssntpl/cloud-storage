<?php

// declare(strict_types=1);

namespace Ssntpl\FlySystemCloud\Cloud;

use Illuminate\Contracts\Filesystem\Filesystem;
use Storage;

class CloudAdapter implements Filesystem
{
    protected $cacheDisk;
    protected $remoteDisks;

    protected $cacheTime;

    public function __construct($cacheTime, $cacheDisk, array $remoteDisks)
    {
        $this->cacheTime = $cacheTime;
        $this->cacheDisk = $cacheDisk;
        $this->remoteDisks = $remoteDisks;
    }

    private function diskP($disk)
    {
        return Storage::disk($disk);
    }

    private function setInCacheDisk($path, $deleteCache = false){
        if (! $this->diskP($this->cacheDisk)->exists($path))
        {
            foreach($this->remoteDisks as $remoteDisk){
                if ($this->diskP($remoteDisk)->exists($path)){
                    $this->diskP($this->cacheDisk)->writeStream($path,$this->diskP($remoteDisk)->readStream($path));
                    \Ssntpl\FlySystemCloud\Jobs\DeleteFileJob::dispatch($path)->delay(now()->addMinutes($this->cacheTime));
                    return true;
                }
            }
            return false;
        }
        elseif ($deleteCache)
        {
            \Ssntpl\FlySystemCloud\Jobs\DeleteFileJob::dispatch($path)->delay(now()->addMinutes($this->cacheTime));
        } 
        return true;
    }

    public function sync($path)
    {
        if ($this->setInCacheDisk($path,true)) 
        {
            foreach($this->remoteDisks as $remoteDisk){
                if (! $this->diskP($remoteDisk)->exists($path)){
                    \Ssntpl\FlySystemCloud\Jobs\SyncIndividualFileJob::dispatch($path,$remoteDisk);
                }
            }
            return true;
        }
        return false;
    }

    public function syncToDisk($path, $toDisk)
    {
        if ($this->setInCacheDisk($path))
        {
            $this->diskP($toDisk)->writeStream($path,$this->diskP($this->cacheDisk)->readStream($path));        
        }
    }

    public function deleteFromCache($path){
        if ($this->diskP($this->cacheDisk)->exists($path))
        {
            foreach($this->remoteDisks as $remoteDisk){
                if ($this->diskP($remoteDisk)->exists($path)){
                    $this->diskP($this->cacheDisk)->delete($path);
                    return true;
                }
            }
            return new \Exception('File not deleted from cache');
        }
    }

    public function url($path)
    {
        if ($this->setInCacheDisk($path)) 
        {
            return $this->diskP($this->cacheDisk)->url($path);
        }
    }

    // Implement all required methods from the Filesystem interface
    public function put($path, $contents, $options = [])
    {
        $res = $this->diskP($this->cacheDisk)->put($path, $contents, $options); 
        if ($this->remoteDisks && $res)
        {
            \Ssntpl\FlySystemCloud\Jobs\SyncFileJob::dispatch($path); 
        }
        return $res;     
    }

    public function delete($paths)
    {
        $this->diskP($this->cacheDisk)->delete($paths);
        foreach($this->remoteDisks as $remoteDisk){
            $this->diskP($remoteDisk)->delete($paths);
        }
        if (is_array($paths))
        {
            foreach($paths as $path)
            {
                \DB::table('jobs')->where('payload', 'like', '%'.$path.'%')->delete();
            }
        }
        else
        {
            \DB::table('jobs')->where('payload', 'like', '%'.$paths.'%')->delete();
        }
        return true;
    }

    public function path($path)
    {
        if ($this->setInCacheDisk($path)) 
        {
            return $this->diskP($this->cacheDisk)->path($path);
        }
    }

    public function putFile($path, $file = null, $options = [])
    {
        $res = $this->diskP($this->cacheDisk)->putFile($path, $file, $options); 
        if ($this->remoteDisks && $res)
        {
            \Ssntpl\FlySystemCloud\Jobs\SyncFileJob::dispatch($path); 
        }
        return $res;
    }

    public function putFileAs($path, $file, $name = null, $options = [])
    {
        $res = $this->diskP($this->cacheDisk)->putFileAs($path, $file, $name, $options); 
        if ($this->remoteDisks && $res)
        {
            \Ssntpl\FlySystemCloud\Jobs\SyncFileJob::dispatch($path); 
        }
        return $res;
    }

    public function exists($path)
    {
        if ($this->setInCacheDisk($path)) 
        {
            return $this->diskP($this->cacheDisk)->exists($path);
        }
    }

    public function get($path)
    {
        if ($this->setInCacheDisk($path)) 
        {
            return $this->diskP($this->cacheDisk)->get($path);            
        }
    }

    public function readStream($path)
    {
        if ($this->setInCacheDisk($path)) 
        {
            return $this->diskP($this->cacheDisk)->readStream($path);           
        }
    }
    
    public function copy($from, $to)
    {
        if ($this->setInCacheDisk($from)) 
        {
            $res = $this->diskP($this->cacheDisk)->copy($from, $to);
            if ($this->remoteDisks && $res)
            {
                \Ssntpl\FlySystemCloud\Jobs\SyncFileJob::dispatch($to); 
                return $res;
            }
        }
        return false;
    }

    public function move($from, $to)
    {
        foreach($this->remoteDisks as $remoteDisks){
            $remoteDisks->move($from, $to);
        }
        return true;
    }

    public function append($path, $data)
    {
        foreach($this->remoteDisks as $remoteDisks){
            $remoteDisks->append($path, $data);
        }
        return true;
    }

    public function makeDirectory($path)
    {
        foreach($this->remoteDisks as $remoteDisks){
            $remoteDisks->makeDirectory($path);
        }
        return true;
    }

    public function prepend($path, $data)
    {
        foreach($this->remoteDisks as $remoteDisks){
            $remoteDisks->prepend($path, $data);
        }
        return true;
    }

    public function setVisibility($path, $visibility)
    {
        foreach($this->remoteDisks as $remoteDisks){
            $remoteDisks->setVisibility($path, $visibility);
        }
        return true;
    }

    public function deleteDirectory($directory)
    {
        foreach($this->remoteDisks as $remoteDisks){
            $remoteDisks->deleteDirectory($directory);
        }
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
        return $this->diskP($this->remoteDisks[0])->getVisibility($path);
    }

    public function lastModified($path)
    {
        return $this->diskP($this->remoteDisks[0])->lastModified($path);
    }

    public function size($path)
    {
        return $this->diskP($this->remoteDisks[0])->size($path);
    }

    public function directories($directory = null, $recursive = false)
    {
        return $this->diskP($this->remoteDisks[0])->directories($directory, $recursive);
    }

    public function writeStream($path, $resource, array $options = [])
    {
        //logic is left for all disks
        return $this->cacheDisk->writeStream($path, $resource, $options);
    }
}

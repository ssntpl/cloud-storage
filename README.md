# Cloud Driver for Laravel Storage

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ssntpl/cloud-storage.svg?style=flat-square)](https://packagist.org/packages/ssntpl/cloud-storage)
[![Total Downloads](https://img.shields.io/packagist/dt/ssntpl/cloud-storage.svg?style=flat-square)](https://packagist.org/packages/ssntpl/cloud-storage)

A powerful Laravel storage driver that enables seamless synchronization of files across multiple disks, with an integrated cache disk for optimized performance.

## Features

- **Multi-Disk Support:** Define multiple remote disks to store your files.
- **Cache Disk:** Files are first uploaded to a designated cache disk for quick access.
- **Asynchronous Sync:** Files are synced to all remote disks asynchronously, ensuring high availability.
- **Optimized Access:** Files are accessed from the cache disk first; if not found, they are retrieved from the remote disks in the defined order.

## Installation

Install the package via Composer:

```bash
composer require ssntpl/cloud-storage
```

## Usage

1. **Configuration:** In your Laravel application's `config/filesystems.php`, define your disks, including the cache disk.

   ```php
   'disks' => [
       'cloud_disk' => [
           'driver' => 'cloud',
           'cache_disk' => 'local',
           'read_priority_cache_disk' => 1, // set cache disk priority when get disk url for given path
           'remote_disks' => [
                'remote_disk_1', 
                'remote_disk_2', 
                's3', 
                'minio',
                // Add more remote disks as needed...
           ],
           'read_only_disks' => [
                'read_disk_1',
                'read_disk_2',
            ], // used for read files only
           'cache_time' => 24, // Time (in hours) to cache files on the cache disk
       ],

       // Define other disks (including cache disk, and remote disks used in the cloud disk above)
   ],
   ```

2. **Upload Files:**
   When uploading files using this driver, they will first be stored on the cache disk and then asynchronously synced to all defined remote disks.

   ```php
   Storage::disk('cloud_disk')->put('path/to/file.jpg', $fileContents);
   ```

3. **Access Files:**
   The driver will check the cache disk first; if the file isn't found there, it will sequentially check each remote disk as configured.

   ```php
   $file = Storage::disk('cloud_disk')->get('path/to/file.jpg');
   ```

## Future Enhancements

- **Improved Sync Strategies:** Additional options for sync strategies, such as prioritizing certain disks.
- **Advanced Caching Mechanisms:** Enhance caching strategies to improve performance in specific use cases.
- **Monitoring and Alerts:** Integrate monitoring for sync failures and performance metrics.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for detailed information on the latest changes.

## Security Vulnerabilities

If you discover any security-related issues, please email support@ssntpl.com instead of using the issue tracker.

## Credits

- [Abhishek Sharma](https://github.com/Abhishek5Sharma)
- [Sambhav Aggarwal](https://github.com/sambhav-aggarwal)
- [All Contributors](../../contributors)

## License

This package is licensed under the MIT License. See the [License File](LICENSE.md) for more details.

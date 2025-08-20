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
      'local' => [
         'driver' => 'local',
         'root' => storage_path('app/private'),
         'serve' => true,
         'throw' => false,
         'report' => false,
         'write_enabled' => true,
         'write_priority' => 2,
         'read_priority' => 1,
         'retention' => 1,
      ],
      'cloud_disk' => [
         'driver' => 'cloud',
         'disks' => [
            'local', // just write here disk name and other configuration variable can be set on this disk configuration array as mention above.
            [
               'driver' => 'local',
               'root' => storage_path('app/public'),
               'url' => env('APP_URL').'/storage',
               'visibility' => 'public',
               'throw' => false,
               'report' => false, 
               'write_enabled' => true, // false means read only opertions should be done. default is true
               'write_priority' => 1, // 0 means least priority. default is 0
               'read_priority' => 2, // 0 means least priority. default is 0
               'retention' => 0, // in days, 0 means no retention. default is 0. if retention is greater than 0, make sure your queue connection sould not be sync.
            ],
         ],
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


# Associate files with Eloquent models

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ssntpl/laravel-files.svg?style=flat-square)](https://packagist.org/packages/ssntpl/laravel-files)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/ssntpl/laravel-files/run-tests?label=tests)](https://github.com/ssntpl/laravel-files/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/ssntpl/laravel-files/Check%20&%20fix%20styling?label=code%20style)](https://github.com/ssntpl/laravel-files/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ssntpl/laravel-files.svg?style=flat-square)](https://packagist.org/packages/ssntpl/laravel-files)

This is a simple package to associate files with your eloquent model in laravel. 

## Installation

You can install the package via composer:

```bash
composer require ssntpl/flysystem-cloud
```

## Testing

```bash
composer test
```

## TODO
- [ ] Declare the `Ssntpl\LaravelFiles\Contracts\File` contract
- [ ] Add option to define default disk for every model
- [ ] Add path() method that returns the full path of the file
- [ ] Make File model sub-class of `Illuminate\Http\File` and see if all the methods work fine.
- [ ] See if destroy/delete method can be modified in trait to handle the file objects

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.


## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Sam](https://github.com/ssntpl)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

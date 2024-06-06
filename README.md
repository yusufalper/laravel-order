# Package Summary 
This Laravel package provides a handy trait called HasOrder designed to simplify 
the management of ordered records in your Eloquent models. By applying this trait 
to your models, you introduce an 'order' attribute, allowing you to effortlessly 
handle the ordering of records within a given model.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yusufalper/laravel-order.svg?style=flat-square)](https://packagist.org/packages/yusufalper/laravel-order)
[![Total Downloads](https://img.shields.io/packagist/dt/yusufalper/laravel-order.svg?style=flat-square)](https://packagist.org/packages/yusufalper/laravel-order)

## Installation

You can install the package via composer:

```bash
composer require yusufalper/laravel-order
```

## Usage
1. Apply Trait:
Simply apply the HasOrder trait to your Eloquent model.
Ensure that your model's migration includes a column (integer and nullable) specified in model as '$orderAttrName' (see example in below)
to support the ordering functionality.

2. Optional Configuration:
Define $orderUnificationAttributes (public array) within your model 
to fine-tune the ordering behavior according to your application's specific requirements.
For example if you add 'user_id' attribute to $orderUnificationAttributes, then your
ordering will be user_id based ordering.

3. Automatic Handling:
The package takes care of automatic order adjustments during record creation, 
ensuring a seamless and organized ordering process.

4. Effortless Update and Delete:
The HasOrder trait also seamlessly handles updates and deletes, maintaining the correct order of records based on your defined criteria.

## Example Usage
```php
use Illuminate\Database\Eloquent\Model;
use Alper\LaravelOrder\Traits\HasOrder;

class CompanyBranch extends Model
{
    use HasOrder;
    
    protected $fillable = [
        'order'
        'company_id'
    ];

    public string $orderAttrName = 'order';
    
    public array $orderUnificationAttributes = [
        'company_id'
    ];
}

```

If you have multiple traits that each of which has a boot method, 
Then you should use like this:
```php
use Illuminate\Database\Eloquent\Model;
use Alper\LaravelOrder\Traits\HasOrder;

class CompanyBranch extends Model
{
    use HasOrder {
        HasOrder::boot as bootHasOrderTrait;
    }
    use OtherTrait {
        OtherTrait::boot as bootOtherTraitTrait;
    }

    public static function boot(): void
    {
        static::bootHasOrderTrait();
        static::bootOtherTraitTrait();
    }
    
    protected $fillable = [
        'order'
        'company_id'
    ];
    
    public string $orderAttrName = 'order';

    public array $orderUnificationAttributes = [
        'company_id'
    ];
}

```

By integrating the HasOrder trait into your Laravel models, 
you streamline the process of managing ordered records, 
offering enhanced control and flexibility.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Alper](https://github.com/yusufalper)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

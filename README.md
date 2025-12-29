# Enable value objects to normalize and denormalize themselves

A Symfony bundle to enable value objects and DTOs to normalize and denormalize themselves through implementing simple interfaces that normalize to scalar values and denormalize themselves from scalar values (`string`, `int`, `float`, `bool` and `array`). Adding this kind of logic to the classes themselves might be considered a bad practice, but depending on the use case it will actually be better due to the fact that the data structure and the normalization need to be changed together.

The name implies that the value objects and DTOs are self-aware in the sense that they know how to normalize and denormalize themselves and that they are self-aware enough to do so 🙂 

As it's a central part of an application, it's tested thoroughly (including mutation testing).

[![Latest Stable Version](https://img.shields.io/badge/stable-1.2.0-blue)](https://packagist.org/packages/digital-craftsman/self-aware-normalizers)
[![PHP Version Require](https://img.shields.io/badge/php-8.4|8.5-5b5d95)](https://packagist.org/packages/digital-craftsman/self-aware-normalizers)
[![codecov](https://codecov.io/gh/digital-craftsman-de/self-aware-normalizers/branch/main/graph/badge.svg?token=BL0JKZYLBG)](https://codecov.io/gh/digital-craftsman-de/self-aware-normalizers)
![Packagist Downloads](https://img.shields.io/packagist/dt/digital-craftsman/self-aware-normalizers)
![Packagist License](https://img.shields.io/packagist/l/digital-craftsman/self-aware-normalizers)

## Installation and configuration

Install package through composer:

```shell
composer require digital-craftsman/self-aware-normalizers
```

Optionally, you can add a `self-aware-normalizers.php` file to your `config/packages` directory to configure the bundle to automatically register all custom doctrine types in one or multiple directories:

```php
<?php

declare(strict_types=1);

use Symfony\Config\SelfAwareNormalizersConfig;

return static function (SelfAwareNormalizersConfig $selfAwareNormalizersConfig) {
    $selfAwareNormalizersConfig->doctrineTypeDirectories([
        '%kernel.project_dir%/src/Doctrine',
    ]);
};
```

## Usage

### Normalizers

To make the normalization process easier, there are the following normalizers included:

- `StringNormalizableNormalizer`
- `IntNormalizableNormalizer`
- `FloatNormalizableNormalizer`
- `BoolNormalizableNormalizer`
- `ArrayNormalizableNormalizer`

Additionally, there is an interface for each of the normalizers. Every class that implements one of the interfaces, will be automatically normalized to the respected type. This means putting the logic of how serialization of a class works within the class. That's not really seen as a good practice. In my experience, the data structure and the normalization need to be changed together. So, I like it better to have both in one place. I've used this approach in multiple large scale projects for years and haven't had a single issue with it yet. But your mileage may vary.

With this you can have nested denormalization that looks like this:

```php
/**
 * @psalm-type NormalizedSearch = array{
 *     searchTerm: string,
 *     limit: int,
 * }
 */
final readonly class Search implements ArrayNormalizable
{
    public function __construct(
        public SearchTerm $searchTerm,
        public Limit $limit,
    ) {
    }
    
    /**
    * @param NormalizedSearch $data
    */
    public static function denormalize(array $data): self
    {
        return new self(
            searchTerm: SearchTerm::denormalize($data['searchTerm']),
            limit: Limit::denormalize($data['limit']),
        );
    }
    
    /**
    * @return NormalizedSearch
    */
    public function normalize(): array
    {
        return [
            'searchTerm' => $this->searchTerm->normalize(),
            'limit' => $this->limit->normalize(),
        ];
    }
}
```

#### Denormalized for null handling

When handling `null` you can use the `Nullable*Denormalizable` interfaces with the related `Nullable*DenormalizableTrait` to handle switches between `null` and the class like the following:

```php
/**
 * @psalm-type NormalizedSearchWithOptionalLimit = array{
 *     searchTerm: string,
 *     limit: int | null,
 * }
 */
final readonly class SearchWithOptionalLimit implements ArrayNormalizable
{
    public function __construct(
        public SearchTerm $searchTerm,
        public ?Limit $limit,
    ) {
    }
    
    /**
    * @param NormalizedSearchWithOptionalLimit $data
    */
    public static function denormalize(array $data): self
    {
        return new self(
            searchTerm: SearchTerm::denormalize($data['searchTerm']),
            limit: Limit::denormalizeWhenNotNull($data['limit']),
        );
    }
    
    /**
    * @return NormalizedSearchWithOptionalLimit
    */
    public function normalize(): array
    {
        return [
            'searchTerm' => $this->searchTerm->normalize(),
            'limit' => $this->limit?->normalize(),
        ];
    }
}
```

Internally the value object simply has to implement the relevant interface and use the related trait like the following:

```php
final readonly class Limit implements IntNormalizable, NullableIntDenormalizable
{
    use NullableIntDenormalizableTrait;

    public function __construct(
        public int $limit,
        ...
```

### Doctrine types

When using the normalizers, you can also use the same logic for doctrine types. Simply create a new doctrine type extending of one of the following types and register them:

- `StringNormalizableType`
- `StringEnumType`
- `IntNormalizableType`
- `FloatNormalizableType`
- `BoolNormalizableType`
- `ArrayNormalizableType`

As an added bonus, this makes sure, that the structure is always the same no matter if you're using Doctrine to read from the data or a normalizer.

## Additional documentation

- [Changelog](./CHANGELOG.md)
- [Upgrade guide](./UPGRADE.md)

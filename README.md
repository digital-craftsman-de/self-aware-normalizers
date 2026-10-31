# Self-aware normalizers

Value objects and DTOs that know how to normalize and denormalize themselves. Implement one small interface and the class works with the Symfony serializer and as a Doctrine type – without writing a single custom normalizer or Doctrine type.

[![Latest Stable Version](https://img.shields.io/badge/stable-1.4.0-blue)](https://packagist.org/packages/digital-craftsman/self-aware-normalizers)
[![PHP Version Require](https://img.shields.io/badge/php-8.5|8.6-5b5d95)](https://packagist.org/packages/digital-craftsman/self-aware-normalizers)
[![codecov](https://codecov.io/gh/digital-craftsman-de/self-aware-normalizers/branch/main/graph/badge.svg?token=BL0JKZYLBG)](https://codecov.io/gh/digital-craftsman-de/self-aware-normalizers)
![Packagist Downloads](https://img.shields.io/packagist/dt/digital-craftsman/self-aware-normalizers)
![Packagist License](https://img.shields.io/packagist/l/digital-craftsman/self-aware-normalizers)

## Why?

- **One place for structure and format.** A class and its normalized form always change together. Keeping both in the same class means you can't forget one of them.
- **No boilerplate.** No custom normalizer per class and no custom Doctrine type per value object. The bundle registers everything automatically.
- **Same format everywhere.** The serializer and Doctrine use the same `normalize()` and `denormalize()` methods, so your API and your database always store the same structure.
- **Explicit and type safe.** Normalization is plain PHP code instead of reflection magic. Static analysis tools like Psalm and PHPStan understand every step.
- **Reliable.** It's a central part of an application, so it's tested thoroughly, including mutation testing with 100% MSI.

## Installation

Requires PHP 8.5 or 8.6, Symfony 7.4 or 8 and Doctrine DBAL 4.

```shell
composer require digital-craftsman/self-aware-normalizers
```

The normalizers are registered automatically. The configuration is only needed for the [Doctrine types](#doctrine-types).

### Configuration

All options are optional. Add a `config/packages/self-aware-normalizers.php` when you need them:

```php
<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

return App::config([
    'self_aware_normalizers' => [
        // Classes and enums implementing one of the interfaces are registered as Doctrine types.
        'implementation_directories' => [
            '%kernel.project_dir%/src/ValueObject',
        ],
        // Custom Doctrine types with a static getTypeName() method are registered automatically.
        'doctrine_type_directories' => [
            '%kernel.project_dir%/src/Doctrine',
        ],
    ],
]);
```

The directories are scanned recursively when the container is built. In the `dev` environment, the container is rebuilt automatically when files in these directories are added, changed or removed.

## Usage

### Value objects

Implement the interface that matches the normalized type:

| Interface | Normalizes to |
|---|---|
| `StringNormalizable` | `string` |
| `IntNormalizable` | `int` |
| `FloatNormalizable` | `float` |
| `BoolNormalizable` | `bool` |
| `ArrayNormalizable` | `array` |

```php
final readonly class SearchTerm implements StringNormalizable
{
    public function __construct(
        public string $searchTerm,
    ) {
    }

    public static function denormalize(string $data): self
    {
        return new self($data);
    }

    public function normalize(): string
    {
        return $this->searchTerm;
    }
}
```

That's it. The Symfony serializer now handles `SearchTerm` everywhere it's used:

```php
$searchTerm = $serializer->denormalize('Symfony', SearchTerm::class);
$normalized = $serializer->normalize($searchTerm); // 'Symfony'
```

### Nested objects

Objects containing other objects simply call `normalize()` and `denormalize()` of their properties:

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

### Enums

Backed enums only need the interface and the matching trait:

```php
enum UserRole: string implements StringNormalizable
{
    use StringNormalizableEnumTrait;

    case ROLE_USER = 'ROLE_USER';
    case ROLE_ADMIN = 'ROLE_ADMIN';
}
```

Use `IntNormalizableEnumTrait` for enums backed by `int`.

### Nullable values

For optional values, add the `Nullable*Denormalizable` interface and the matching trait. You then get a `denormalizeWhenNotNull()` method that returns `null` for `null`:

```php
final readonly class Limit implements IntNormalizable, NullableIntDenormalizable
{
    use NullableIntDenormalizableTrait;

    // ...
}
```

```php
return new self(
    searchTerm: SearchTerm::denormalize($data['searchTerm']),
    limit: Limit::denormalizeWhenNotNull($data['limit']),
);
```

Interfaces and traits exist for all types: `NullableStringDenormalizable`, `NullableIntDenormalizable`, `NullableFloatDenormalizable`, `NullableBoolDenormalizable` and `NullableArrayDenormalizable`.

## Doctrine types

### Automatic Doctrine types

Add the directories containing your value objects to `implementation_directories` (see [Configuration](#configuration)). Every class and enum in these directories that implements one of the interfaces is registered as a Doctrine type. Use the class name as the column type:

```php
#[ORM\Entity]
class User
{
    #[ORM\Column(name: 'name', type: UserName::class)]
    public UserName $name;
}
```

Types that are already registered under the same name (for example in your `doctrine.yaml`) take precedence and aren't overwritten.

In queries, you can use plain scalar values instead of value objects. Values of type `string`, `int`, `float` and `bool` are passed through to the database as they are:

```php
$queryBuilder
    ->where('user.name = :name')
    ->setParameter('name', 'Jane', UserName::class);
```

### Column types

| Interface | Default column type |
|---|---|
| `StringNormalizable` | `VARCHAR(255)` |
| `IntNormalizable` | `INTEGER` |
| `FloatNormalizable` | `DOUBLE PRECISION` |
| `BoolNormalizable` | `BOOLEAN` |
| `ArrayNormalizable` | `JSONB` on PostgreSQL, `JSON` on other platforms |

The exact type depends on your database platform. Implement one of these interfaces in your class to change it:

| Interface | Effect |
|---|---|
| `StringNormalizableTypeWithMaxLength` | Sets the max length, e.g. `VARCHAR(50)`. |
| `StringNormalizableTypeAsTypeText` | Uses `TEXT` instead of `VARCHAR`. |
| `NormalizableTypeWithSQLDeclaration` | Defines a completely custom SQL declaration. |

### Custom Doctrine types

Have your own Doctrine types? Add their directories as well and they are registered automatically. They only need a `public static function getTypeName(): string` method.

```php
return App::config([
    'self_aware_normalizers' => [
        'doctrine_type_directories' => [
            '%kernel.project_dir%/src/Doctrine',
        ],
    ],
]);
```

The abstract base types like `StringNormalizableType` are deprecated and will be removed in 2.0.0. Switch to the automatic Doctrine types as described in the [upgrade guide](./UPGRADE.md#from-12-to-130).

## Isn't normalization in the class a bad practice?

It's often seen that way. In practice, the data structure and its normalized form have to be changed together anyway, so keeping them in one place makes changes easier and safer. This approach has been used in multiple large-scale projects for years without issues. Your mileage may vary.

## Additional documentation

- [Changelog](./CHANGELOG.md)
- [Upgrade guide](./UPGRADE.md)

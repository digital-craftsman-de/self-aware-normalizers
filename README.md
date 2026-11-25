# Self-aware normalizers

Value objects and DTOs that know how to normalize and denormalize themselves. Implement one small interface and the class works with the Symfony serializer and as a Doctrine type – without writing a single custom normalizer or Doctrine type.

[![Latest Stable Version](https://img.shields.io/badge/stable-2.0.0-blue)](https://packagist.org/packages/buron-digital/self-aware-normalizers)
[![PHP Version Require](https://img.shields.io/badge/php-8.5|8.6-5b5d95)](https://packagist.org/packages/buron-digital/self-aware-normalizers)
[![codecov](https://codecov.io/gh/buron-digital/self-aware-normalizers/branch/main/graph/badge.svg?token=BL0JKZYLBG)](https://codecov.io/gh/buron-digital/self-aware-normalizers)
![Packagist Downloads](https://img.shields.io/packagist/dt/buron-digital/self-aware-normalizers)
![Packagist License](https://img.shields.io/packagist/l/buron-digital/self-aware-normalizers)

> [!NOTE]
> Up to version 1.5.1, the package was published as [`digital-craftsman/self-aware-normalizers`](https://packagist.org/packages/digital-craftsman/self-aware-normalizers) with the namespace `DigitalCraftsman\SelfAwareNormalizers`. See the [upgrade guide](./UPGRADE.md#renamed-package-and-namespace) for how to switch.
>
> Downloads under the old name: [![Packagist Downloads](https://img.shields.io/packagist/dt/digital-craftsman/self-aware-normalizers)](https://packagist.org/packages/digital-craftsman/self-aware-normalizers)

## Why?

- **One place for structure and format.** A class and its normalized form always change together. Keeping both in the same class means you can't forget one of them.
- **No boilerplate.** No custom normalizer per class and no custom Doctrine type per value object. The bundle registers everything automatically.
- **Same format everywhere.** The serializer and Doctrine use the same `normalize()` and `denormalize()` methods, so your API and your database always store the same structure.
- **Explicit and type safe.** Normalization is plain PHP code instead of reflection magic. Static analysis tools like Psalm and PHPStan understand every step.
- **Reliable.** It's a central part of an application, so it's tested thoroughly, including mutation testing.
- **Fast.** The optional [`KnownConstructionNormalizer`](#knownconstructionnormalizer) normalizes large object graphs of read models, DTOs, commands and queries about **8× faster** and denormalizes them about **12× faster** than the `PropertyNormalizer`, as it knows once per class how an object is constructed instead of analysing every object. The results stay the same.

## Installation

Requires PHP 8.5 or 8.6, Symfony 7.4 or 8 and Doctrine DBAL 4.

```shell
composer require buron-digital/self-aware-normalizers
```

The normalizers are registered automatically. The configuration is only needed for the [Doctrine types](#doctrine-types) and the [`KnownConstructionNormalizer`](#knownconstructionnormalizer).

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

## KnownConstructionNormalizer

An optional fast path in front of Symfony's `PropertyNormalizer` for plain constructor-promoted classes like read models, DTOs, commands, queries and events. It's disabled by default.

### Why?

The `KnownConstructionNormalizer` knows in advance how each object is constructed: containers through a constructor that mirrors their promoted public properties, self-aware value objects through their `denormalize()` and `normalize()`. That knowledge is precomputed once per class as a plan. The `PropertyNormalizer` analyses every object again instead: it selects the normalizer per value, resolves the type of every constructor parameter, loops over the attributes and evaluates the context.

**On large object graphs it's about 8× faster for the normalization and 12× faster for the denormalization**: 20 objects with 50 nested items each (scalars, nullable values, a `StringNormalizable` value object, a `BackedEnum`, `list<Item>`, `list<string>`) take 0.8 ms instead of 6.3 ms to normalize and 2.2 ms instead of 27.8 ms to denormalize (average of 30 runs) (PHP 8.5, opcache enabled, without Xdebug, type extractor cached in both cases). In a real application, the responses of endpoints with large read models got 11 % to 62 % faster end to end, as the database, the encoding and everything else of a request stay the same.

The result is exactly the one of the `PropertyNormalizer`:

- **Objects are still fully constructed.** Read models through their constructor, value objects through their `denormalize()` method. Validation in constructors runs as before.
- **Anything the plan doesn't handle exactly like the `PropertyNormalizer` falls back to it.** Data of the wrong type, a missing or `null` value for a non-nullable parameter, a failing nested object or a `TypeError` of a constructor make the object fall back to the `PropertyNormalizer`, which throws its own exception with its own message and path. Error responses stay the same.
- **Classes, contexts and formats the plan can't express aren't supported at all**, so the serializer uses the `PropertyNormalizer` for them. A class that misses the fast path only costs performance, never correctness.

### Guarantees

The `KnownConstructionNormalizer` mirrors internals of `symfony/serializer`, `symfony/type-info` and `symfony/property-info`. There is no version lock and no test your application has to write. Instead, anything it doesn't know leads to the `PropertyNormalizer` or fails the container build:

- **Context options are an allowlist.** The plan is only used when every option of the context and of the default context is one it was verified against: the ones it implements (`skip_null_values`, `preserve_empty_objects`), the ones of the `PropertyNormalizer` only with the value of its default behaviour (e.g. `allow_extra_attributes: true`), the internal ones of the serializer (e.g. `deserialization_path`) and the ones of other normalizers and encoders, which the plan hands on unchanged (e.g. `datetime_format`). Any other option, e.g. one of a later version of Symfony, leads to the `PropertyNormalizer`. Options of your own can be accepted through `accepted_context_keys`.
- **The constructor arguments of the fallback are an allowlist.** It must be exactly the `PropertyNormalizer` without serializer mapping, and every other argument must keep its default value. Anything else fails the container build.
- **The normalizers in front of it are checked.** The plan handles nested classes and value objects inline. So every normalizer the serializer asks before the `KnownConstructionNormalizer` is checked per class through its `getSupportedTypes()`, like the serializer selects it: a class one of them may handle is never handled inline. A normalizer that decides per call for every object (`'object' => false`) keeps every class from the plan, unless you declare it in `neutral_normalizers`.
- **The test suite of the bundle** compares the `KnownConstructionNormalizer` with the `PropertyNormalizer` for every kind of parameter with valid and invalid data, in a kernel with all built-in normalizers, and runs against the next version of Symfony in CI.

### Requirements for the fast path

Every class that reaches the `KnownConstructionNormalizer` gets a plan attempt. A class gets a plan when:

- it has a public constructor whose parameters are exactly its non-static properties, all promoted, public and without hooks (not inherited),
- every parameter has a type the plan can express: scalars, `mixed`, arrays and (nested) lists of scalars, self-aware value objects, other classes with a plan and (nested) lists of them, other classes, which are (de)normalized through the serializer, and union types (not intersection types),
- it doesn't reference itself (directly or through other classes),
- no normalizer in front of the `KnownConstructionNormalizer` may handle it.

**Union types** (native like `A|B` or `int|string`, or in collections like `list<A|B>` or `list<SomeInterface>`) are fine for the normalization: like the `PropertyNormalizer`, which never reads the declared type when normalizing, the plan handles every value by its runtime class (planned classes and value objects inline, everything else through the serializer). For the denormalization, the `PropertyNormalizer` tries the members of a union in order, and `symfony/serializer` 8.1 and 8.2 differ for the elements of collections (8.1 keeps them as they are, 8.2 builds them). The plan only mirrors unions of scalars (a value of the type of a member is kept as it is); a set value of a union with other members makes the object fall back to the `PropertyNormalizer`, which the profiler panel shows.

Classes with a mutable (not `readonly`) property are only denormalized through their plan. Interfaces, enums, abstract classes, internal classes, exceptions, anonymous classes and self-aware value objects never get a plan. Only the formats `null` and `json` are supported. As the fallback is built without class metadata factory, **serializer mapping is ignored** for all classes handled by it or by the `KnownConstructionNormalizer` (attributes like `#[Groups]`, `#[SerializedName]` or `#[Ignore]` and mapping files). The [profiler panel](#profiler-panel) lists the attributes it finds on the classes of a request.

### Setup

The fallback is a `PropertyNormalizer` without serializer mapping, registered by the application. The bundle gives the `KnownConstructionNormalizer` its type extractor and default context, so both always resolve the same types.

```yaml
# config/services.yaml
services:

  # The fallback of the KnownConstructionNormalizer. Without class metadata factory, name converter, class discriminator resolver and
  # object class resolver. Set them to null explicitly, an autowired service gets them injected otherwise.
  # Autoconfiguration may add a second tag without priority, which is ignored: like for the serializer, the first tag counts.
  serializer.normalizer.property_normalizer:
    class: Symfony\Component\Serializer\Normalizer\PropertyNormalizer
    tags: [ { name: 'serializer.normalizer', priority: -50 } ]
    arguments:
      $classMetadataFactory: null
      $nameConverter: null
      $propertyTypeExtractor: '@app.property_normalizer_type_extractor'
      $classDiscriminatorResolver: null

  app.property_normalizer_type_extractor:
    class: Symfony\Component\PropertyInfo\PropertyInfoExtractor
    arguments:
      $typeExtractors:
        - '@Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor'
        - '@property_info.reflection_extractor'

  Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor: ~

  # Enums must be handled before the PropertyNormalizer
  serializer.normalizer.backed_enum:
    class: Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer
    tags: [ { name: 'serializer.normalizer', priority: -40 } ]
```

```php
<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

return App::config([
    'self_aware_normalizers' => [
        'known_construction_normalizer' => [
            'enabled' => true,
            // Pool the plans are cached in across requests (see below). Optional.
            'plan_cache_pool' => 'cache.known_construction_normalizer_plans',
            // Context keys of your own that don't change the result of the PropertyNormalizer. Optional.
            'accepted_context_keys' => [
                'app_response_locale',
            ],
            // Normalizers in front of the KnownConstructionNormalizer that never handle its classes. Optional.
            'neutral_normalizers' => [
                'app.serializer.normalizer.money',
            ],
            // Defaults
            'fallback_normalizer' => 'serializer.normalizer.property_normalizer',
            'priority' => -45,
        ],
    ],
]);
```

| Option | Default | Description |
|---|---|---|
| `enabled` | `false` | Registers the `KnownConstructionNormalizer`. |
| `plan_cache_pool` | `null` | Service id of a PSR-6 pool. Without it (or when the service doesn't exist), plans are only kept per instance. |
| `accepted_context_keys` | `[]` | Further context keys the plan is used with. Only for options of your own that don't change the result of the `PropertyNormalizer`. Options the plan doesn't implement (e.g. `groups`) or only accepts with a specific value (e.g. `allow_extra_attributes`) can't be accepted. |
| `neutral_normalizers` | `[]` | Service ids of normalizers in front of the `KnownConstructionNormalizer` that never handle the classes it plans, although their `getSupportedTypes()` may claim them. Must be normalizers the serializer asks before the `KnownConstructionNormalizer`. |
| `fallback_normalizer` | `serializer.normalizer.property_normalizer` | Service id of the `PropertyNormalizer` to fall back to. |
| `priority` | `-45` | Priority in the serializer. Must be between the `BackedEnumNormalizer` and the fallback. |

### Cache of the plans

Building the plans takes time on every request without a cache. In production, cache them in a pool. Plans are cached under the build id of the container, so a cached plan never outlives the code it was built from. Don't cache them in `dev` and `test`, where a changed class must apply without clearing a cache, by defining the pool only in `prod` (the `KnownConstructionNormalizer` ignores a pool that doesn't exist):

```php
return App::config([
    'when@prod' => [
        'framework' => [
            'cache' => [
                'pools' => [
                    'cache.known_construction_normalizer_plans' => [
                        'adapter' => 'cache.system',
                    ],
                ],
            ],
        ],
    ],
]);
```

### Cache of the type extractor

The fallback `PropertyNormalizer` (and the `KnownConstructionNormalizer` while it builds a plan) resolve the types of the properties through the type extractor: the `PropertyInfoExtractor` parses the docblocks and the use statements of the source file. Symfony only memoizes the result per process. With PHP-FPM, every request starts with a fresh container, so every involved class and property is parsed again on every request (about 70 to 120 µs each in a real application, about 9 ms on a single authentication request). With the plans cached, the `KnownConstructionNormalizer` only needs it when a plan is built (the first request after a deployment), but it still pays off for every class and value that goes through the fallback.

In production, wrap the type extractor of the [setup](#setup) in a `PropertyInfoCacheExtractor` on a pool of its own. The bundle passes the type extractor of the fallback on to the plans, so both still see the same types:

```yaml
# config/services.yaml
when@prod:
  services:
    app.property_normalizer_type_extractor.cached:
      class: Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor
      decorates: app.property_normalizer_type_extractor
      autowire: false
      autoconfigure: false
      arguments:
        $propertyInfoExtractor: '@.inner'
        $cacheItemPool: '@cache.property_normalizer_types'

# config/packages/framework.yaml
when@prod:
  framework:
    cache:
      pools:
        cache.property_normalizer_types:
          adapter: cache.adapter.system
```

- **Use a pool of its own, not `cache.property_info`.** The `property_info.cache` of the framework writes the same keys for a longer chain of extractors (used by the `ObjectNormalizer`), so both would return each other's results.
- **Disable autoconfiguration** for the wrapper, otherwise it's tagged into the extractor chains of the framework's `property_info`.
- **Empty the pool on every deployment.** Unlike the plans, the types aren't cached under the build id of the container, and the pool is filled lazily. Without emptying it (e.g. a system cache directory that is rebuilt per deployment, or clearing the pool when the application starts), changed docblocks keep their old types.
- **Production only,** so changed docblocks apply right away in `dev` and `test`.

### Profiler panel

In debug mode with the Symfony profiler, the panel "Self-Aware Normalizers" (menu entry "Self-Aware Norm.", placed in the order of the bundles) shows how the `KnownConstructionNormalizer` handled the classes of a request. The toolbar shows how many objects took the fast path and how many missed it. The panel lists:

- the objects (de)normalized through a plan, through the fallback after the plan was tried, and not handled at all, and the value objects handled inline,
- every class that missed the fast path with the reason and a hint how to fix it: no plan (e.g. "property has hooks: $name"), a context option or format that isn't supported, a fallback while (de)normalizing (e.g. "Page::$count: int expected, string given") or another normalizer that claims it,
- the values the plans send through the serializer (and why),
- the plans built in the request (with the time), loaded from the pool or kept in memory,
- the serializer mapping that is ignored: the attributes of `Symfony\Component\Serializer\Attribute` (e.g. `#[Groups]`, `#[SerializedName]`, `#[Ignore]`) on the classes of the request, their properties and methods, which neither the `KnownConstructionNormalizer` nor the fallback reads (it's built without class metadata factory),
- the configuration: fallback, the normalizers of the serializer in their order and the accepted context options.

Found mapping marks the toolbar and the menu entry as a warning as well. Use it to find the classes that miss the fast path. Without debug mode or profiler, nothing is registered or recorded.

## Isn't normalization in the class a bad practice?

It's often seen that way. In practice, the data structure and its normalized form have to be changed together anyway, so keeping them in one place makes changes easier and safer. This approach has been used in multiple large-scale projects for years without issues. Your mileage may vary.

## Additional documentation

- [Changelog](./CHANGELOG.md)
- [Upgrade guide](./UPGRADE.md)

## Who's behind this

<a href="https://www.buron.digital"><img src="https://raw.githubusercontent.com/buron-digital/self-aware-normalizers/rename-namespace/.github/assets/buron-digital-banner.png" alt="Christian Kolb, Buron Digital – Building your digital products. Free initial consultation." width="100%"></a>

<p align="center"><sub><a href="https://www.buron.digital">www.buron.digital</a> · <a href="mailto:info@buron.digital">info@buron.digital</a></sub></p>

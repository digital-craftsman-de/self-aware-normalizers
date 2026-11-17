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
- **Reliable.** It's a central part of an application, so it's tested thoroughly, including mutation testing.
- **Fast.** The optional [plan normalizer](#plan-normalizer) (de)normalizes plain read models, DTOs, commands and queries without the per object analysis of the `PropertyNormalizer`, with exactly the same results.

## Installation

Requires PHP 8.5 or 8.6, Symfony 7.4 or 8 and Doctrine DBAL 4.

```shell
composer require digital-craftsman/self-aware-normalizers
```

The normalizers are registered automatically. The configuration is only needed for the [Doctrine types](#doctrine-types) and the [plan normalizer](#plan-normalizer).

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

## Plan normalizer

An optional fast path in front of Symfony's `PropertyNormalizer` for plain constructor-promoted classes like read models, DTOs, commands, queries and events. It's disabled by default.

### Why?

The `PropertyNormalizer` analyses every object again: it selects the normalizer per value, resolves the type of every constructor parameter, loops over the attributes and evaluates the context. For large read models, that's most of the (de)normalization time. The plan normalizer builds a plan per class once (per constructor parameter the kind of value and the class to build) and then only runs the constructors and the `normalize()` / `denormalize()` methods of the value objects. In a real application, the responses of endpoints with large read models got 30 % to 60 % faster.

The result is exactly the one of the `PropertyNormalizer`:

- **Objects are still fully constructed.** Read models through their constructor, value objects through their `denormalize()` method. Validation in constructors runs as before. Nothing is passed through as JSON.
- **Anything the plan doesn't handle exactly like the `PropertyNormalizer` falls back to it.** Data of the wrong type, a missing or `null` value for a non-nullable parameter, a failing nested object or a `TypeError` of a constructor make the object fall back to the `PropertyNormalizer`, which throws its own exception with its own message and path. Error responses stay the same.
- **Classes and contexts the plan can't express aren't supported at all**, so the serializer uses the `PropertyNormalizer` for them (see [Limitations](#limitations)).

### Requirements

The plan normalizer mirrors internals of `symfony/serializer`, `symfony/type-info` and `symfony/property-info` (e.g. `AbstractObjectNormalizer::denormalizeParameter()`, the `ArrayDenormalizer` and the context options). There is no version lock. Instead, anything it doesn't know leads to the `PropertyNormalizer` or fails the build, and your equivalence test checks the rest against the versions you have installed:

- **Context options are an allowlist.** The plan is only used when every option of the context and of the default context of the serializer is one it was verified against: the options it implements itself (`skip_null_values`, `preserve_empty_objects`), the options of the `PropertyNormalizer` only with the value that keeps its default behaviour (e.g. `allow_extra_attributes: true`, `enable_type_conversion: false`), the internal options of the serializer (e.g. `deserialization_path`, `cache_key`, `key_type` / `value_type`) and the options of other normalizers, encoders and decoders, which the plan hands on unchanged (e.g. `datetime_format`, `json_encode_options`). Any other option, e.g. one added by a later version of the serializer, makes the serializer use the `PropertyNormalizer`. Options of your own can be accepted through `accepted_context_keys`.
- **The constructor arguments of the fallback are an allowlist.** The fallback must be exactly the `PropertyNormalizer` (see [Setup](#setup)), and every other argument of its constructor must keep its default value. An argument added by a later version that is configured fails the container build.
- **The equivalence test of your application is required.** It compares the plan normalizer with the `PropertyNormalizer` for every planned class of your application with the installed versions (see [Equivalence test for your application](#equivalence-test-for-your-application)). Run it, together with the list command with `--fail-on-unplanned`, in your test suite, especially after updating Symfony. Don't use the plan normalizer without it.

The bundle itself runs its tests against the next version of Symfony in CI as well.

### Setup

The fallback is a `PropertyNormalizer` without serializer mapping, registered by the application. The plan normalizer is only equivalent to it when it's built without class metadata factory, name converter, class discriminator resolver and object class resolver, and with every other argument but the type extractor and the default context in its default value. The bundle validates that when the container is built (and fails with an explanation otherwise). It also gives the plan normalizer the type extractor and the default context of the fallback, so both always resolve the same types.

```yaml
# config/services.yaml
services:

  # The fallback of the plan normalizer. Without class metadata factory, name converter, class discriminator resolver and object
  # class resolver. Set them to null explicitly, an autowired service gets them injected otherwise.
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
        'plan_normalizer' => [
            'enabled' => true,
            // Classes the plan normalizer is responsible for: matched by name ...
            'supported_class_name_pattern' => '/^App\\\\(?:[^\\\\]+\\\\)*(?:ReadModel|DTO)\\\\/',
            // ... or by an interface or parent class
            'supported_parent_types' => [
                'DigitalCraftsman\CQSRouting\Command\Command',
                'DigitalCraftsman\CQSRouting\Query\Query',
            ],
            // Classes in the scope that fall back by design, with the reason (see the list command)
            'accepted_unplanned_classes' => [
                'App\ReadModel\TreeNode' => 'References itself',
            ],
            // Context keys of your own that don't change the result of the PropertyNormalizer. Optional.
            'accepted_context_keys' => [
                'app_response_locale',
            ],
            // Classes listed by the list command
            'source_directories' => [
                '%kernel.project_dir%/src',
            ],
            // Pool the plans are cached in across requests (see below). Optional.
            'plan_cache_pool' => 'cache.plan_normalizer_plans',
            // Defaults
            'fallback_normalizer' => 'serializer.normalizer.property_normalizer',
            'priority' => -45,
        ],
    ],
]);
```

| Option | Default | Description |
|---|---|---|
| `enabled` | `false` | Registers the plan normalizer. |
| `supported_class_name_pattern` | `null` | Regular expression matched against the fully qualified class name. |
| `supported_parent_types` | `[]` | Interfaces or parent classes. A class is in scope when it matches the pattern or extends / implements one of them. At least one of both is required. Exceptions are never in scope. |
| `accepted_unplanned_classes` | `[]` | Class => reason. Only used by the list command. |
| `accepted_context_keys` | `[]` | Further context keys the plan normalizer is used with (see [Requirements](#requirements)). Only for options of your own that don't change the result of the `PropertyNormalizer`, e.g. options of your own normalizers. Options the plan doesn't implement (e.g. `groups`) or only accepts with a specific value (e.g. `allow_extra_attributes`) can't be accepted. |
| `source_directories` | `[]` | Directories scanned by the list command (recursively). |
| `plan_cache_pool` | `null` | Service id of a PSR-6 pool. Without it (or when the service doesn't exist), plans are only kept per instance. |
| `fallback_normalizer` | `serializer.normalizer.property_normalizer` | Service id of the `PropertyNormalizer` to fall back to. |
| `priority` | `-45` | Priority in the serializer. Must be between the `BackedEnumNormalizer` and the fallback. |

The services are `DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\PlanNormalizer` and `DigitalCraftsman\SelfAwareNormalizers\Serializer\PlanNormalizer\PlanFactory` (private).

### Cache of the plans

Building the plans takes time on every request without a cache, which eats a large part of the gain on requests with many small classes. In production, cache them in a pool. Plans are cached under the build id of the container (`container.build_id`), so a cached plan never outlives the code it was built from. Don't cache them in `dev` and `test`, where a changed class must apply without clearing a cache, by defining the pool only in `prod` (the plan normalizer ignores a pool that doesn't exist):

```php
return App::config([
    'when@prod' => [
        'framework' => [
            'cache' => [
                'pools' => [
                    'cache.plan_normalizer_plans' => [
                        'adapter' => 'cache.system',
                    ],
                ],
            ],
        ],
    ],
]);
```

The type extractor of the fallback re-parses the docblocks of every class on every request as well. Wrapping it in a `PropertyInfoCacheExtractor` in production is worth it, too. It's taken over by the plan normalizer automatically.

### Limitations

A class gets a plan when it has a public constructor whose parameters are exactly its non-static properties, all promoted, public and without hooks, and every parameter has a type the plan can handle. Everything else is (de)normalized by the `PropertyNormalizer` as before:

- Interfaces, enums, abstract classes, internal classes and self-aware value objects (they never reach the normalizer as such).
- No constructor or a constructor that isn't public.
- Properties that aren't public, aren't promoted (e.g. set in the constructor body), have hooks or are inherited from a parent class.
- Constructor parameters without property, variadic or passed by reference, without type or with an object as default value.
- Union types (except nullable types) and intersection types.
- Collections that aren't arrays (e.g. `iterable` or a collection class) and unsupported types like `object` or object shapes.
- Classes referencing themselves (directly or through other classes), as invalid data deep down would fall back on every level.
- Classes with a mutable (not `readonly`) property are only denormalized through their plan, the normalization falls back.
- Contexts with options the plan doesn't implement (e.g. `groups`, `attributes`, `object_to_populate`, `callbacks`, `enable_max_depth`, `disable_type_enforcement`, `circular_reference_handler`, collecting denormalization errors, `allow_extra_attributes: false`) or doesn't know (see [Requirements](#requirements)) and the formats XML and CSV (which enable the type conversion) always go to the `PropertyNormalizer`.

As the fallback is built without class metadata factory, **serializer mapping is ignored** for all classes handled by it or by the plan normalizer: attributes like `#[Groups]`, `#[SerializedName]` or `#[Ignore]` and XML/YAML mapping files. The test case `SerializerMappingIsNotUsedTestCase` (see below) fails when they are used.

### List the classes that aren't planned

```shell
bin/console self-aware-normalizers:plan-normalizer:list-unplanned
```

Lists every class of the `source_directories` that is in scope but not (fully) on the fast path, with the reason, and the parameters of planned classes whose values are (de)normalized through the serializer. Options: `--only-relevant` hides the classes that never get a plan by design, `--format=json` prints the data as JSON, and `--fail-on-unplanned` fails when a class falls back that isn't one of the `accepted_unplanned_classes` or when an accepted class doesn't fall back anymore. Run it in your tests or CI with `--fail-on-unplanned`, so a new read model that falls back doesn't go unnoticed. Requires `symfony/console`.

### Equivalence test for your application

The bundle tests the plan normalizer with its own fixtures. Your classes are only covered by a test of your application: `PlanNormalizerEquivalenceTestCase` builds a sample of every planned class of your source (through the constructors, with generated values of the parameter types) and checks that the plan normalizer produces the same results as the `PropertyNormalizer` alone: when normalizing, when denormalizing (with and without JSON format, as is and after a JSON round trip), with cached plans and with invalid data (every parameter removed, `null`, of the wrong type, with wrong elements, ...), and that the serializer selects the plan normalizer for every planned class. It requires `phpunit/phpunit` 12 or 13 and runs in a `KernelTestCase` of your application.

```php
<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\ReadModel\ContentElementImage;
use App\ValueObject\ContentElementType;
use App\ValueObject\DateRange;
use App\ValueObject\Id;
use DigitalCraftsman\DateTimePrecision\Duration;
use DigitalCraftsman\DateTimePrecision\Moment;
use DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer\PlanNormalizerEquivalenceTestCase;
use DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer\SampleContext;
use DigitalCraftsman\SelfAwareNormalizers\Testing\PlanNormalizer\SampleProvider;

final class PlanNormalizerEquivalenceTest extends PlanNormalizerEquivalenceTestCase
{
    protected static function sourceDirectories(): array
    {
        return [
            dirname(__DIR__, 2).'/src',
        ];
    }

    // About the current number of planned classes, so classes silently taken off the plan fail the test
    protected static function minimumNumberOfPlannedClasses(): int
    {
        return 450;
    }

    // Classes whose validation needs a combination of values the sample factory doesn't generate
    protected static function samplesOfClasses(): array
    {
        return [
            DateRange::class => static fn () => new DateRange(
                new \DateTimeImmutable('2026-01-01'),
                new \DateTimeImmutable('2026-01-31'),
            ),
        ];
    }

    // Samples of value objects and strings of the application
    protected static function sampleProviders(): array
    {
        return [
            new AppSampleProvider(),
        ];
    }
}

final readonly class AppSampleProvider implements SampleProvider
{
    public function sample(string $class, string $parameterName, SampleContext $context): ?object
    {
        return match (true) {
            is_subclass_of($class, Id::class) => $class::generateRandom(),
            // Increasing with every sample, so the start of a range is before its end
            $class === Moment::class => Moment::fromString('2027-01-01 10:00:00')->add(Duration::fromHours($context->sequence)),
            // The class whose constructor the parameter belongs to, e.g. for a discriminated read model that requires its type
            $class === ContentElementType::class
                && $context->classOfParameter === ContentElementImage::class => ContentElementType::IMAGE,
            default => null,
        };
    }

    public function string(string $parameterName): ?string
    {
        return str_contains(strtolower($parameterName), 'slug')
            ? 'example-slug'
            : null;
    }
}
```

A `SampleProvider` is asked before the built-in samples for every object the sample factory builds. Besides the class and the name of the parameter, its `SampleContext` contains the class whose constructor the parameter belongs to (`classOfParameter`, also for the elements of a collection and for the implementation built for an interface or abstract class), the values the factory currently tries (`sequence`, `int`, `bool`, `numberOfElements`) and `sampleOf()` to build samples of other classes. Enums are taken from the providers as well; without one, the factory tries the first and the last case.

`SerializerComparison` builds the serializers the test case compares (the configured one, one without the plan normalizer, and one whose fallback throws) and can be used for further tests of your own.

`SerializerMappingIsNotUsedTestCase` checks that no class of your source and no non-dev vendor package uses serializer attributes and that no mapping file is registered:

```php
final class SerializerMappingIsNotUsedTest extends SerializerMappingIsNotUsedTestCase
{
    protected static function sourceDirectories(): array
    {
        return [
            dirname(__DIR__, 2).'/src',
        ];
    }
}
```

Vendor files that use the attributes for classes that are never serialized can be allowed through `allowedVendorFiles()`.

## Isn't normalization in the class a bad practice?

It's often seen that way. In practice, the data structure and its normalized form have to be changed together anyway, so keeping them in one place makes changes easier and safer. This approach has been used in multiple large-scale projects for years without issues. Your mileage may vary.

## Additional documentation

- [Changelog](./CHANGELOG.md)
- [Upgrade guide](./UPGRADE.md)

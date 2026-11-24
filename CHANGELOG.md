# Changelog

## 1.5.1

- The doctrine types of the automatic registration (`*NormalizableThroughLookupType`) look up the class name only once per type instance instead of for every value converted to PHP. The lookup (`Type::lookupName()`) is deprecated since `doctrine/dbal` 4.6 and triggers a deprecation on every call, which is expensive when deprecations are triggered (e.g. `DOCTRINE_DEPRECATIONS=trigger`). With `doctrine/dbal` 4.6, the deprecation is still triggered once for every type instance that converts a value or builds an SQL declaration.

## 1.5.0

- Added the optional `KnownConstructionNormalizer` (`self_aware_normalizers.known_construction_normalizer`, disabled by default): a fast path in front of the `PropertyNormalizer` for plain constructor-promoted classes and self-aware value objects, with exactly the same results. It precomputes a plan per class and is about 8× faster for the normalization and 12× faster for the denormalization of large object graphs (see the README for the setup of the measurement).
- Anything the plan doesn't handle exactly like the `PropertyNormalizer` falls back to it, so a class that misses the fast path only costs performance:
  - Context options are an allowlist; options of your own can be accepted through `accepted_context_keys`.
  - The fallback must be exactly the `PropertyNormalizer` without serializer mapping, with every other constructor argument in its default value (validated when the container is built).
  - Classes a normalizer in front of the `KnownConstructionNormalizer` may handle are never handled inline; normalizers that never handle them can be declared in `neutral_normalizers`.
  - Collections that `symfony/serializer` 8.2 handles differently than 8.1 (arrays that aren't lists for lists, elements of union types) go to the fallback.
- Union types: values of native and PHPDoc unions (also `list<A|B>` and collections of interfaces) are normalized by their runtime class. For the denormalization, unions of scalars are handled by the plan, a set value of a union with other members goes to the fallback.
- Plans can be cached across requests in a PSR-6 pool (`plan_cache_pool`), under the build id of the container.
- Added the profiler panel "Self-Aware Normalizers" (menu entry "Self-Aware Norm."): the classes of a request that took or missed the fast path and why, the values sent through the serializer, the plans built or loaded, the configuration and the serializer mapping that is ignored.
- Documented the cache of the type extractor for production.
- CI runs the tests against the next version of Symfony as well.

## 1.4.0

- Added support for PHP 8.6.
- Dropped support for PHP 8.4.

## 1.3.0

- Added new doctrine types that are registered automatically depending on the interface.
- Deprecated previous doctrine types. They should be replaced with the automatic registration.
- Fixed deprecation for JSONB type.

## 1.2.1

- Fixed length handling for `StringNormalizableType` and `StringEnumType`.

## 1.2.0

- Added support for PHP 8.5.
- Dropped support for PHP 8.3.
- Added support for Symfony ^8.0.
- Dropped support for Symfony 7.3 and below. Only the 7.4 LTS version is still supported.

## 1.1.0

- Added `denormalizeWhenNotNull` through interfaces and traits.

## 1.0.0

- Added configuration to automatically register all custom doctrine types in one or multiple directories.

## 0.3.0

- Fully support PHP 8.4.

## 0.2.0

- When scalar values (`string`, `int`, `float` or `bool`) are provided to a doctrine type, then they are piped through without trying to normalize them.

## 0.1.0

- Initial release

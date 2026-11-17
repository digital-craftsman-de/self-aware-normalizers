# Changelog

## 1.5.0

- Added the optional `KnownConstructionNormalizer` (`self_aware_normalizers.known_construction_normalizer`, disabled by default): a fast path in front of the `PropertyNormalizer` for plain constructor-promoted classes with exactly the same results. Its fallback is validated when the container is built (exactly the `PropertyNormalizer`, every unknown constructor argument in its default value), it's only used with context options on an allowlist (extendable through `accepted_context_keys`), and classes a normalizer in front of it may handle are never handled inline (unless the normalizer is declared in `neutral_normalizers`).
- Added the profiler panel "Self-Aware Normalizers", which shows the classes that missed the fast path of the `KnownConstructionNormalizer` and why.
- Added `SerializerMappingIsNotUsedTestCase` for the tests of applications using the `KnownConstructionNormalizer`.

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

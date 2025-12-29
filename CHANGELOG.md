# Changelog

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

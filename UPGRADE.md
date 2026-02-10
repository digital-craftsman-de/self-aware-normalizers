# Upgrade guide

## From 1.2.* to 1.3.0

### Switch to automatic doctrine types

- Add the directories that contain implementations of the interfaces to `self_aware_normalizers.implementation_directories`.
- Use the full class string in the doctrine column `type` instead of the custom names (see [README - Automatic doctrine types](./README.md#automatic-doctrine-types)).
- Implement the necessary additional interfaces when needed.
- Remove all custom doctrine types.

## From 1.1.* to 1.2.0

### Dropped support for PHP 8.3

Update to at least PHP 8.4.

### Dropped support for Symfony 7.3 and below

Update to at least the LTS version 7.4.

## From 1.0.* to 1.1.0

Nothing to do.

## From 0.3.* to 1.0.0

Nothing to do.

## From 0.2.* to 0.3.0

Nothing to do.

## From 0.1.* to 0.2.0

Nothing to do.

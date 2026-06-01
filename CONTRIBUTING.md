# Contributing to Metrial Laravel RBAC

Thank you for your interest in contributing! This document outlines how to contribute effectively.

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone git@github.com:YOUR_USERNAME/metrial-rbac.git`
3. Install dependencies: `composer install`
4. Run tests: `vendor/bin/phpunit`

## Development Workflow

1. Create a feature branch: `git checkout -b feature/your-feature`
2. Write your code
3. Add tests for new functionality
4. Ensure all tests pass: `vendor/bin/phpunit`
5. Commit with clear messages
6. Push and create a Pull Request

## Coding Standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style
- Use type hints and return types everywhere
- Write descriptive docblocks for all public methods
- Keep methods short and focused (single responsibility)

## Testing

- All new features must include tests
- Bug fixes must include a regression test
- Run the full suite before submitting: `vendor/bin/phpunit`
- Target: 80%+ code coverage

## Commit Messages

Follow conventional commits:

```
feat: add wildcard permission support
fix: resolve cache invalidation race condition
docs: update API resource examples
test: add integration tests for middleware
refactor: extract wildcard resolution to dedicated method
```

## Reporting Bugs

Use the [Bug Report](https://github.com/KaremMetrial/metrial-rbac/issues/new?template=bug_report.md) template and include:

- PHP, Laravel, and package versions
- Minimal code to reproduce
- Expected vs actual behavior

## Feature Requests

Use the [Feature Request](https://github.com/KaremMetrial/metrial-rbac/issues/new?template=feature_request.md) template.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

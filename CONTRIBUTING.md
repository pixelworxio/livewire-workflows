# Contributing to Livewire Workflows

Thank you for considering contributing to Livewire Workflows! This guide will help you understand our development process.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Feature Requests](#feature-requests)

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code. Please be respectful and professional in all interactions.

## Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer
- Laravel 11 or 12
- Livewire 3 or 4

### Local Development Setup

```bash
# Fork and clone the repository
git clone https://github.com/YOUR_USERNAME/livewire-workflows.git
cd livewire-workflows

# Install dependencies
composer install

# Run tests to ensure everything works
composer test
```

## Development Workflow

1. **Fork** the repository on GitHub
2. **Clone** your fork locally
3. **Create a branch** for your feature or bug fix:
   ```bash
   git checkout -b feature/amazing-feature
   ```
4. **Make your changes** following our coding standards
5. **Write tests** for your changes
6. **Run the test suite** to ensure nothing breaks
7. **Commit your changes** with clear, descriptive messages
8. **Push** to your fork
9. **Submit a pull request** to the `main` branch

## Coding Standards

We follow PSR-12 coding standards and SOLID principles.

### Code Style

Run PHP CS Fixer before committing:

```bash
composer format
# or
./vendor/bin/pint
```

### Static Analysis

Ensure your code passes PHPStan analysis:

```bash
composer analyse
# or
./vendor/bin/phpstan analyse
```

### Key Principles

- **SOLID**: Keep classes focused and follow dependency inversion
- **DRY**: Don't repeat yourself
- **KISS**: Keep it simple, stupid
- **PSR-12**: Follow PHP coding standards
- **Type Safety**: Use strict types and proper type hints
- **Immutability**: Favor immutable objects where appropriate

### Code Style Examples

```php
<?php

declare(strict_types=1);

namespace Pixelworxio\LivewireWorkflows\Example;

use Illuminate\Http\Request;

/**
 * Example class following our conventions.
 */
class ExampleClass
{
    public function __construct(
        protected readonly SomeDependency $dependency,
    ) {}

    public function exampleMethod(Request $request): string
    {
        // Use early returns
        if (! $this->shouldProceed($request)) {
            return 'early';
        }

        // Favor readability over brevity
        $result = $this->dependency->process($request);

        return $result;
    }

    protected function shouldProceed(Request $request): bool
    {
        return $request->has('required_field');
    }
}
```

## Testing

We use Pest v3 for testing. All new features must include tests.

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
./vendor/bin/pest tests/Unit/WorkflowRegistrarTest.php

# Run with coverage
./vendor/bin/pest --coverage
```

### Test Structure

```php
<?php

declare(strict_types=1);

use Pixelworxio\LivewireWorkflows\Facades\Workflow;

beforeEach(function () {
    // Setup code
});

test('it does something specific', function () {
    // Arrange
    Workflow::flow('test')->entersAt('test.start', '/test')
        ->finishesAt('done');
    
    // Act
    $result = workflow('test')->nextRouteNameFor(request());
    
    // Assert
    expect($result)->toBe('done');
});
```

### Test Coverage

- Unit tests for business logic
- Feature tests for integration
- Test edge cases and error conditions
- Maintain >80% code coverage

## Pull Request Process

1. **Update documentation** if you change functionality
2. **Update CHANGELOG.md** following [Keep a Changelog](https://keepachangelog.com) format
3. **Ensure tests pass** and coverage is maintained
4. **Update README** if you add features
5. **Request review** from maintainers

### PR Title Format

Use conventional commit format:

- `feat: Add new feature`
- `fix: Resolve bug in guard evaluation`
- `docs: Update installation instructions`
- `test: Add tests for workflow state`
- `refactor: Simplify route registration`
- `chore: Update dependencies`

### PR Description Template

```markdown
## Description
Brief description of changes.

## Type of Change
- [ ] Bug fix (non-breaking change fixing an issue)
- [ ] New feature (non-breaking change adding functionality)
- [ ] Breaking change (fix or feature causing existing functionality to change)
- [ ] Documentation update

## Testing
- [ ] All existing tests pass
- [ ] New tests added for changes
- [ ] Manual testing completed

## Checklist
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
```

## Reporting Bugs

Found a bug? Please create an issue with:

1. **Clear title** describing the bug
2. **Steps to reproduce** the issue
3. **Expected behavior** vs actual behavior
4. **Environment details**:
    - PHP version
    - Laravel version
    - Livewire version
    - Package version
5. **Code samples** if applicable
6. **Stack traces** or error messages

### Bug Report Template

```markdown
**Describe the bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce:
1. Define workflow with '...'
2. Create guard that '...'
3. Visit route '...'
4. See error

**Expected behavior**
A clear description of expected behavior.

**Environment:**
- PHP: 8.3.x
- Laravel: 11.x.x
- Livewire: 3.x.x
- Package: 1.x.x

**Additional context**
Any other context about the problem.
```

## Feature Requests

Have an idea? We'd love to hear it!

1. **Check existing issues** to avoid duplicates
2. **Describe the feature** clearly
3. **Explain the use case** and why it's needed
4. **Provide examples** if possible

### Feature Request Template

```markdown
**Is your feature request related to a problem?**
A clear description of the problem.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Alternative solutions or features you've considered.

**Additional context**
Any other context, code examples, or screenshots.
```

## Documentation

Documentation improvements are always welcome!

- Fix typos and grammatical errors
- Improve clarity and examples
- Add missing use cases
- Translate documentation (future)

## Questions?

- Check [Discussions](https://github.com/pixelworxio/livewire-workflows/discussions) for Q&A
- Open an issue for bugs
- Submit a discussion for general questions

## Recognition

Contributors will be:
- Listed in the README
- Credited in release notes
- Appreciated immensely! 🎉

Thank you for contributing to Livewire Workflows!

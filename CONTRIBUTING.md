# Contributing to @umaarov/goat-dev

Thank you for your interest in contributing to @umaarov/goat-dev! We welcome all contributions, whether you're fixing bugs, adding new features, improving documentation, or suggesting ideas.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How to Contribute](#how-to-contribute)
- [Reporting Issues](#reporting-issues)
- [Submitting Changes](#submitting-changes)
- [Coding Standards](#coding-standards)
- [Pull Request Guidelines](#pull-request-guidelines)
- [Development Setup](#development-setup)
- [Style Guide](#style-guide)
- [Community](#community)
- [License](#license)

---

## Code of Conduct

By participating, you are expected to uphold our [Code of Conduct](CODE_OF_CONDUCT.md). Please be respectful and considerate in all interactions.

## How to Contribute

1. **Fork** the repository and clone it locally.
2. Create a new branch for your contribution:
   ```sh
   git checkout -b my-feature
   ```
3. Make your changes.
4. Ensure all tests pass.
5. Commit your changes with a descriptive message.
6. Push to your fork and submit a [pull request](https://github.com/umaarov/goat-dev/pulls).

## Reporting Issues

- Use the [GitHub Issues](https://github.com/umaarov/goat-dev/issues) to report bugs, suggest features, or ask questions.
- Please provide as much detail as possible (steps to reproduce, expected behavior, logs, screenshots, etc.).

## Submitting Changes

- All changes should be made on a branch other than `main`.
- Write clear, concise commit messages.
- Update documentation as needed.
- Add tests for new features or bug fixes.

## Coding Standards

- Follow idiomatic Go (Golang) conventions.
- Keep functions small and focused.
- Write clear, descriptive comments for complex logic.
- Use descriptive names for variables, functions, and types.

## Pull Request Guidelines

- Ensure your PR description clearly explains the problem and your solution.
- Reference related issues using `Fixes #issue_number` when applicable.
- Check that your code passes all automated tests and lints.
- Be responsive to feedback and willing to make necessary changes.

## Development Setup

1. Install [Go](https://golang.org/dl/) (ensure your version matches the `.go-version` or project requirements).
2. Clone your fork and install dependencies:
   ```sh
   git clone https://github.com/umaarov/goat-dev.git
   cd goat-dev
   go mod tidy
   ```
3. Run tests:
   ```sh
   go test ./...
   ```

## Style Guide

- Format your code with `gofmt` or `go fmt`.
- Run `go vet` to catch potential issues.
- Use descriptive commit messages (e.g., `fix: resolve bug in authentication logic`).

## Community

- Join the discussions in [GitHub Discussions](https://github.com/umaarov/goat-dev/discussions) if enabled.
- Follow and contribute to issues and pull requests to help improve the project.

## License

By contributing, you agree that your contributions will be licensed under the terms of the [LICENSE](LICENSE) file in this repository.

---

Thank you for helping make @umaarov/goat-dev better! 🚀

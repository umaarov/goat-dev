# Contributing to Project GOAT

First and foremost, thank you for considering contributing to GOAT. Your interest and effort are invaluable to the project's success. This document outlines the standards, procedures, and guidelines for making contributions to ensure the process is clear, consistent, and effective for everyone involved.

## Table of Contents

1.  [Code of Conduct](#code-of-conduct)
2.  [How to Contribute](#how-to-contribute)
    * [Reporting Bugs](#reporting-bugs)
    * [Suggesting Enhancements](#suggesting-enhancements)
    * [Submitting Code Changes](#submitting-code-changes)
3.  [Development Workflow](#development-workflow)
    * [Repository Forking & Branching](#repository-forking--branching)
    * [Commit Message Guidelines](#commit-message-guidelines)
    * [Submitting a Pull Request](#submitting-a-pull-request)
4.  [Style Guides & Conventions](#style-guides--conventions)
    * [PHP / Laravel Style](#php--laravel-style)
    * [JavaScript Style](#javascript-style)
    * [C++ Style (for WASM Modules)](#c--style-for-wasm-modules)
5.  [Contact & Communication](#contact--communication)

## Code of Conduct

This project and everyone participating in it is governed by the [Project GOAT Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to the project maintainers.

## How to Contribute

We welcome several forms of contribution, each valuable to the project.

### Reporting Bugs

If you identify a security vulnerability, please **do not** create a public GitHub issue. Instead, refer to our `SECURITY.md` policy for instructions on responsible disclosure.

For all other bugs, please ensure your report is detailed and reproducible. A high-quality bug report should include:

* **A Clear and Descriptive Title**: e.g., "API Error 500 when updating profile with non-ASCII characters."
* **A Step-by-Step Description**: Provide the exact steps required to reproduce the issue.
* **Expected vs. Actual Behavior**: Clearly describe what you expected to happen and what actually occurred.
* **Environment Details**: Specify your operating system, browser version, PHP version, and any other relevant environmental details.
* **Logs and Screenshots**: Include relevant log snippets from `storage/logs` and provide screenshots of the UI error if applicable.

Before submitting, please search the existing issues to ensure you are not reporting a duplicate.

### Suggesting Enhancements

We encourage well-thought-out proposals for new features or enhancements to existing functionality. When submitting a feature request, please provide:

* **A Detailed Proposal**: Explain the feature and its intended purpose clearly.
* **Use Cases & Motivation**: Describe the problem this feature solves or the value it adds. Who would benefit from this?
* **Implementation Sketch (Optional)**: If you have ideas on the technical implementation, please include them. This helps frame the discussion.

### Submitting Code Changes

All code contributions must be submitted via Pull Requests and are subject to review. We have a formal process to ensure quality and consistency.

## Development Workflow

### Repository Forking & Branching

1.  **Fork the Repository**: Start by creating a personal fork of the `umaarov/goat-dev` repository.
2.  **Clone Your Fork**: Clone your fork to your local machine: `git clone https://github.com/YOUR_USERNAME/goat-dev.git`.
3.  **Create a Feature Branch**: All new work must be done in a dedicated branch, created from the `main` branch. Use a descriptive naming convention.
    * For features: `git checkout -b feature/your-feature-name`
    * For bug fixes: `git checkout -b fix/bug-description`

### Commit Message Guidelines

We adhere to the [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/) specification. This ensures a clear and descriptive commit history. Each commit message should consist of a **type**, an optional **scope**, and a **subject**.

* **Types**: `feat`, `fix`, `build`, `chore`, `ci`, `docs`, `style`, `refactor`, `perf`, `test`.
* **Example**:
    ```
    feat(api): add rate limiting to post creation endpoint

    Implements a new middleware to handle rate limiting for the
    POST /posts endpoint, preventing abuse. The limit is configured
    to 10 posts per minute per user.
    ```

### Submitting a Pull Request

1.  **Ensure Tests Pass**: Before submitting, run the full test suite to ensure your changes have not introduced any regressions.
2.  **Push to Your Fork**: Push your feature branch to your forked repository.
3.  **Open a Pull Request**: From your fork on GitHub, open a pull request targeting the `main` branch of the `umaarov/goat-dev` repository.
4.  **Fill Out the PR Template**: Provide a clear description of the changes, link any relevant issues, and complete the checklist in the pull request template.
5.  **Code Review**: At least one project maintainer will review your pull request. Be prepared to address feedback and make changes to your submission. The maintainer will merge the PR once it meets all requirements.

## Style Guides & Conventions

Consistency is key. Please adhere to the following style guides.

### PHP / Laravel Style

* All PHP code must adhere to the **PSR-12** coding standard.
* We use **strict types** (`declare(strict_types=1);`) in all new PHP files.
* Controllers should be lean. Business logic should reside in dedicated **Service classes**.
* Use Laravel's dependency injection container wherever possible. Avoid using facades for complex dependencies in service classes.
* All public methods and complex protected/private methods must be fully documented with PHPDoc blocks.

### JavaScript Style

* Code should follow the **ES6+** standard.
* Use **Prettier** for automated code formatting to maintain consistency.
* All new frontend logic should be modular. Avoid global state where possible.
* For the Three.js/WebGL engine, code must be self-contained within the Web Worker and its modules to prevent main-thread blocking.

### C++ Style (for WASM Modules)

* Follow the **Google C++ Style Guide**.
* Code must be clean, efficient, and memory-safe.
* The interface with JavaScript via Emscripten must be clearly defined and documented.

## Contact & Communication

If you have questions about the contribution process or need to discuss a proposed change, please open a new "Discussion" on GitHub.

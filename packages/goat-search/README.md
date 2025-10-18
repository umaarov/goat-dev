# GOAT Search Engine

A standalone hybrid C++ search daemon for high-performance searching in PHP applications, featuring BM25 and Vector search capabilities.

This package is part of the [GOAT Social Debate Platform](https://github.com/umaarov/goat-dev).

## Requirements

This package compiles a C++ daemon upon installation. You **must** have the following tools available on your server:

* `make`
* A C++ compiler like `g++` (e.g., install `build-essential` on Debian/Ubuntu).

## Installation

You can install the package via Composer:

```bash
composer require umaarov/goat-search
```
After installation, the search daemon will be compiled at `vendor/umaarov/goat-search/bin/search_engine_daemon`. You will need to run this daemon as a background process using a tool like Supervisor.

## Basic Usage

```php
use Umaarov\GoatSearch\SearchClient;

$client = new SearchClient('127.0.0.1', 9999);

// Index a new document
$client->index(1, 'This is the text of the first document.');

// Perform a search
$results = $client->search('text document');

print_r($results); // [1]
```

# GOAT Search Engine for PHP

[![Latest Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/umaarov/goat-search)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://php.net)
[![C++](https://img.shields.io/badge/C%2B%2B-17-00599C.svg)](https://isocpp.org)

**GOAT Search** is a standalone, high-performance **Hybrid Search Engine** designed for PHP applications.

Unlike traditional SQL `LIKE` queries or heavy Java-based engines (Elasticsearch), GOAT combines **BM25 (Keyword Matching)** and **N-Gram Vector Search (Semantic/Fuzzy Matching)** into a lightweight, compiled C++ daemon.

> **Why use this?**
> * 🚀 **Speed:** Core logic runs in C++, communicating with PHP via TCP sockets ( < 1ms response times).
> * 🧠 **Intelligence:** (Partial Vector Matching).
> * 🎯 **Precision:** Uses a smart threshold (0.25) to filter random noise automatically.

---

## 🏗 Architecture

GOAT Search runs as a background service (`bin/goat-daemon`). Your PHP application sends lightweight JSON payloads to it via TCP Port `9999`.



1.  **PHP Client** sends `SEARCH {"query": "iphane"}`.
2.  **GOAT Daemon**:
    * **BM25 Layer**: Checks for exact word matches.
    * **Vector Layer**: Generates N-Gram embeddings and performs Cosine Similarity.
    * **Fusion**: Merges scores and applies a `0.25` confidence threshold.
3.  **Result**: Returns JSON IDs of matching documents (e.g., `[15, 42]`).

---

## 📦 Installation

### Requirements
* **OS:** Linux / macOS (Windows requires WSL)
* **Tools:** `g++`, `make` (Build Essential)
* **PHP:** 8.2+

### Install via Composer
```bash
composer require umaarov/goat-search-backup
```
*The installation process will automatically compile the C++ source code into a binary executable.*

---

## 🚀 Usage

### 1. Start the Daemon
The engine must be running to accept queries. We recommend running this via **Supervisor** or **Docker**.

```bash
# Manual Start (for testing)
./vendor/bin/goat-daemon
# Output: [INFO] GOAT SEARCH ENGINE STARTED on port 9999...
```
### 2. PHP Client Example

```php
use Umaarov\GoatSearch\SearchClient;

require 'vendor/autoload.php';

// Connect to the C++ Daemon
$engine = new SearchClient('127.0.0.1', 9999);

// -----------------------------
// 1. Indexing Data
// -----------------------------
// You can index posts, users, or comments.
$engine->index(101, "Who is the greatest footballer? Ronaldo or Messi?");
$engine->index(102, "Which framework is better? Laravel or Django?");

// -----------------------------
// 2. Searching
// -----------------------------
// Exact match
$results = $engine->search("Ronaldo"); 
// Returns: [101]

// Typo / Semantic match
$results = $engine->search("ronald"); 
// Returns: [101] (Matched via N-Gram Vector)

// Noise filtering
$results = $engine->search("fastapi"); 
// Returns: [] (Filtered out by 0.25 threshold)
```
## ⚙️ Configuration

The engine is pre-tuned for social debate platforms.

| Parameter      | Default | Description                                   |
|:---------------|:--------|:----------------------------------------------|
| **Port**       | `9999`  | The TCP port the daemon listens on.           |
| **Vector Dim** | `1024`  | Dimension size for N-Gram hashing.            |
| **Threshold**  | `0.25`  | Minimum Cosine Similarity to accept a result. |

### Saving & Persistence
The engine holds the index in **RAM** for speed. To save to disk:

```php
$engine->save(); // Writes index.bm25 and index.vec to disk
```
*The engine automatically loads these files upon restart.*

---

## 🐳 Docker Support

If you prefer running GOAT as a microservice:

**`docker-compose.yml`**
```yaml
services:
  goat-search:
    build: 
      context: ./vendor/umaarov/goat-search-backup
    ports:
      - "9999:9999"
    restart: always
```
## 🤝 Contributing

This package is part of the **GOAT Social Ecosystem**.
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

**Maintained by:** [Umarov Ismoiljon](https://github.com/umaarov)

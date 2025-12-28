# Technical Project Summary

This document serves as a comprehensive engineering audit of the project.

It translates the raw codebase into a structured map of Computer Science concepts, identifying the specific Algorithms, Data Structures, and Design Patterns implemented across the system.

## I. Algorithms & Mathematical Logic

### A. Search & Text Analysis

- **Okapi BM25**: (C++) Probabilistic information retrieval for ranking text relevance based on term frequency and document length.
- **Cosine Similarity**: (C++) Vector space metric to measure semantic similarity between high-dimensional text embeddings.
- **Levenshtein Distance**: (PHP) Dynamic programming algorithm for fuzzy string matching and typo tolerance.
- **Soundex**: (PHP) Phonetic algorithm for indexing names by sound (handling "Jon" vs "John").
- **Feature Hashing (The Hashing Trick)**: (C++) Dimensionality reduction converting n-grams into fixed-size vectors without a dictionary.
- **Hybrid Score Fusion**: (C++) Weighted linear combination algorithm to merge Keyword (BM25) and Semantic (Vector) search scores.
- **Linear Search**: (PHP) Iterative string scanning implementation for log filtering (--grep).
- **Regex Pattern Matching**: (PHP) State-machine based text extraction for mentions and URL validation.

### B. Graphics & Geometry

- **Parametric Curve Generation**: (C++/WASM) Calculating 3D vertices for (p,q)-Torus Knots using trigonometric functions.
- **Simplex Noise**: (GLSL) Gradient noise algorithm for procedural texture generation (Ink blots).
- **Volumetric Ray Casting (God Rays)**: (GLSL) Screen-space radial blur using occlusion sampling to simulate light scattering.
- **Spherical to Cartesian Conversion**: (JS) Distributing particles evenly on a 3D sphere surface.
- **AABB Layout (Axis-Aligned Bounding Box)**: (JS) 2D geometric algorithm to center and arrange arbitrary groups of badges.
- **Gaussian Blur (Convolution)**: (PHP/C) Matrix operation for image smoothing and noise reduction.
- **Image Downsampling**: (PHP/C) Algorithmic reduction of pixel density for LQIP generation.
- **Color Space Conversion (HSV to RGB)**: (PHP) Geometric mapping of cylindrical color coordinates to cubic RGB for consistent pastel generation.

### C. System & Optimization

- **Exponential Decay**: (PHP) Mathematical ranking formula ($S(t) = W \cdot e^{-\lambda t}$) to lower scores of old content over time.
- **Greedy Algorithm**: (PHP) Resource allocation logic for filling limited daily email slots with the most urgent users.
- **Probabilistic Throttling (Jitter)**: (PHP) Randomizing execution time to prevent "Thundering Herd" database stampedes.
- **Tailing Algorithm**: (PHP) File pointer manipulation (fseek, ftell) to read only appended bytes of large logs.
- **Batch Processing (Chunking)**: (PHP) Memory-safe cursor iteration for processing millions of database rows.
- **Pessimistic Locking (Mutex)**: (PHP) Concurrency control to prevent race conditions during vote/like counting.
- **Proof of Work (Hashcash)**: (C) Brute-force algorithm finding a nonce to satisfy a cryptographic difficulty constraint.

### D. Security & Data

- **SHA-256**: (C/PHP) Cryptographic hashing implementation.
- **HMAC**: (PHP) Hash-based message authentication code for data integrity.
- **CRC32**: (PHP) Cyclic redundancy check used for deterministic color seeding.
- **Data Masking/Obfuscation**: (PHP) Algorithmic scrubbing of PII to anonymize users while maintaining DB integrity.
- **Collision Resolution (Linear Probing)**: (PHP) Iterative suffix appending to ensure username uniqueness.
- **Canary Testing (Heuristic)**: (PHP) Transaction rollback diagnostic to test database trigger behavior safely in production.

## II. Data Structures

- **Inverted Index**: (C++) Mapping terms to postings lists for O(1) text search.
- **Dense Vectors**: (C++) 1024-dimensional float arrays representing semantic meaning.
- **Scene Graph**: (JS/Three.js) Hierarchical tree structure managing 3D object transformations.
- **Typed Arrays (Buffers)**: (JS/WASM) Contiguous memory blocks (Float32Array) for high-performance binary data transfer.
- **Tree (DOM/XML)**: (PHP) Hierarchical structure used for Sitemap generation.
- **Tree (Threaded Comments)**: (PHP) Recursive parent-child relationship flattened for UI presentation.
- **Queues**: (PHP/Redis) FIFO structures managing asynchronous background jobs.
- **Hash Maps**: (PHP/C++) Key-value stores used for caching, CSP policies, and JSON payloads.
- **Bitmaps**: (PHP/C) 2D pixel grids manipulated during image processing.
- **Linked List (Abstract)**: (PHP) Pagination cursors behaving as doubly linked lists for navigation.
- **TCP Sockets**: (PHP/C++) Stream-based communication channels for inter-service messaging.
- **Priority Queue (Implicit)**: (C++) Sorting mechanisms used to rank search results and user badges.

## III. Design Patterns

### A. Structural Patterns

- **Facade**: (PHP) Static interfaces hiding complex subsystem logic (Laravel Facades).
- **Adapter**: (PHP) Wrappers unifying different OAuth providers (Google, GitHub) into a single User model.
- **Proxy**: (PHP) Classes acting as local interfaces for remote C++ search services.
- **Decorator**: (PHP) Extending job providers to add UUID logging without altering the base class.
- **Bitmasking**: (PHP) Using bitwise OR to store multiple boolean configuration flags in a single integer.

### B. Behavioral Patterns

- **Observer (Pub-Sub)**: (PHP) Event broadcasting system where listeners react to state changes (New Comment → Notification).
- **Command**: (PHP) Encapsulating requests as objects (Console Commands, Jobs) to parameterize and queue them.
- **Strategy**: (PHP/JS) Swapping algorithms at runtime (e.g., Image Processor Fallback, PBR vs Shader Materials).
- **Chain of Responsibility**: (PHP) Middleware pipelines filtering HTTP requests (Auth → Locale → Throttling).
- **Template Method**: (PHP) Defining the skeleton of Social Sharing workflows where subclasses/services implement specific steps.
- **State/Machine**: (Implicit) Handling user lifecycle states (Active → Deactivated → Reactivated).

### C. System & Architectural Patterns

- **Microservices**: (Docker) Decomposing the app into distinct containers (App, Search, Worker, DB).
- **Sidecar**: (Docker) Collocating the C++ Search Engine with the main app for low-latency offloading.
- **Worker Pattern**: (JS & PHP) Offloading heavy lifting to background threads (Web Workers) or processes (Queue Workers).
- **Dependency Injection (IOC)**: (PHP) Injecting services into controllers to ensure loose coupling.
- **Singleton**: (PHP) Ensuring only one instance of specific services (Filesystem) exists.
- **Transaction Script**: (PHP) Wrapping logic in atomic DB transactions.
- **Event-Driven Architecture**: (PHP) Reacting to external webhooks (SonarCloud) asynchronously.
- **Federated Identity**: (PHP) Offloading authentication to third-party IdPs (OAuth 2.0).
- **Query Builder**: (PHP) Object-oriented construction of SQL queries.
- **Server Loop**: (C++) Infinite loop pattern for accepting incoming TCP connections.
- **Pipeline**: (JS) Post-processing graphics chain (Render → Bloom → Output).
- **Factory**: (JS) Centralized logic for instantiating complex 3D Badge objects.
- **Fallback/Circuit Breaker**: (PHP) Gracefully degrading from C-binary processing to PHP-GD if the binary fails.

## Technical Summary

Here is the count of the distinct engineering concepts implemented in project:

| Category | Count | Primary Focus |
|----------|-------|---------------|
| Algorithms | 29 | Text Search, Vector Math, Graphics, Security |
| Data Structures | 12 | Trees, Graphs, Binary Buffers, Queues |
| Design Patterns | 24 | Distributed Systems, Decoupling, Async Processing |
| **Total Concepts** | **65** | |

---

### Project Metrics

- **Code**: 45k+ raw code (calculated without boilerplates, frameworks, libraries, etc. ~20k STB libs, ~25k proprietary code)
- **90+** libraries, tools, APIs, and integrations
- **30+** 3rd party services
- **65+** DSA, patterns, and concepts
- **17** programming languages and formats (PHP, Blade, Javascript, HTML, CSS, C, C++, GLSL, SQL, Dockerfile, Markdown, XML, JSON, YAML, Bash, Shell, Makefile)
- **Time spent**: 500+ hours

### Full Technology Stack

PHP, Laravel 12, MariaDB, Javascript (ES6), C/C++, Bash, Wasm, GLSL, Blade Templating Engine, TailwindCSS, Three.js, Alpine.js, jQuery, FrankenPHP, Caddy, Nginx, Apache HTTP Server, Supervisor, Redis, Eloquent ORM, Query Builder, Vite, Composer, npm, Docker, Docker Compose, Docker multi-stage builds, Git, GitHub, Makefile, Web Workers, PWA (Progressive Web App), Google Gemini API, Stable Diffusion, Cloudflare AI Workers API, Google API Client, Google AdSense, Google Search Console (GSC) API, Google/Bing Ping API, Ezoic, CMP (Consent Management Platform), Let's Encrypt, SonarQube, ESLint, Synk, Trivy, Barryvdh Debugbar, Pulse, Sanctum, Socialite, Carbon, GuzzleHttp, Intervention, Spatie Packages (CSP, ResponseCache, Sitemap etc), cropper.js, Axios, Fetch API, lil-gui, stats.js, concurrently, nlohmann/json, Predis, Debian, MVC, Hybrid Search (BM25, Vector, Levenshtein, Soundex), Service Container (Dependency Injection), Reverse Proxy, Static File Serving, Generative AI Content Creation, i18n (Internationalization), SEO (JSON-LD, Hreflang, Canonical URLs etc), Infinite Scrolling, Initial Server-Side Render with Client-side Hydration, Pusher (Web Sockets), Real-time Typing Indicator, HTTP/2 & HTTP/3 (QUIC), HTTP Security Headers, Bcrypt, Signed URLs, Refresh Token Auth, Rate Limiting, Laravel Gates, Eloquent Observers, Eloquent Accessors, Eloquent Attribute Casting, Queued Mailables, Task Scheduler, Custom Audit Logging Channels, 3D Post-Processing (EffectComposer, Bloom, SSR, SSAO, God Rays, Chromatic Aberration etc), HDRI Lighting, ACESFilmicToneMapping, Simplex Noise, Off-thread Rendering, OffscreenCanvas, LQIP, Asynchronous CSS, Asset Preloading, Shimmer, Debouncing, HTML Email Templating, Web Share API, IntersectionObserver API, FileReader API, DataTransfer API, OpenSearch, Google Image Sitemap Extension, Inline SVG, base64, Telegram Login Widget, Github OAuth, X OAuth, SonarCloud, TrustProxies, Instagram Graph API, Telegram Bot API, X API, Custom Github-based Feature Flags, Netdebug (custom module), Refresh Token System, Account Deactivation, Multitheme Support, DERS Algorithm, Groq API (Llama 3/Mixtral/Vision), C++17 Standard, Emscripten (EMCC), Microservices Architecture, Sidecar Pattern, TCP Socket Networking (Inter-service communication), Custom Search Engine Design, Inverted Indexing, Dense Vector Embeddings (1024-dim), Feature Hashing (The Hashing Trick), Cosine Similarity Math, Okapi BM25 Ranking, Weighted Score Fusion, Parametric Geometry Generation (Torus Knots), Procedural Textures (Perlin/Simplex Noise in GLSL), Volumetric Lighting Algorithms (Ray Casting), Occlusion Culling, Spherical Coordinate Math, AABB (Axis-Aligned Bounding Box) Layout Algorithms, Matrix Convolution (Gaussian Blur), Cryptographic Primitives (SHA-256 implementation in C), Proof of Work Algorithms (Hashcash), Heuristic Canary Testing, Data Masking/Obfuscation Algorithms, Bitwise Operation Logic, Memory Mapping, Threading (std::thread), Mutex Locking, Atomic Transactions, Fallback Strategies (Circuit Breaker), Chain of Responsibility Pattern, Command Pattern, Observer Pattern, Proxy Pattern, Adapter Pattern

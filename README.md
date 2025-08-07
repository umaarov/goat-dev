# GOAT: The Next-Generation Social Debate Platform

![GOAT Banner](./public/images/preview.png)

### [Live Demo: goat.uz](https://www.goat.uz)

[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-support%20us-orange?style=for-the-badge&logo=buy-me-a-coffee)](https://www.buymeacoffee.com/umarov)

**GOAT** is a hyper-performance, feature-rich social debate application engineered on **Laravel 12**. Designed for scalability and security, it provides a robust platform for intelligent, user-driven discussions. The architecture leverages a modern, containerized stack with **Docker** and **FrankenPHP**, and integrates a suite of cutting-edge technologies, including a hybrid AI strategy for content moderation and generation, and a high-fidelity real-time 3D engine for a uniquely engaging user experience.

---

## Table of Contents

- [Key Features & Technical Highlights](#key-features--technical-highlights)
- [Technology Stack](#technology-stack)
- [Getting Started: Local Development](#getting-started-local-development)
    - [Prerequisites](#prerequisites)
    - [Recommended: Docker Installation](#recommended-docker-installation)
    - [Manual Installation](#manual-installation)
- [Environment Configuration](#environment-configuration)
- [Artisan Command Suite](#artisan-command-suite)
- [Show Your Support](#show-your-support)
- [Contributing](#contributing)
- [License](#license)

---

## Key Features & Technical Highlights

GOAT is built with a focus on enterprise-grade performance, security, and a premium, responsive user experience.

* **Advanced Hybrid Search Engine**
  A sophisticated search system powered by a hybrid algorithm combining **BM25** full-text relevance, **Vector** similarity search, **Levenshtein distance** for fuzzy matching, and **Soundex** phonetic analysis. This delivers exceptionally accurate and relevant results for both platform content and users.

* **Multi-Provider Generative AI Suite**
    * **Content Moderation:** A robust pipeline leveraging **Google's Gemini Pro API** for real-time analysis of all user-generated content, augmented by a local keyword filter.
    * **Dynamic Asset Generation:** Empowers users to generate unique profile pictures from text prompts using a **Stable Diffusion AI** model, served via the **Cloudflare AI Workers API**.

* **High-Fidelity 3D Rendering Engine**
  A sophisticated **Three.js/WebGL** engine renders interactive 3D achievement badges in a dedicated **Web Worker**, offloading heavy computation from the main thread. The engine features advanced post-processing effects (**Bloom, SSR, SSAO, God Rays**), **HDRI lighting**, and `ACESFilmicToneMapping` for photorealistic visuals.

* **WebAssembly (WASM) & C++ Optimization**
  Performance-critical geometry calculations for the 3D engine are handled by **C/C++ modules compiled to WebAssembly** via Emscripten. This delivers near-native speed for complex computations directly in the browser, ensuring a smooth UI.

* **Progressive Web App (PWA)**
  Fully installable as a PWA for an app-like experience on desktop and mobile, with offline access capabilities and enhanced performance thanks to service worker caching.

* **Hardened Security & Authentication**
    * **Multi-Provider OAuth2:** Secure login via local credentials, **Google, GitHub, X (Twitter), and Telegram**.
    * **Robust Auth System:** Implements a **Refresh Token** system using **Laravel Sanctum** for persistent, secure sessions.
    * **Best Practices:** Enforces a strict **Content Security Policy (CSP)**, HSTS, and other security headers. Protected against common vulnerabilities with rate limiting, signed URLs, and `Bcrypt` hashing.
    * **Static Analysis:** Continuously scanned for vulnerabilities and code quality issues using **SonarCloud, Snyk, and Trivy**.

* **Performance-Optimized Architecture**
    * **Server:** Built to run on high-performance servers like **FrankenPHP** (with **Caddy**) for HTTP/2 & HTTP/3 support, or traditional stacks like Nginx/Apache with Supervisor.
    * **Caching:** Leverages **Redis** for application caching, queues, and session storage. Uses **Spatie ResponseCache** for intelligent full-page caching.
    * **Frontend:** Delivers a lightning-fast experience with **Initial Server-Side Render (SSR) with Client-side Hydration**, LQIP (Low-Quality Image Placeholders) with shimmer effects, asynchronous CSS loading, and asset preloading.

* **DevOps & Automation**
    * **Containerized Environment:** Ships with a full **Docker Compose** setup including multi-stage builds for lightweight production images.
    * **CI/CD Ready:** Includes a `Makefile` for streamlined build, test, and deployment automation.
    * **Feature Flags:** Utilizes a custom **GitHub-based feature flag system** to safely roll out new functionality in production.

* **Advanced SEO & Monetization**
    * **Deep SEO Integration:** Automated sitemap generation (including Google Image Sitemaps) with ping submissions to Google/Bing. Implements structured data (JSON-LD), `hreflang` tags, and canonical URLs. Integrates with the **Google Search Console (GSC) API**.
    * **Monetization Ready:** Pre-configured for **Google AdSense** and **Ezoic**, with an integrated **Consent Management Platform (CMP)** for GDPR compliance.

* **Comprehensive Admin Toolkit**
  Includes a suite of custom Artisan commands for database cleanup, sitemap generation, image optimization, and `netdebug` for API health checks. Also features an advanced, filterable command-line log viewer.

---

## Technology Stack

The project is implemented using a curated selection of modern, powerful, and scalable technologies.

* **Core Backend:** PHP 8.3+, Laravel 12, MariaDB, Redis
* **Core Frontend:** JavaScript (ES6+), Three.js (WebGL), Alpine.js, Tailwind CSS, Vite
* **High-Performance & Interop:** C/C++ compiled to WebAssembly (WASM), GLSL
* **Server & Deployment:** **FrankenPHP (Caddy)**, **Docker**, Docker Compose, Supervisor, Nginx/Apache
* **APIs & Services:** Google (Gemini, OAuth, AdSense, GSC), Cloudflare AI, Social APIs (X, Instagram, Telegram), Ezoic
* **Key Libraries:** GuzzleHttp, Intervention Image, Predis, Spatie Packages (CSP, ResponseCache, Sitemap), nlohmann/json
* **Tooling & Quality:** Composer, npm, Git, Makefile, **SonarCloud**, **Snyk**, **Trivy**, ESLint, Barryvdh Debugbar, Laravel Pulse

---

## Getting Started: Local Development

### Prerequisites

* **Docker & Docker Compose** (Recommended)
* OR a manual setup with:
    * PHP >= 8.3
    * Composer
    * Node.js & npm
    * MariaDB (or MySQL)
    * Redis
    * **Emscripten SDK**: Required for compiling the C++ to WASM module. Follow the [official installation guide](https://emscripten.org/docs/getting_started/downloads.html).

### Recommended: Docker Installation

This is the fastest way to get a consistent development environment up and running.

1.  **Clone the repository:**
    ```sh
    git clone [https://github.com/umaarov/goat-dev.git](https://github.com/umaarov/goat-dev.git)
    cd goat-dev
    ```
2.  **Set up your environment:**
    ```sh
    cp .env.example .env
    ```
    * *Update the `.env` file with your API keys and configuration (see table below).*

3.  **Build and run the containers:**
    ```sh
    docker-compose up -d --build
    ```
4.  **Finalize installation:**
    ```sh
    docker-compose exec app composer install
    docker-compose exec app php artisan key:generate
    docker-compose exec app php artisan migrate
    docker-compose exec app npm install
    docker-compose exec app bash build.sh # Compile WASM
    docker-compose exec app npm run build
    ```
5.  Your application is now running! Access it at `http://localhost`.

### Manual Installation

1.  **Clone the repository** and `cd` into it.
2.  **Install PHP dependencies:** `composer install`
3.  **Install Node.js dependencies:** `npm install`
4.  **Set up your environment:** `cp .env.example .env` then `php artisan key:generate`
5.  **Configure `.env`:** Add your database, Redis, API keys, etc.
6.  **Run migrations:** `php artisan migrate`
7.  **Compile WASM:** Activate Emscripten SDK and run `bash build.sh`
8.  **Build frontend assets:** `npm run build`
9.  **Run development servers:**
    * `php artisan serve` (or configure FrankenPHP/Nginx)
    * `npm run dev` (for Vite HMR)
10. **Configure Task Scheduler (Production):**
    ```sh
    * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
    ```

---

## Environment Configuration

The following `.env` variables are critical for the application's full functionality.

| Variable | Description | Required |
|---|---|:---:|
| `DB_CONNECTION` | The database driver (e.g., `mysql`). | ✅ |
| `DB_HOST` | The database host. | ✅ |
| `DB_PORT` | The database port. | ✅ |
| `DB_DATABASE` | The database schema name. | ✅ |
| `DB_USERNAME` | The database user. | ✅ |
| `DB_PASSWORD` | The database user's password. | ✅ |
| `REDIS_HOST` | The Redis server host. | ✅ |
| `REDIS_PASSWORD` | The Redis password. | |
| `REDIS_PORT` | The Redis port. | ✅ |
| `GOOGLE_CLIENT_ID` | OAuth Client ID from Google Cloud. | ✅ |
| `GOOGLE_CLIENT_SECRET` | OAuth Client Secret from Google Cloud. | ✅ |
| `GEMINI_API_KEY` | API key for the Google Gemini AI service. | ✅ |
| `CLOUDFLARE_ACCOUNT_ID`| Cloudflare Account ID for AI services. | ✅ |
| `CLOUDFLARE_API_TOKEN` | Cloudflare API Token with AI Workers permissions.| ✅ |
| `GITHUB_CLIENT_ID` | OAuth Client ID from GitHub. | |
| `GITHUB_CLIENT_SECRET` | OAuth Client Secret from GitHub. | |
| `X_CLIENT_ID` | OAuth Client ID from X (Twitter). | |
| `X_CLIENT_SECRET` | OAuth Client Secret from X (Twitter). | |
| `TELEGRAM_BOT_TOKEN` | Your Telegram Bot token for login/API. | |
| `MAIL_MAILER` | The mail driver (e.g., `smtp`). | ✅ |
| `MAIL_HOST` | The SMTP server host. | ✅ |
| `MAIL_USERNAME` | The SMTP username for authentication. | ✅ |
| `MAIL_PASSWORD` | The SMTP password for authentication. | ✅ |

---

## Artisan Command Suite

The application provides a powerful set of custom commands for system administration and maintenance.

### `users:cleanup-unverified`
Deletes unverified user accounts after a configured timeframe (default: 1 hour).
* **Usage**: `php artisan users:cleanup-unverified`
* **Schedule**: Runs every ten minutes.

### `sitemap:generate`
Generates a fresh `sitemap.xml` (with image extensions) and pings Google/Bing.
* **Usage**: `php artisan sitemap:generate`
* **Schedule**: Runs daily at 02:00.

### `images:optimize`
Batch-processes existing JPEG/PNG images, converting them to the high-efficiency WebP format.
* **Usage**: `php artisan images:optimize`

### `app:show-logs`
An advanced, interactive terminal log viewer.
* **Usage**: `php artisan app:show-logs [options]`
* **Options**: `--lines`, `--channel`, `--date`, `--grep`, `--tail`

### `app:netdebug`
A network utility to diagnose connectivity issues with external APIs (e.g., Google, Cloudflare).
* **Usage**: `php artisan app:netdebug`

---

## Show Your Support

If you find this project useful, please consider giving it a star on GitHub!

[![Star History Chart](https://api.star-history.com/svg?repos=umaarov/goat-dev&type=Date)](https://star-history.com/#umaarov/goat-dev)

---

## Contributing

Contributions are what make the open-source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

1.  Fork the Project.
2.  Create your Feature Branch (`git checkout -b feature/AmazingFeature`).
3.  Commit your Changes (`git commit -m 'feat: Add some AmazingFeature'`). We follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/).
4.  Push to the Branch (`git push origin feature/AmazingFeature`).
5.  Open a Pull Request.

---

## Documentation
* The main project documentation for GOAT can be found at [/docs/goat_doc_en.pdf](https://github.com/umaarov/goat-dev/blob/master/docs/goat_doc_en.pdf).
* The full User Interface mockups and design specifications for the platform can be found at [/docs/goat_ui.pdf](https://github.com/umaarov/goat-dev/blob/master/docs/goat_ui.pdf).
* The complete, advanced database schema for the application is detailed in [/docs/goat_db.pdf](https://github.com/umaarov/goat-dev/blob/master/docs/goat_db.pdf).

## License

This project is open-source software distributed under the MIT License. See `LICENSE` for more information.

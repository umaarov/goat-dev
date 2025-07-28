# GOAT: The Next-Generation Social Debate Platform

![GOAT Banner](./public/images/preview.png)

### [Live Demo: goat.uz](https://www.goat.uz)


[![Buy Me a Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-support%20us-orange?style=for-the-badge&logo=buy-me-a-coffee)](https://www.buymeacoffee.com/umarov)

**GOAT** is a sophisticated social debate application built on the **Laravel 12** framework. It's engineered from the ground up to provide a feature-rich, scalable, and secure environment for intelligent, user-driven discussions. The platform's architecture leverages modern design patterns and integrates cutting-edge technologies, including AI for content moderation and real-time 3D graphics for a uniquely engaging user experience.

---

## Table of Contents

- [Key Features & Technical Highlights](#key-features--technical-highlights)
- [Technology Stack](#technology-stack)
- [Getting Started: Local Development](#getting-started-local-development)
    - [Prerequisites](#prerequisites)
    - [Installation](#installation)
- [Environment Configuration](#environment-configuration)
- [Artisan Command Suite](#artisan-command-suite)
- [Show Your Support](#show-your-support)
- [Contributing](#contributing)
- [License](#license)

---

## Key Features & Technical Highlights

GOAT is built with a focus on performance, security, and a premium user experience.

* **AI-Powered Content Moderation**
  A robust pipeline leveraging **Google's Gemini Pro API** for real-time analysis and moderation of all user-generated content (posts, comments, images, URLs), augmented by a configurable local keyword filter.

* **Dynamic AI Asset Generation**
  Empowers users to generate unique profile pictures directly from text prompts using a **Stable Diffusion AI** model, providing endless personalization.

* **High-Performance 3D Rendering**
  A sophisticated **Three.js/WebGL** engine renders interactive 3D achievement badges in a dedicated **Web Worker**. This offloads heavy computation from the main thread, ensuring a consistently smooth and responsive UI.

* **WebAssembly (WASM) Optimization**
  Performance-critical geometry calculations for the 3D engine are handled by **C++ modules compiled to WebAssembly** via Emscripten, delivering near-native speed in the browser.

* **Secure by Design**
  Implements a hardened authentication system with local credentials and **Google OAuth2**. The application is protected by a strict Content Security Policy (CSP), security headers, and follows industry best practices for user data protection.

* **Intelligent Email Notifications**
  A scheduled, queue-based system intelligently sends activity digests to users. It respects user preferences and only dispatches notifications when there is sufficient new content, preventing spam.

* **Advanced Fuzzy Search**
  A powerful search system built with **Levenshtein distance** and **Soundex phonetic algorithms** delivers highly accurate "fuzzy" search results for both platform content and users.

* **Comprehensive Admin Toolkit**
  Includes a suite of custom Artisan commands for database cleanup, sitemap generation, image optimization, and an advanced, filterable command-line log viewer for streamlined maintenance.

* **Automated Image Optimization**
  An automated pipeline processes all user-uploaded images, converting them to the next-generation **WebP** format to dramatically reduce bandwidth and improve page load times.

* **SEO & Internationalization (i18n)**
  Features automated sitemap generation with ping submissions to Google and Bing for optimal search engine indexing. The platform is fully internationalized to support multiple locales.

---

## Technology Stack

The project is implemented using a curated selection of modern and powerful technologies.

* **Backend:** PHP 8.3+, Laravel 12, MySQL
* **Frontend:** JavaScript (ESM), Three.js (WebGL), GLSL, Tailwind CSS, Vite
* **High-Performance Computing:** C++ compiled to WebAssembly (WASM) via Emscripten
* **Core APIs:** Google Gemini API, Stable Diffusion (via Cloudflare Workers AI), Google OAuth 2.0

---

## Getting Started: Local Development

Follow these steps to get a local instance of GOAT up and running.

### Prerequisites

* PHP >= 8.3
* Composer
* Node.js & npm
* MySQL (or a compatible database)
* **Emscripten SDK**: Required for compiling the C++ to WASM module. Follow the [official installation guide](https://emscripten.org/docs/getting_started/downloads.html).

### Installation

1.  **Clone the repository:**
    ```sh
    git clone [https://github.com/umaarov/goat-dev.git](https://github.com/umaarov/goat-dev.git)
    cd goat-dev
    ```

2.  **Install PHP dependencies:**
    ```sh
    composer install
    ```

3.  **Install Node.js dependencies:**
    ```sh
    npm install
    ```

4.  **Set up your environment:**
    * Copy the example environment file:
        ```sh
        cp .env.example .env
        ```
    * Generate a unique application key:
        ```sh
        php artisan key:generate
        ```
    * Configure your database credentials, API keys, and other values in the `.env` file (see table below).

5.  **Run database migrations:**
    ```sh
    php artisan migrate
    ```

6.  **Compile the WebAssembly Module:**
    * Ensure the Emscripten SDK environment is active in your terminal.
    * Execute the compilation script to build the C++ module:
        ```sh
        bash build.sh
        ```
    * This will generate `geometry_optimizer.js` and `geometry_optimizer.wasm` in the `public/assets/wasm/` directory.

7.  **Build frontend assets:**
    ```sh
    npm run build
    ```

8.  **Run the development servers:**
    * Start the Laravel server:
        ```sh
        php artisan serve
        ```
    * In a new terminal, start the Vite HMR server for frontend assets:
        ```sh
        npm run dev
        ```

9.  **Configure the Task Scheduler (Production):**
    * To enable scheduled tasks like email notifications, add the following Cron entry to your server:
        ```sh
        * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
        ```

---

## Environment Configuration

The following `.env` variables are critical for the application's full functionality.

| Variable                | Description                                                   | Required |
|-------------------------|---------------------------------------------------------------|:--------:|
| `DB_CONNECTION`         | The database driver (e.g., `mysql`).                          | ✅       |
| `DB_HOST`               | The database host.                                            | ✅       |
| `DB_PORT`               | The database port.                                            | ✅       |
| `DB_DATABASE`           | The database schema name.                                     | ✅       |
| `DB_USERNAME`           | The database user.                                            | ✅       |
| `DB_PASSWORD`           | The database user's password.                                 | ✅       |
| `GOOGLE_CLIENT_ID`      | OAuth Client ID from your Google Cloud project.               | ✅       |
| `GOOGLE_CLIENT_SECRET`  | OAuth Client Secret from your Google Cloud project.           | ✅       |
| `GOOGLE_REDIRECT_URI`   | The configured OAuth redirect URI.                            | ✅       |
| `GEMINI_API_KEY`        | API key for the Google Gemini AI service.                     | ✅       |
| `CLOUDFLARE_ACCOUNT_ID` | Your Cloudflare Account ID for AI services.                   | ✅       |
| `CLOUDFLARE_API_TOKEN`  | A Cloudflare API Token with AI Workers permissions.           | ✅       |
| `CLOUDFLARE_AI_MODEL`   | The Cloudflare AI model for image generation.                 | ✅       |
| `MAIL_MAILER`           | The mail driver (e.g., `smtp`).                               | ✅       |
| `MAIL_HOST`             | The SMTP server host.                                         | ✅       |
| `MAIL_USERNAME`         | The SMTP username for authentication.                         | ✅       |
| `MAIL_PASSWORD`         | The SMTP password for authentication.                         | ✅       |


---

## Artisan Command Suite

The application provides a powerful set of custom commands for system administration and maintenance.

### `users:cleanup-unverified`
Deletes user accounts that have not been verified via email within a configured timeframe (default: 1 hour).
* **Usage**: `php artisan users:cleanup-unverified`
* **Schedule**: Runs every ten minutes.

### `sitemap:generate`
Generates a fresh `sitemap.xml` file containing all application routes accessible to search engines and pings Google/Bing.
* **Usage**: `php artisan sitemap:generate`
* **Schedule**: Runs daily at 02:00.

### `images:optimize`
A powerful batch-processing command that finds and converts all existing JPEG/PNG images (for posts and profiles) to the high-efficiency WebP format.
* **Usage**: `php artisan images:optimize`

### `app:show-logs`
An advanced, interactive log viewer for your terminal.
* **Usage**: `php artisan app:show-logs [options]`
* **Options**:
    * `--lines=<number>`: Number of lines to display.
    * `--channel=<name>`: Specify a log channel (e.g., `laravel`, `audit_trail`).
    * `--date=<YYYY-MM-DD>`: View logs for a specific date.
    * `--grep=<string>`: Filter lines containing a specific string (case-insensitive).
    * `--tail`: Tail the log file for real-time monitoring.

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

## License

This project is open-source software distributed under the MIT License. See `LICENSE` for more information.

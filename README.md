# 🔍 WatchBoard

<p align="center">
<a href="https://github.com/gemanzo/watchboard/actions/workflows/ci.yml"><img src="https://github.com/gemanzo/watchboard/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

Monitor your websites and APIs, get instant email alerts when something goes down, and share a beautiful public status page with your users.

---

## Features

- **HTTP Monitoring** — Scheduled health checks with configurable intervals (1-5 min)
- **Instant Alerts** — Email notifications on downtime and recovery
- **Public Status Page** — Shareable page with 90-day uptime history bar
- **Dashboard** — Real-time overview with response time charts and uptime metrics
- **REST API** — Fully documented API with Sanctum authentication
- **Multi-plan Architecture** — Free/Pro plan support with configurable limits

## Tech Stack

- **Backend**: PHP 8.4 / Laravel 13
- **Frontend**: Vue 3 / Inertia.js / Tailwind CSS
- **Database**: PostgreSQL
- **Queue**: Redis + Laravel Horizon
- **Testing**: Pest PHP
- **Auth**: Laravel Breeze + Sanctum (API)

## Architecture Highlights

- Event-driven status change detection (`MonitorStatusChanged`)
- Scheduled command → Queue job pipeline for check dispatching
- Laravel Notification system for multi-channel alerts
- Optimized uptime queries with PostgreSQL and caching
- Policy-based authorization with plan limit enforcement

## Getting Started

### Requirements

- PHP 8.3+
- Composer
- Node.js 20+
- PostgreSQL 15+
- Redis

### Installation

```bash
git clone https://github.com/gemanzo/watchboard.git
cd watchboard
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### Configuration

```bash
# Edit .env with your database and Redis credentials
php artisan migrate
php artisan db:seed --class=DemoSeeder  # Optional: load demo data
```

### Running Locally

```bash
# Terminal 1: Laravel dev server
php artisan serve

# Terminal 2: Vite dev server
npm run dev

# Terminal 3: Queue worker (Horizon)
php artisan horizon

# Terminal 4: Scheduler (for local dev)
php artisan schedule:work
```

## API Documentation

API docs available at `/docs/api` when the app is running.

Authentication via Bearer token (generate from dashboard Settings).

## Project Management

This project was built using Agile methodology with weekly sprints. Full documentation:

- [Project Charter](docs/PROJECT_CHARTER.md)
- [Product Backlog](docs/PRODUCT_BACKLOG.md)
- [Sprint Plan](docs/SPRINT_PLAN.md)

## License

MIT

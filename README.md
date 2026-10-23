# shopmade-jobs

Jobs, a weekly schedule, crew photos and job conversations for [Shopmade core](https://github.com/LabskAI/shopmade-core). MIT licensed.

## What it adds

- **Staff:** Jobs and Schedule in the sidebar (Work group). Create, edit and reschedule jobs, assign crew, advance a job with one tap (Start, Mark done, Cancel), upload before/after photos with thumbnails, and reply on a job.
- **Dashboard:** "My jobs today" for crew and the next open jobs for everyone.
- **Customer page:** recent jobs and a New job button.
- **Client portal:** a Visits tab, the next visit and recent photos on the portal home, and comments on a visit.
- **MCP:** `jobs_list` (read-only) lists jobs by status and date range.

## Install

```bash
composer require labskai/shopmade-jobs
php artisan migrate
php artisan storage:link   # photos are served from the public disk
npm run build              # picks up the package's Tailwind classes
```

Core finds the package through Laravel package discovery. Remove it with `composer remove labskai/shopmade-jobs`; its nav entries, panels and portal tab go with it.

Photos need the GD extension for thumbnails (EXIF for phone orientation). Without GD the original is shown in place of the thumbnail.

Quote and invoice buttons on a job show only when a package provides the `quotes.create` and `invoices.create` routes.

## Tests

The tests run inside a core install:

```bash
cd ../shopmade-core
composer config repositories.jobs '{"type":"path","url":"../shopmade-jobs"}'
composer require "labskai/shopmade-jobs:*@dev"
vendor/bin/pest vendor/labskai/shopmade-jobs/tests
```

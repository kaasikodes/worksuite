# Laravel Developer Test - Document Processing Feature

## Goal

Add a "Document Processing" feature to this Laravel app.
Users upload PDF invoices → AI mock extracts data → Match with mock Purchase Orders → Display on dashboard.

## Setup

1. Clone this repository.
2. Run `composer install`.
3. Copy `.env.example` to `.env` and set up DB.
4. Run: `php artisan migrate --seed` and `php artisan serve`
5. Visit `/dashboard`.

## Mock APIs

-   AI Extraction: `POST /api/mock-ai-extract`
-   Purchase Orders: `GET /api/mock-purchase-orders`

## Requirements

-   PDF upload (validated)
-   Process file via AI mock
-   Match to mock PO
-   Store and display results
-   Use queues for processing
-   Include unit tests
-   Update this README with notes

## Submission

-   Push your code to a public GitHub repo.
-   Email the link before the 48-hour deadline.

## Notes

-   Incorrect controller implementation, used AiMockController
-   Also api routes not registered in bootstrap/app.php

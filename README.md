# Sushi Journal API

## Overview

**Sushi Journal** is a backend system designed to allow authenticated users to log sushi rolls they have tried at various restaurants, rate them, attach ingredients, upload photos, and browse an aggregate view of their sushi experiences.

The system is designed API-first, mobile-friendly, and future-scalable.

---

## Technologies Used

- PHP 8.x (vanilla, no frameworks)
- MySQL 8.x (relational database)
- RESTful JSON APIs
- Apache 2.4 (local and production web server)
- AWS EC2 (server hosting)
- Git and GitHub (version control)
- Composer (dependency management for PHP)
- Postman (API testing)

---

## Features

- Secure Google login (future expansion for other providers supported)
- Session key authentication with sliding expiration
- User creation and management
- Sushi roll logging with restaurant and rating
- Upload multiple photos per roll
- Tag rolls with selectable or custom ingredients
- Clean, scalable database architecture
- Future-proof API versioning structure (v1)

---

## Project Structure

```
/api
  /v1
    /auth
      login.php
      logout.php
      ping.php
      refresh.php
    /rolls
      create.php
      photo_upload.php
/lib
  db.php         // Database connection
  session.php    // Session management
/uploads         // Uploaded user images
/vendor          // Composer-managed libraries
/public          // Web-accessible PHP endpoints
.env             // Environment variables (DB credentials, etc.)
schema.sql       // Database creation script
README.md        // Project documentation
composer.json    // Composer project metadata
```

---

## Database Schema Summary

### `users`

| Field | Type | Notes |
| --- | --- | --- |
| id | INT, PK | Auto-incremented user ID |
| provider_sub | VARCHAR(255) | Unique auth provider ID |
| status | TINYINT | 1 = active, 0 = inactive |
| email | VARCHAR(255) | Unique user email |
| display_name | VARCHAR(100) | Friendly display name |
| group_id | INT (nullable) | Future grouping support |
| role | VARCHAR(50) | User role, defaults to 'member' |
| joined_at | BIGINT | Join timestamp |
| mod_at | BIGINT | Modification timestamp |
| last_login | BIGINT | Last login timestamp |
| auth | VARCHAR(50) | Authentication method (e.g., 'google') |
| settings | JSON | User settings |

### `sessions`

| Field | Type | Notes |
| --- | --- | --- |
| id | INT, PK | Auto-incremented session ID |
| session_key | CHAR(64) | Secure random session key |
| user_id | INT | Linked user ID |
| created_at | BIGINT | Creation timestamp |
| expires_at | BIGINT | Expiration timestamp |
| status | TINYINT | 1 = active, 0 = inactive |

### `rolls`

| Field | Type | Notes |
| --- | --- | --- |
| id | INT, PK | Auto-incremented roll ID |
| user_id | INT | Creator's user ID |
| restaurant_name | VARCHAR(255) | Name of restaurant |
| restaurant_google_place_id | VARCHAR(255) | Google Maps Place ID (optional) |
| roll_name | VARCHAR(255) | Name of the sushi roll |
| notes | TEXT | User notes |
| rating | DECIMAL(3,2) | Rating out of 10.00 |
| created_at | BIGINT | Creation timestamp |
| updated_at | BIGINT | Last update timestamp |

### `roll_photos`

| Field | Type | Notes |
| --- | --- | --- |
| id | INT, PK | Auto-incremented photo ID |
| roll_id | INT | Linked roll ID |
| user_id | INT | Uploader's user ID |
| photo_url | VARCHAR(500) | Stored photo path |
| created_at | BIGINT | Upload timestamp |

### `ingredient_tags`

| Field | Type | Notes |
| --- | --- | --- |
| id | INT, PK | Auto-incremented tag ID |
| name | VARCHAR(100) | Unique ingredient name |
| status | TINYINT | 1 = active, 0 = hidden |
| created_by_user_id | INT | ID of the creator (nullable) |
| created_at | BIGINT | Creation timestamp |

### `roll_ingredients`

| Field | Type | Notes |
| --- | --- | --- |
| id | INT, PK | Auto-incremented ID |
| roll_id | INT | Linked roll ID |
| ingredient_tag_id | INT | Linked ingredient ID |

---

## Authentication

All APIs require authentication via session key.

- Session key must be passed in the `Authorization` header.
- Session keys expire after inactivity but are refreshed on valid activity.
- Logout API available to destroy session keys cleanly.

Example header:

```
Authorization: your-session-key-here
```

---

## Current API Endpoints

| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/v1/auth/login.php` | POST | Login user with provider_sub |
| `/api/v1/auth/logout.php` | POST | Logout session key |
| `/api/v1/auth/ping.php` | GET | Check if session is still valid |
| `/api/v1/auth/refresh.php` | POST | Refresh session expiration |
| `/api/v1/rolls/create.php` | POST | Create a new sushi roll log |
| `/api/v1/rolls/photo_upload.php` | POST | Upload a photo for an existing roll |

---

## Setup Instructions

1. Clone repository locally.
2. Copy `.env.example` to `.env` and update database credentials.
3. Run `composer install` to install PHP dependencies.
4. Create your MySQL database and run `schema.sql` to create tables.
5. Serve project using Apache or PHP built-in server (`php -S localhost:8000` for development).
6. Test APIs using Postman with appropriate Authorization headers.

---

## Future Enhancements

- Google Maps API integration for restaurant selection
- Aggregate roll listing (combining multiple user reviews)
- Favorites/bookmarking rolls
- Improved image handling using AWS S3
- Session key rotation and refresh token support
- Pagination for roll listings
- Admin moderation for ingredient tags

---

## Notes

This project is designed for clean incremental growth.

It follows best practices for database normalization, API security, and backend structure.

No frameworks are used to encourage deep understanding of backend fundamentals.

---
# GateArch

A student portal and admin system in plain PHP 8 and MySQL, with no framework. Students see their own courses and grades; admins manage students, courses, enrollments and grades.

## Features

**Students**
- Dashboard: enrolled courses, credits, grades, letter grades and a credit-weighted average.
- Course page with details and grade, scoped to the logged-in student.

**Admins**
- Students: add, edit, reset password, delete, search, paginate.
- Courses: add, edit, delete, search, paginate.
- Enrollments and grades: enroll, grade inline (0 to 100, two decimals), clear a grade, unenroll, filter by course.
- Audit log of every admin change: who, what and when.

## Security

| Threat | Defence |
| --- | --- |
| SQL injection | Every query is a PDO prepared statement with bound values, all in `includes/repo.php`. Emulated prepares are off. |
| Wildcard injection in search | `%` and `_` are escaped in `LIKE` patterns. |
| Stored passwords | `password_hash()` with `PASSWORD_DEFAULT`, re-hashed on login when the default changes. |
| Account enumeration | Unknown user and wrong password give the same message. |
| Brute force | 5 failed logins per username or IP in 15 minutes locks login for that window. |
| Session fixation | `session_regenerate_id(true)` on login. |
| Session theft | HttpOnly, SameSite=Lax cookies, Secure over HTTPS. Logout destroys the session and its cookie. |
| CSRF | One random token per session, checked with `hash_equals()` on every POST, including logout. |
| XSS | All output goes through `htmlspecialchars()` (the `h()` helper). A Content-Security-Policy allows only same-origin scripts. |
| Broken access control | `require_role('admin')` or `require_role('student')` on the first line of every protected page. Student queries are scoped to the session user ID. |
| Direct access to code | Only `public/` is the web root. `includes/`, `sql/`, `bin/` and `tests/` also carry `Require all denied`. |
| Double submits | Post/Redirect/Get after every successful POST. |

The database enforces its own rules too: unique usernames, course codes and enrollments, grades from 0 to 100, credits from 1 to 12, and `ON DELETE CASCADE` from students and courses to enrollments and grades.

## Run with Docker

```sh
cp .env.example .env          # set real passwords
docker compose up -d --build
docker compose exec web php bin/create-admin.php admin "Your Name"
# optional demo data (password for every demo account: demo-password)
docker compose exec web php bin/seed-demo.php
```

Open http://localhost:8080.

## Run with XAMPP or LAMP

1. Create a database `gatearch` and load `sql/schema.sql`.
2. Set `GATEARCH_DB_DSN`, `GATEARCH_DB_USER` and `GATEARCH_DB_PASS`, or edit the defaults in `includes/config.php`.
3. Point the web root at `public/`.
4. `php bin/create-admin.php admin "Your Name"`

## Tests

```sh
php tests/run.php
```

The suite runs every query against an in-memory SQLite copy of the schema (`tests/schema.sqlite.sql`). It covers hashing, login, throttling, data scoping, unique rules, grade insert, update and clear, range checks, cascades, admin protection, search escaping, SQL injection attempts, pagination, averages, letter grades, input validation, output escaping, the audit log and stats.

## Layout

```
public/      web root: one PHP file per page, plus assets/
includes/    bootstrap, config, db, repo (all SQL), auth, validate, layout
sql/         MySQL schema
bin/         create-admin.php, seed-demo.php
tests/       run.php and the SQLite schema twin
```

## Licence

MIT

-- SQLite twin of sql/schema.sql, used only by the test suite.
-- Same tables, columns, keys and cascades; MySQL-only syntax swapped for SQLite's.

CREATE TABLE users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  username      TEXT NOT NULL UNIQUE,
  full_name     TEXT NOT NULL,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL DEFAULT 'student' CHECK (role IN ('student','admin')),
  created_at    DATETIME NOT NULL
);

CREATE TABLE courses (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  code        TEXT NOT NULL UNIQUE,
  name        TEXT NOT NULL,
  credits     INTEGER NOT NULL CHECK (credits BETWEEN 1 AND 12),
  description TEXT
);

CREATE TABLE enrollments (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  student_id  INTEGER NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
  course_id   INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
  enrolled_at DATETIME NOT NULL,
  UNIQUE (student_id, course_id)
);

CREATE TABLE grades (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  enrollment_id INTEGER NOT NULL UNIQUE REFERENCES enrollments(id) ON DELETE CASCADE,
  grade         NUMERIC NOT NULL CHECK (grade BETWEEN 0 AND 100),
  updated_at    DATETIME NOT NULL
);

CREATE TABLE login_attempts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  username     TEXT NOT NULL,
  ip           TEXT NOT NULL,
  attempted_at INTEGER NOT NULL
);

CREATE TABLE audit_log (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  actor      TEXT NOT NULL,
  action     TEXT NOT NULL,
  detail     TEXT NOT NULL,
  created_at DATETIME NOT NULL
);

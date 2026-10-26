-- GateArch schema, MySQL 8 / MariaDB 10.6+
-- Foreign keys with ON DELETE CASCADE keep the database consistent on its own:
-- deleting a student or a course removes their enrollments and grades.

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(80)  NOT NULL UNIQUE,
  full_name     VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('student','admin') NOT NULL DEFAULT 'student',
  created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE courses (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(20)  NOT NULL UNIQUE,
  name        VARCHAR(120) NOT NULL,
  credits     TINYINT      NOT NULL,
  description TEXT,
  CHECK (credits BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE enrollments (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  student_id  INT NOT NULL,
  course_id   INT NOT NULL,
  enrolled_at DATETIME NOT NULL,
  UNIQUE KEY uq_enrollment (student_id, course_id),
  FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE,
  FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One grade per enrollment.
CREATE TABLE grades (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT NOT NULL UNIQUE,
  grade         DECIMAL(5,2) NOT NULL,
  updated_at    DATETIME NOT NULL,
  CHECK (grade BETWEEN 0 AND 100),
  FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Failed logins, for throttling (5 failures per 15 minutes per username or IP).
CREATE TABLE login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  username     VARCHAR(80) NOT NULL,
  ip           VARCHAR(45) NOT NULL,
  attempted_at INT NOT NULL,
  INDEX idx_attempt_user (username, attempted_at),
  INDEX idx_attempt_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Who changed what, and when. Kept even if the admin account is later deleted.
CREATE TABLE audit_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  actor      VARCHAR(80)  NOT NULL,
  action     VARCHAR(40)  NOT NULL,
  detail     VARCHAR(255) NOT NULL,
  created_at DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

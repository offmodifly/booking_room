<?php
/** โครงสร้างฐานข้อมูล */
return [
"CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  prefix VARCHAR(30) NOT NULL DEFAULT '',
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL DEFAULT '',
  position VARCHAR(150) NOT NULL DEFAULT '',
  role ENUM('admin','building_staff','room_staff','building_head','deputy_director','director','user') NOT NULL DEFAULT 'user',
  email VARCHAR(150) NULL,
  phone VARCHAR(30) NULL,
  avatar VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  theme ENUM('light','dark') NOT NULL DEFAULT 'light',
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS buildings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(30) NOT NULL DEFAULT '',
  location VARCHAR(255) NOT NULL DEFAULT '',
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  floors TINYINT UNSIGNED NOT NULL DEFAULT 1,
  width_m DECIMAL(6,2) NOT NULL DEFAULT 30,
  depth_m DECIMAL(6,2) NOT NULL DEFAULT 15,
  color VARCHAR(7) NOT NULL DEFAULT '#d9d4c7',
  style TEXT NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS building_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  building_id INT UNSIGNED NOT NULL,
  file VARCHAR(255) NOT NULL,
  face ENUM('front','back','left','right','roof','other') NOT NULL DEFAULT 'other',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bimg_building FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  building_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(30) NOT NULL DEFAULT '',
  floor TINYINT UNSIGNED NOT NULL DEFAULT 1,
  pos_x DECIMAL(5,2) NOT NULL DEFAULT 50,
  pos_y DECIMAL(5,2) NOT NULL DEFAULT 50,
  width_m DECIMAL(6,2) NOT NULL DEFAULT 10,
  depth_m DECIMAL(6,2) NOT NULL DEFAULT 8,
  height_m DECIMAL(4,2) NOT NULL DEFAULT 3,
  capacity INT UNSIGNED NOT NULL DEFAULT 20,
  equipment TEXT NULL,
  description TEXT NULL,
  color VARCHAR(7) NOT NULL DEFAULT '#e8e3d8',
  layout TEXT NULL,
  status ENUM('available','booked','unavailable') NOT NULL DEFAULT 'available',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_room_building FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS room_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  file VARCHAR(255) NOT NULL,
  wall ENUM('front','back','left','right','floor','ceiling','other') NOT NULL DEFAULT 'other',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rimg_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_no VARCHAR(20) NULL UNIQUE,
  room_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  requester_name VARCHAR(150) NOT NULL DEFAULT '',
  department VARCHAR(150) NOT NULL DEFAULT '',
  title VARCHAR(255) NOT NULL,
  purpose TEXT NULL,
  equip TEXT NULL,
  attendees INT UNSIGNED NOT NULL DEFAULT 0,
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME NOT NULL,
  contact_phone VARCHAR(30) NULL,
  status ENUM('pending','head_approved','deputy_approved','signed','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  admin_note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_room_time (room_id, start_datetime, end_datetime),
  INDEX idx_status (status),
  CONSTRAINT fk_bk_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_bk_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_bk_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS booking_signatures (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  step ENUM('requester','building_head','deputy_director','director') NOT NULL,
  user_id INT UNSIGNED NULL,
  signer_name VARCHAR(150) NOT NULL,
  signer_position VARCHAR(150) NOT NULL DEFAULT '',
  decision ENUM('sign','approve','reject') NOT NULL,
  note VARCHAR(255) NULL,
  file VARCHAR(255) NOT NULL,
  hash CHAR(64) NOT NULL,
  signed_at DATETIME NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  UNIQUE KEY uq_booking_step (booking_id, step),
  INDEX idx_sig_user (user_id),
  CONSTRAINT fk_sig_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_sig_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS remember_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL UNIQUE,
  validator_hash CHAR(64) NOT NULL,
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  INDEX idx_rt_user (user_id),
  CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS booking_staff (
  booking_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ack_at DATETIME NULL,
  done_at DATETIME NULL,
  PRIMARY KEY (booking_id, user_id),
  CONSTRAINT fk_bs_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_bs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  username VARCHAR(50) NOT NULL DEFAULT '',
  action VARCHAR(50) NOT NULL,
  detail TEXT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

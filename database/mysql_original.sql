CREATE DATABASE IF NOT EXISTS taller_simulacion_umss CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE taller_simulacion_umss;

CREATE TABLE IF NOT EXISTS datasets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('titulados','empleadores') NOT NULL,
  filename VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  rows_count INT NOT NULL DEFAULT 0,
  columns_count INT NOT NULL DEFAULT 0,
  headers_json LONGTEXT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS responses (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  dataset_id INT NOT NULL,
  row_number INT NOT NULL,
  data_json LONGTEXT NOT NULL,
  CONSTRAINT fk_responses_dataset FOREIGN KEY (dataset_id) REFERENCES datasets(id) ON DELETE CASCADE,
  INDEX idx_dataset(dataset_id)
) ENGINE=InnoDB;

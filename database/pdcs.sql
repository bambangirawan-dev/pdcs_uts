-- Database sistem akademik (Pertemuan 2)
-- Import file ini lewat phpMyAdmin: tab Import -> pilih pdcs.sql -> Go

CREATE DATABASE IF NOT EXISTS pdcs CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE pdcs;

DROP TABLE IF EXISTS matakuliah;
DROP TABLE IF EXISTS mahasiswa;
DROP TABLE IF EXISTS jurusan;

-- Tabel jurusan
CREATE TABLE jurusan (
  id INT(11) NOT NULL AUTO_INCREMENT,
  kode_jurusan VARCHAR(32) NOT NULL,
  nama_jurusan VARCHAR(64) NOT NULL,
  fakultas VARCHAR(32) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_kode_jurusan (kode_jurusan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel mahasiswa (jurusan 1 : N mahasiswa)
CREATE TABLE mahasiswa (
  id INT(11) NOT NULL AUTO_INCREMENT,
  jurusan_id INT(11) DEFAULT NULL,
  nama VARCHAR(32) NOT NULL,
  nim VARCHAR(32) NOT NULL,
  created_at DATE NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_nim (nim),
  KEY idx_mahasiswa_jurusan (jurusan_id),
  CONSTRAINT fk_mahasiswa_jurusan FOREIGN KEY (jurusan_id)
    REFERENCES jurusan (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel matakuliah (jurusan 1 : N matakuliah)
CREATE TABLE matakuliah (
  id INT(11) NOT NULL AUTO_INCREMENT,
  jurusan_id INT(11) NOT NULL,
  kode_mk VARCHAR(32) NOT NULL,
  nama_mk VARCHAR(64) NOT NULL,
  sks INT(8) NOT NULL,
  semester INT(8) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_kode_mk (kode_mk),
  KEY idx_matakuliah_jurusan (jurusan_id),
  CONSTRAINT fk_matakuliah_jurusan FOREIGN KEY (jurusan_id)
    REFERENCES jurusan (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data contoh
INSERT INTO jurusan (id, kode_jurusan, nama_jurusan, fakultas) VALUES
(1, 'TI', 'Teknik Informatika', 'Teknik'),
(2, 'SI', 'Sistem Informasi', 'Teknik'),
(3, 'MN', 'Manajemen', 'Ekonomi');

INSERT INTO mahasiswa (id, jurusan_id, nama, nim, created_at) VALUES
(1, 1, 'Budi Santoso', '202401001', '2024-09-02'),
(2, 2, 'Siti Aminah', '202401002', '2024-09-02'),
(3, 1, 'Andi Pratama', '202401003', '2024-09-03'),
(4, 3, 'Dewi Lestari', '202401004', '2024-09-03'),
(5, NULL, 'Rina Kartika', '202401005', '2024-09-04');

INSERT INTO matakuliah (id, jurusan_id, kode_mk, nama_mk, sks, semester) VALUES
(1, 1, 'IT027', 'Pemrograman Database Client Server', 3, 3),
(2, 1, 'IT021', 'Basis Data', 3, 2),
(3, 2, 'SI015', 'Analisis Sistem Informasi', 3, 3),
(4, 3, 'MN010', 'Pengantar Manajemen', 2, 1);


-- Database: hsv_color_detection

CREATE DATABASE IF NOT EXISTS hsv_color_detection;
USE hsv_color_detection;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    upload_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE colors (
    color_id INT AUTO_INCREMENT PRIMARY KEY,
    color_name VARCHAR(50) NOT NULL UNIQUE,
    hsv_lower VARCHAR(50) NOT NULL,
    hsv_upper VARCHAR(50) NOT NULL
);

CREATE TABLE image_colors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_id INT NOT NULL,
    color_id INT NOT NULL,
    percentage FLOAT,
    FOREIGN KEY (image_id) REFERENCES images(image_id) ON DELETE CASCADE,
    FOREIGN KEY (color_id) REFERENCES colors(color_id) ON DELETE CASCADE
);

CREATE TABLE color_regions (
    region_id INT AUTO_INCREMENT PRIMARY KEY,
    image_id INT NOT NULL,
    color_id INT NOT NULL,
    mask_image_path VARCHAR(255),
    FOREIGN KEY (image_id) REFERENCES images(image_id),
    FOREIGN KEY (color_id) REFERENCES colors(color_id)
);

CREATE TABLE IF NOT EXISTS search_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    search_type ENUM('plate', 'disk') NOT NULL,
    search_term VARCHAR(50) NOT NULL,
    search_date DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
); 
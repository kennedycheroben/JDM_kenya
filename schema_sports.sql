ALTER TABLE users ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sports_matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  team_a VARCHAR(100) NOT NULL,
  team_b VARCHAR(100) NOT NULL,
  status ENUM('Scheduled', 'Ongoing', 'Completed') DEFAULT 'Scheduled',
  start_time DATETIME NOT NULL,
  match_type VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sports_stats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  goals_scored INT DEFAULT 0,
  assists INT DEFAULT 0,
  mvp_awards INT DEFAULT 0,
  gbs_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_sports_stats_user (user_id),
  CONSTRAINT fk_sports_stats_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sports_commentary (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT NOT NULL,
  comment_text TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sports_commentary_match_created (match_id, created_at),
  CONSTRAINT fk_sports_commentary_match
    FOREIGN KEY (match_id) REFERENCES sports_matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

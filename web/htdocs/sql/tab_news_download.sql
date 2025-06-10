-- For news items
CREATE TABLE news (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_published TINYINT(1) DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES user(id)
);

-- For downloadable files
CREATE TABLE downloads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  filename VARCHAR(255) NOT NULL,
  filepath VARCHAR(255) NOT NULL,
  filesize INT,
  filetype VARCHAR(100),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_published TINYINT(1) DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES user(id)
);

-- For linking news to downloads (optional - if you want multiple files per news item)
CREATE TABLE news_downloads (
  news_id INT NOT NULL,
  download_id INT NOT NULL,
  PRIMARY KEY (news_id, download_id),
  FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE,
  FOREIGN KEY (download_id) REFERENCES downloads(id) ON DELETE CASCADE
);
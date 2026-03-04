CREATE TABLE IF NOT EXISTS rebotling_goal_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  goal_type VARCHAR(50) NOT NULL DEFAULT 'dagmal',
  value INT NOT NULL,
  changed_by VARCHAR(100),
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_type_time (goal_type, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

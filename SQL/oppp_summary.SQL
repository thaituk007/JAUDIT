CREATE TABLE oppp_summary (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hospcode VARCHAR(10),
  hospname VARCHAR(255),
  report_month VARCHAR(10),
  qty INT DEFAULT 0,
  upload_date DATETIME
) CHARACTER SET utf8 COLLATE utf8_general_ci;

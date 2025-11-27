CREATE TABLE nhso_import (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hospcode VARCHAR(5),
  hospname VARCHAR(255),
  batch_no VARCHAR(50),
  cid VARCHAR(13),
  fullname VARCHAR(255),
  start_date DATE,
  expire_date DATE,
  main_hosp VARCHAR(255),
    upload_date DATE,
    UNIQUE KEY uniq_hosp_batch (hospcode, batch_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

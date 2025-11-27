CREATE TABLE nhso_excel_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospcode VARCHAR(10),
    hospname VARCHAR(255),
    batch_no VARCHAR(50),
    transfer_date DATE,
    amount DECIMAL(12,2),
    fund VARCHAR(255),
    sub_fund VARCHAR(255),
    remark TEXT,
    UNIQUE KEY (hospcode, batch_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

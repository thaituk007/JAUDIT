CREATE TABLE oppp_pivot (
    hospcode VARCHAR(10),
    hospname VARCHAR(255),
    report_month VARCHAR(20), -- เก็บค่าแบบ "พ.ย.-2567"
    sent TINYINT(1) DEFAULT 0,
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (hospcode, report_month)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_general_ci;

ALTER TABLE oppp_pivot
MODIFY COLUMN report_month VARCHAR(255) NOT NULL;

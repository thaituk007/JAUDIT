-- DROP TABLE IF EXISTS เพื่อความสะดวกเวลาสร้างใหม่
-- DROP TABLE IF EXISTS nhso_excel;

CREATE TABLE nhso_excel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospcode VARCHAR(10) NOT NULL COMMENT 'รหัสหน่วยบริการ',
    hospname VARCHAR(255) NOT NULL COMMENT 'ชื่อหน่วยบริการ',
    upload_date DATE NOT NULL COMMENT 'วันที่อัปโหลดข้อมูล',
    batch_no VARCHAR(50) NOT NULL COMMENT 'Batch No.',
    fund VARCHAR(100) DEFAULT NULL COMMENT 'กองทุน',
    amount DECIMAL(15,2) DEFAULT NULL COMMENT 'จำนวนเงิน',
    transfer_date DATE DEFAULT NULL COMMENT 'วันที่โอน',
    pay_no VARCHAR(50) DEFAULT NULL COMMENT 'งวด/เลขที่เบิกจ่าย',
    sub_fund VARCHAR(100) DEFAULT NULL COMMENT 'กองทุนย่อย',
    sub_fund_detail VARCHAR(255) DEFAULT NULL COMMENT 'กองทุนย่อยเฉพาะด้าน',
    hold_transfer VARCHAR(255) DEFAULT NULL COMMENT 'ชะลอการโอน รายการหักจากยอดโอนเงิน',
    contract_guarantee VARCHAR(255) DEFAULT NULL COMMENT 'หลักประกันสัญญา',
    tax VARCHAR(100) DEFAULT NULL COMMENT 'ภาษี',
    remaining VARCHAR(100) DEFAULT NULL COMMENT 'คงเหลือ',
    waiting_deduction VARCHAR(100) DEFAULT NULL COMMENT 'จำนวนเงินรอหักกลบ',
    transferred_amount VARCHAR(100) DEFAULT NULL COMMENT 'เงินโอนเข้าบัญชี',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_hosp_batch_fund_date (hospcode, batch_no, fund, transfer_date),
    INDEX idx_hospcode (hospcode),
    INDEX idx_batch_no (batch_no),
    INDEX idx_fund (fund),
    INDEX idx_transfer_date (transfer_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

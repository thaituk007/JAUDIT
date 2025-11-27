CREATE TABLE `eclaim_hsub_data` (
  `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ลำดับข้อมูล',
  `rep_no` VARCHAR(50) DEFAULT NULL COMMENT 'หมายเลข REP',
  `seq_no` VARCHAR(20) DEFAULT NULL COMMENT 'ลำดับที่',
  `tran_id` VARCHAR(50) DEFAULT NULL COMMENT 'รหัส TRAN_ID',
  `hn` VARCHAR(50) DEFAULT NULL COMMENT 'HN',
  `an` VARCHAR(50) DEFAULT NULL COMMENT 'AN',
  `pid` VARCHAR(20) DEFAULT NULL COMMENT 'เลขประจำตัวประชาชน',
  `fullname` VARCHAR(255) DEFAULT NULL COMMENT 'ชื่อ-สกุลผู้ป่วย',
  `patient_type` VARCHAR(50) DEFAULT NULL COMMENT 'ประเภทผู้ป่วย',
  `admit_date` DATE DEFAULT NULL COMMENT 'วันเข้ารักษา',
  `discharge_date` DATE DEFAULT NULL COMMENT 'วันจำหน่าย',
  `reimburse_net` DECIMAL(15,2) DEFAULT NULL COMMENT 'ชดเชยสุทธิ',
  `reimburse_from` VARCHAR(100) DEFAULT NULL COMMENT 'ชดเชยจาก',
  `error_code` VARCHAR(50) DEFAULT NULL COMMENT 'Error Code',
  `main_fund` VARCHAR(100) DEFAULT NULL COMMENT 'กองทุนหลัก',
  `sub_fund` VARCHAR(100) DEFAULT NULL COMMENT 'กองทุนย่อย',
  `service_type` VARCHAR(100) DEFAULT NULL COMMENT 'ประเภทบริการ',
  `referral` VARCHAR(100) DEFAULT NULL COMMENT 'การรับส่งต่อ',
  `entitlement` VARCHAR(100) DEFAULT NULL COMMENT 'การมีสิทธิ',
  `right_used` VARCHAR(100) DEFAULT NULL COMMENT 'การใช้สิทธิ',
  `ref_hospital` VARCHAR(100) DEFAULT NULL COMMENT 'หน่วยบริการที่รับส่งต่อ',
  `total_claim` DECIMAL(15,2) DEFAULT NULL COMMENT 'ยอดชดเชยทั้งสิ้น',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'เวลาที่นำเข้าข้อมูล'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='ตารางข้อมูล eClaim Hsub (นำเข้าจาก Excel)';


ALTER TABLE `eclaim_hsub_data`
ADD UNIQUE KEY `uq_rep_tran_id` (`rep_no`, `tran_id`);
ALTER TABLE `eclaim_hsub_data`
ADD COLUMN `file_source` VARCHAR(255) NULL AFTER `total_claim`;

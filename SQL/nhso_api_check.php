CREATE TABLE PersonFundDetail (
    id INT AUTO_INCREMENT PRIMARY KEY,   -- ใช้เป็น PK แทน PID
    -- ==== Person ====
    pid VARCHAR(13) NOT NULL,           -- เลขประจำตัวประชาชน 13 หลัก
    checkDate DATE,                     -- วันที่ตรวจสอบสิทธิ
    tname VARCHAR(50),                  -- คำนำหน้าชื่อ
    fname VARCHAR(100),                 -- ชื่อ
    lname VARCHAR(100),                 -- นามสกุล
    nation_id VARCHAR(10),              -- รหัสสัญชาติ
    birthDate DATE,                     -- วันเกิด
    sex_id VARCHAR(10),                 -- รหัสเพศ
    deathDate DATE,                     -- วันที่เสียชีวิต
    -- ==== Fund ====
    transDate DATE,                     -- วันที่เปลี่ยนแปลงสิทธิ
    fundType ENUM('Y','N') DEFAULT 'N',-- สถานะกองทุน
    -- ==== FundDetail ====
    mainInscl_id VARCHAR(20),           -- รหัสสิทธิหลัก
    mainInscl_name VARCHAR(255),        -- ชื่อสิทธิหลัก
    subInscl_id VARCHAR(20),            -- รหัสสิทธิย่อย
    subInscl_name VARCHAR(255),         -- ชื่อสิทธิย่อย
    startDateTime DATE,                 -- วันที่เริ่มใช้สิทธิย่อย
    expireDateTime DATE,                -- วันที่หมดอายุสิทธิย่อย
    paidModel VARCHAR(10),              -- รูปแบบการจ่ายเงิน
    hospMainOp_hcode VARCHAR(20),       -- หน่วยบริการประจำ รหัสหน่วย
    hospMainOp_hname VARCHAR(255),      -- หน่วยบริการประจำ ชื่อหน่วย
    hospSub_hcode VARCHAR(20),          -- หน่วยบริการปฐมภูมิ รหัสหน่วย
    hospSub_hname VARCHAR(255),         -- หน่วยบริการปฐมภูมิ ชื่อหน่วย
    hospMain_hcode VARCHAR(20),         -- หน่วยบริการส่งต่อ รหัสหน่วย
    hospMain_hname VARCHAR(255)         -- หน่วยบริการส่งต่อ ชื่อหน่วย
);


ALTER TABLE personfunddetail
ADD COLUMN purchaseProvince_id VARCHAR(10) NULL,
ADD COLUMN purchaseProvince_name VARCHAR(100) NULL,
ADD COLUMN relation VARCHAR(50) NULL,
ADD COLUMN cardId VARCHAR(20) NULL;

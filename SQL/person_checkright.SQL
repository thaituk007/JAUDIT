CREATE TABLE person_checkright (
    cid CHAR(13) NOT NULL PRIMARY KEY,        -- เลขประจำตัวประชาชน 13 หลัก
    name VARCHAR(100) NOT NULL,         -- ชื่อ
    lname VARCHAR(100) NOT NULL,          -- นามสกุล
    gender ENUM('M','F') NOT NULL,            -- เพศ: M=ชาย, F=หญิง
    age TINYINT UNSIGNED,                     -- อายุ
    main_inscl VARCHAR(10),                   -- รหัสสิทธิหลัก
    main_inscl_name VARCHAR(100),             -- ชื่อสิทธิหลัก
    sub_inscl VARCHAR(10),                    -- รหัสสิทธิย่อย
    sub_inscl_name VARCHAR(100),              -- ชื่อสิทธิย่อย
    check_date DATE,                          -- วันที่ตรวจสอบสิทธิ
    death_status BOOLEAN DEFAULT FALSE,       -- สถานะการเสียชีวิต: 0=มีชีวิต, 1=เสียชีวิต
    reg_hmain_op VARCHAR(10),                 -- รหัสหน่วยบริการประจำ
    reg_hmain_op_name VARCHAR(150),           -- ชื่อหน่วยบริการประจำ
    reg_hsub VARCHAR(10),                     -- รหัสหน่วยบริการปฐมภูมิ
    reg_hsub_name VARCHAR(150),               -- ชื่อหน่วยบริการปฐมภูมิ
    referral_hmain VARCHAR(10),               -- รหัสหน่วยบริการที่รับการส่งต่อ
    referral_hmain_name VARCHAR(150),         -- ชื่อหน่วยบริการที่รับการส่งต่อ
    province_code CHAR(2),                     -- รหัสจังหวัด
    province_name VARCHAR(100),               -- ชื่อจังหวัด
    paid_model VARCHAR(50),                   -- รูปแบบการจ่ายเงิน
    remark TEXT                               -- หมายเหตุ
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

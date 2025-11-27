CREATE TABLE IF NOT EXISTS nhso_check (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checkDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    pid VARCHAR(13) NOT NULL,
    tname VARCHAR(100),
    fname VARCHAR(100),
    lname VARCHAR(100),
    nation VARCHAR(10),
    birthDate DATE,
    sex CHAR(1),
    deathDate DATE,
    hospMain VARCHAR(100),
    funds TEXT
);

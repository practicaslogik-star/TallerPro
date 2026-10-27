--
-- Table structure for table llx_taller_material
--

CREATE TABLE IF NOT EXISTS llx_taller_material (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(255) NOT NULL,
    ref             VARCHAR(128) DEFAULT NULL,
    proveedor       VARCHAR(255) DEFAULT NULL,
    stock           DOUBLE DEFAULT 0,
    precio          DOUBLE(24,8) DEFAULT 0,
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INT NOT NULL,
    entity          INT DEFAULT 1,
    status          SMALLINT DEFAULT 1,
    INDEX idx_taller_material_ref (ref),
    INDEX idx_taller_material_nombre (nombre)
) ENGINE=innodb;

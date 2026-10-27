--
-- Table structure for table llx_taller_seguro
--

CREATE TABLE IF NOT EXISTS llx_taller_seguro (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_vehiculo     INT DEFAULT NULL,
    compania        VARCHAR(128) NOT NULL,
    poliza          VARCHAR(64) DEFAULT NULL,
    tipo            VARCHAR(64) DEFAULT NULL,
    fecha_inicio    DATE DEFAULT NULL,
    fecha_fin       DATE DEFAULT NULL,
    telefono        VARCHAR(32) DEFAULT NULL,
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INT NOT NULL,
    entity          INT DEFAULT 1,
    status          SMALLINT DEFAULT 1,
    INDEX idx_taller_seguro_vehiculo (fk_vehiculo),
    INDEX idx_taller_seguro_compania (compania)
) ENGINE=innodb;

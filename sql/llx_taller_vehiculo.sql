--
-- Table structure for table llx_taller_vehiculo
--

CREATE TABLE IF NOT EXISTS llx_taller_vehiculo (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_soc          INT NOT NULL,
    matricula       VARCHAR(32) NOT NULL,
    marca           VARCHAR(64) DEFAULT NULL,
    modelo          VARCHAR(64) DEFAULT NULL,
    anio            VARCHAR(10) DEFAULT NULL,
    color           VARCHAR(32) DEFAULT NULL,
    vin             VARCHAR(64) DEFAULT NULL,
    km              INT DEFAULT 0,
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INT NOT NULL,
    entity          INT DEFAULT 1,
    status          SMALLINT DEFAULT 1,
    INDEX idx_taller_vehiculo_fk_soc (fk_soc),
    INDEX idx_taller_vehiculo_matricula (matricula)
) ENGINE=innodb;

-- Copyright (C) 2026 TallerPro
-- Table structure for llx_taller_factura

CREATE TABLE IF NOT EXISTS llx_taller_factura (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    numero_factura  VARCHAR(64) NOT NULL,
    fecha           DATE DEFAULT NULL,
    fk_soc          INT DEFAULT NULL,
    fk_vehiculo     INT DEFAULT NULL,
    total           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estado          VARCHAR(32) DEFAULT 'Pendiente',
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INT NOT NULL,
    entity          INT DEFAULT 1,
    status          SMALLINT DEFAULT 1,
    INDEX idx_taller_factura_soc (fk_soc),
    INDEX idx_taller_factura_vehiculo (fk_vehiculo),
    INDEX idx_taller_factura_num (numero_factura)
) ENGINE=innodb;

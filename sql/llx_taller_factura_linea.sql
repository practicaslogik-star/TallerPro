-- Copyright (C) 2026 TallerPro
-- Table structure for llx_taller_factura_linea

CREATE TABLE IF NOT EXISTS llx_taller_factura_linea (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_factura      INT NOT NULL,
    tipo            VARCHAR(32) NOT NULL, -- 'material' | 'reparacion'
    fk_material     INT DEFAULT NULL,
    codigo          VARCHAR(64) DEFAULT NULL,
    descripcion     VARCHAR(255) NOT NULL,
    cantidad        DECIMAL(10,2) DEFAULT 1.00,
    precio          DECIMAL(10,2) DEFAULT 0.00,
    descuento       DECIMAL(5,2) DEFAULT 0.00,
    total_neto      DECIMAL(10,2) DEFAULT 0.00,
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    entity          INT DEFAULT 1,
    INDEX idx_taller_fac_linea_fac (fk_factura),
    INDEX idx_taller_fac_linea_tipo (tipo)
) ENGINE=innodb;

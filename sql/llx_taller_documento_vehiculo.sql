CREATE TABLE IF NOT EXISTS llx_taller_documento_vehiculo (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_vehiculo INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    tipo VARCHAR(100) DEFAULT NULL,
    archivo VARCHAR(255) DEFAULT NULL,
    url VARCHAR(500) DEFAULT NULL,
    datec DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fk_vehiculo (fk_vehiculo)
) ENGINE=InnoDB;

--
-- Table structure for table llx_taller_stock_mvt
--

CREATE TABLE IF NOT EXISTS llx_taller_stock_mvt (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_material     INT NOT NULL,
    cantidad        DOUBLE NOT NULL,
    tipo            VARCHAR(32) NOT NULL,
    fk_reparacion   INT DEFAULT NULL,
    stock_anterior  DOUBLE DEFAULT 0,
    stock_posterior DOUBLE DEFAULT 0,
    fecha           DATETIME NOT NULL,
    fk_user         INT NOT NULL,
    descripcion     VARCHAR(255) DEFAULT NULL,
    entity          INT DEFAULT 1,
    INDEX idx_taller_mvt_mat (fk_material),
    INDEX idx_taller_mvt_rep (fk_reparacion)
) ENGINE=innodb;

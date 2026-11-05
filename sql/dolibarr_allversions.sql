--
-- Script run when an upgrade of Dolibarr is done. Whatever is the Dolibarr version.
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


CREATE TABLE IF NOT EXISTS llx_taller_material (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(255) NOT NULL,
    ref             VARCHAR(128) DEFAULT NULL,
    fk_product      INT DEFAULT NULL,
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
    INDEX idx_taller_material_nombre (nombre),
    INDEX idx_taller_material_fk_product (fk_product)
) ENGINE=innodb;

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

CREATE TABLE IF NOT EXISTS llx_taller_reparacion (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_vehiculo     INT NOT NULL,
    fecha           DATE NOT NULL,
    ref_factura     VARCHAR(64) DEFAULT NULL,
    descripcion     TEXT DEFAULT NULL,
    coste_mo        DOUBLE(24,8) DEFAULT 0,
    notas           TEXT DEFAULT NULL,
    date_creation   DATETIME NOT NULL,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat   INT NOT NULL,
    entity          INT DEFAULT 1,
    status          SMALLINT DEFAULT 1,
    estado          VARCHAR(32) DEFAULT 'en_curso',
    INDEX idx_taller_rep_fk_vehiculo (fk_vehiculo),
    INDEX idx_taller_rep_fecha (fecha)
) ENGINE=innodb;

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


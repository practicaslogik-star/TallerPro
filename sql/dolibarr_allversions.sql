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

CREATE TABLE IF NOT EXISTS llx_taller_factura (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    numero_factura  VARCHAR(64) NOT NULL,
    fecha           DATE DEFAULT NULL,
    fk_soc          INT DEFAULT NULL,
    fk_vehiculo     INT DEFAULT NULL,
    total           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descuento_global DECIMAL(10,2) DEFAULT 0.00,
    iva_porcentaje  DECIMAL(5,2) DEFAULT 21.00,
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

CREATE TABLE IF NOT EXISTS llx_taller_factura_linea (
    rowid           INT AUTO_INCREMENT PRIMARY KEY,
    fk_factura      INT NOT NULL,
    tipo            VARCHAR(32) NOT NULL,
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

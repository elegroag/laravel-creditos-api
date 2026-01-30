-- =================================================================
-- BASE DE DATOS RELACIONAL COMFACA CRÉDITO (MySQL)
-- Convertido desde MongoDB a MySQL
-- =================================================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS comfaca_credito_mysql
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE comfaca_credito_mysql;

-- =================================================================
-- TABLA DE USUARIOS (basado en users.mongodb.js)
-- =================================================================
CREATE TABLE usuarios (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(255),
    phone VARCHAR(20),
    roles JSON, -- Almacenado como JSON para mantener flexibilidad
    disabled BOOLEAN DEFAULT FALSE,
    tipo_documento VARCHAR(10),
    numero_documento VARCHAR(20),
    nombres VARCHAR(100),
    apellidos VARCHAR(100),
    password_hash VARCHAR(255) NOT NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_usuarios_username (username),
    INDEX idx_usuarios_email (email),
    INDEX idx_usuarios_documento (numero_documento),
    INDEX idx_usuarios_disabled (disabled)
);

-- =================================================================
-- TABLA DE ROLES (basado en roles.mongodb.js)
-- =================================================================
CREATE TABLE roles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    permisos JSON, -- Almacenado como JSON array de permisos
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_roles_nombre (nombre),
    INDEX idx_roles_activo (activo)
);

-- =================================================================
-- TABLA DE EMPRESAS CONVENIO (basado en empresas_convenios.mongodb.js)
-- =================================================================
CREATE TABLE empresas_convenio (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    nit BIGINT NOT NULL UNIQUE,
    razon_social VARCHAR(255) NOT NULL,
    fecha_convenio DATE,
    fecha_vencimiento DATE,
    estado ENUM('Activo', 'Inactivo', 'Suspendido', 'Vencido') DEFAULT 'Activo',
    representante_documento VARCHAR(20),
    representante_nombre VARCHAR(255),
    telefono VARCHAR(20),
    correo VARCHAR(255),
    direccion TEXT,
    ciudad VARCHAR(100),
    departamento VARCHAR(100),
    sector_economico VARCHAR(100),
    numero_empleados INT,
    tipo_empresa VARCHAR(100),
    descripcion TEXT,
    notas_internas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_empresas_nit (nit),
    INDEX idx_empresas_estado (estado),
    INDEX idx_empresas_vencimiento (fecha_vencimiento)
);

-- =================================================================
-- TABLA DE ESTADOS DE SOLICITUD (basado en estados_solicitud.mongodb.js)
-- =================================================================
CREATE TABLE estados_solicitud (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    orden INT NOT NULL,
    color VARCHAR(7) DEFAULT '#6B7280',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_estados_codigo (codigo),
    INDEX idx_estados_orden (orden),
    INDEX idx_estados_activo (activo)
);

-- =================================================================
-- TABLA DE LÍNEAS DE INVERSIÓN (basado en lineas_inversion.mongodb.js)
-- =================================================================
CREATE TABLE lineas_inversion (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    linea_credito VARCHAR(255) NOT NULL,
    monto_maximo_pesos DECIMAL(15,2) NOT NULL,
    plazo_maximo VARCHAR(50),
    tasas_interes_anual JSON, -- {categoria_a: "10%", categoria_b: "11%", categoria_c: "14%"}
    requisitos JSON, -- Array de requisitos
    categoria ENUM('A', 'B', 'C') DEFAULT 'B',
    descripcion TEXT,
    estado ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_lineas_categoria (categoria),
    INDEX idx_lineas_estado (estado),
    INDEX idx_lineas_monto (monto_maximo_pesos)
);

-- =================================================================
-- TABLA DE SECUENCIA DE NÚMEROS DE SOLICITUD (basado en numero_solicitudes.mongodb.js)
-- =================================================================
CREATE TABLE numero_solicitudes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    radicado VARCHAR(20) UNIQUE,
    numeric_secuencia INT NOT NULL DEFAULT 0,
    linea_credito VARCHAR(10) DEFAULT '03',
    vigencia INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_radicado (radicado),
    INDEX idx_secuencia_vigencia (vigencia),
    INDEX idx_secuencia_linea (linea_credito),
    INDEX idx_secuencia_numero (numeric_secuencia)
);

-- =================================================================
-- TABLA DE SOLICITUDES DE CRÉDITO (versión simplificada y normalizada)
-- =================================================================
CREATE TABLE solicitudes_credito (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    numero_solicitud VARCHAR(20) UNIQUE,
    owner_username VARCHAR(100) NOT NULL,
    xml_filename VARCHAR(255),
    monto_solicitado DECIMAL(15,2) NOT NULL,
    monto_aprobado DECIMAL(15,2) DEFAULT 0,
    plazo_meses INT,
    tasa_interes DECIMAL(5,2),
    destino_credito VARCHAR(255),
    descripcion TEXT,
    estado_codigo VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (owner_username) REFERENCES usuarios(username) ON DELETE CASCADE,
    FOREIGN KEY (estado_codigo) REFERENCES estados_solicitud(codigo),

    INDEX idx_solicitudes_numero (numero_solicitud),
    INDEX idx_solicitudes_owner (owner_username),
    INDEX idx_solicitudes_estado (estado_codigo),
    INDEX idx_solicitudes_monto (monto_solicitado),
    INDEX idx_solicitudes_fecha (created_at)
);

-- =================================================================
-- TABLA DE DOCUMENTOS DE SOLICITUDES (normalizada)
-- =================================================================
CREATE TABLE solicitud_documentos (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    solicitud_id BIGINT NOT NULL,
    documento_uuid VARCHAR(36) NOT NULL UNIQUE, -- UUID del documento
    documento_requerido_id VARCHAR(100) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    saved_filename VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100),
    tamano_bytes INT,
    ruta_archivo VARCHAR(500),
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id) ON DELETE CASCADE,

    INDEX idx_documentos_solicitud (solicitud_id),
    INDEX idx_documentos_uuid (documento_uuid),
    INDEX idx_documentos_tipo (documento_requerido_id),
    INDEX idx_documentos_activo (activo)
);

-- =================================================================
-- TABLA DE PAYLOAD DE SOLICITUDES (normalizada)
-- =================================================================
CREATE TABLE solicitud_payload (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    solicitud_id BIGINT NOT NULL,
    version VARCHAR(20) DEFAULT '1.0',
    encabezado JSON, -- {fecha_radicado, otros campos}
    solicitud JSON, -- {numero_solicitud, valor_solicitud, categoria, etc}
    producto_solicitado JSON, -- {tipo, ha_tenido_credito_comfaca}
    solicitante JSON, -- Datos completos del solicitante
    informacion_laboral JSON, -- Datos laborales
    ingresos_descuentos JSON, -- Información financiera
    informacion_economica JSON, -- Activos y pasivos
    propiedades JSON, -- Propiedades del solicitante
    deudas JSON, -- Deudas existentes
    referencias JSON, -- Referencias personales y familiares
    linea_credito JSON, -- Detalles de línea de crédito
    save_xml BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id) ON DELETE CASCADE,

    INDEX idx_payload_solicitud (solicitud_id),
    INDEX idx_payload_version (version)
);

-- =================================================================
-- TABLA DE TIMELINE DE SOLICITUDES (normalizada)
-- =================================================================
CREATE TABLE solicitud_timeline (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    solicitud_id BIGINT NOT NULL,
    estado_codigo VARCHAR(50) NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    detalle TEXT,
    usuario_username VARCHAR(100),
    automatico BOOLEAN DEFAULT FALSE, -- Si es cambio automático o manual
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id) ON DELETE CASCADE,
    FOREIGN KEY (estado_codigo) REFERENCES estados_solicitud(codigo),
    FOREIGN KEY (usuario_username) REFERENCES usuarios(username) ON DELETE SET NULL,

    INDEX idx_timeline_solicitud (solicitud_id),
    INDEX idx_timeline_estado (estado_codigo),
    INDEX idx_timeline_fecha (fecha),
    INDEX idx_timeline_usuario (usuario_username)
);

-- =================================================================
-- TABLA DE DATOS DEL SOLICITANTE (normalizada)
-- =================================================================
CREATE TABLE solicitud_solicitante (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    solicitud_id BIGINT NOT NULL,
    tipo_identificacion VARCHAR(10),
    numero_identificacion VARCHAR(20),
    nombres_apellidos VARCHAR(255),
    email VARCHAR(255),
    telefono_fijo VARCHAR(20),
    telefono_movil VARCHAR(20),
    barrio_residencia VARCHAR(255),
    ciudad_residencia VARCHAR(20),
    codigo_categoria VARCHAR(5),
    empresa_nit BIGINT,
    empresa_razon_social VARCHAR(255),
    fecha_expedicion_documento DATE,
    fecha_nacimiento DATE,
    fecha_vinculacion DATE,
    nivel_educativo VARCHAR(10),
    pais_nacimiento VARCHAR(20),
    pais_residencia VARCHAR(20),
    personas_a_cargo INT,
    profesion_ocupacion VARCHAR(100),
    salario DECIMAL(15,2),
    sexo VARCHAR(1),
    tipo_vivienda VARCHAR(50),
    vive_con_nucleo_familiar BOOLEAN,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_credito(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_nit) REFERENCES empresas_convenio(nit) ON DELETE SET NULL,

    INDEX idx_solicitante_solicitud (solicitud_id),
    INDEX idx_solicitante_identificacion (numero_identificacion),
    INDEX idx_solicitante_empresa (empresa_nit),
    INDEX idx_solicitante_email (email)
);

-- =================================================================
-- TABLA DE ENTIDAD DIGITAL (basado en entidad_digital.mongodb.js)
-- =================================================================
CREATE TABLE entidad_digital (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    tipo_identificacion VARCHAR(10),
    numero_identificacion VARCHAR(20),
    documentos JSON, -- {frente: "ruta", reverso: "ruta"}
    selfie VARCHAR(500),
    clave_firma_hash VARCHAR(255),
    estado ENUM('activa', 'inactiva', 'bloqueada') DEFAULT 'activa',
    metadata JSON, -- {directorio: "ruta"}
    validaciones JSON, -- Array de validaciones
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_validation_at TIMESTAMP NULL,

    FOREIGN KEY (username) REFERENCES usuarios(username) ON DELETE CASCADE,

    INDEX idx_entidad_username (username),
    INDEX idx_entidad_identificacion (numero_identificacion),
    INDEX idx_entidad_estado (estado)
);

-- =================================================================
-- TABLA DE DOCUMENTOS POSTULANTES (basado en documentos_postulantes.mongodb.js)
-- =================================================================
CREATE TABLE documentos_postulantes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    tipo_documento VARCHAR(50) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    saved_filename VARCHAR(255) NOT NULL,
    tipo_mime VARCHAR(100),
    tamano_bytes INT,
    ruta_archivo VARCHAR(500),
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (username) REFERENCES usuarios(username) ON DELETE CASCADE,

    INDEX idx_docs_postulantes_username (username),
    INDEX idx_docs_postulantes_tipo (tipo_documento),
    INDEX idx_docs_postulantes_activo (activo)
);

-- =================================================================
-- TABLA DE POSTULACIONES
-- =================================================================
CREATE TABLE postulaciones (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    tipo_postulante ENUM('trabajador', 'empresa') NOT NULL,
    empresa_nit BIGINT,
    empresa_razon_social VARCHAR(255),
    datos_personales JSON,
    datos_laborales JSON,
    datos_financieros JSON,
    estado ENUM('iniciada', 'completa', 'verificada', 'aprobada', 'rechazada') DEFAULT 'iniciada',
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (username) REFERENCES usuarios(username) ON DELETE CASCADE,
    FOREIGN KEY (empresa_nit) REFERENCES empresas_convenio(nit) ON DELETE SET NULL,

    INDEX idx_postulaciones_username (username),
    INDEX idx_postulaciones_tipo (tipo_postulante),
    INDEX idx_postulaciones_empresa (empresa_nit),
    INDEX idx_postulaciones_estado (estado)
);

-- =================================================================
-- INSERCIONES DE DATOS INICIALES
-- =================================================================

-- Insertar Roles (basado en roles.mongodb.js)
INSERT INTO roles (nombre, descripcion, permisos, activo) VALUES
('administrator', 'Administrador del sistema con acceso completo',
 JSON_ARRAY('users.create', 'users.edit', 'users.delete', 'users.view', 'applications.create', 'applications.edit', 'applications.delete', 'applications.view_all', 'roles.manage', 'system.admin'),
 TRUE),
('adviser', 'Asesor para gestión de solicitudes de crédito',
 JSON_ARRAY('applications.create', 'applications.edit', 'applications.delete', 'applications.view_all', 'applications.approve', 'applications.reject'),
 TRUE),
('user_empresa', 'Usuario de tipo empresa que solicita créditos',
 JSON_ARRAY('applications.create', 'applications.edit', 'applications.delete', 'applications.view_own'),
 TRUE),
('user_trabajador', 'Usuario de tipo trabajador que solicita créditos',
 JSON_ARRAY('applications.create', 'applications.edit', 'applications.delete', 'applications.view_own'),
 TRUE);

-- Insertar Usuario Admin (basado en el ejemplo proporcionado)
INSERT INTO usuarios (
    username,
    email,
    full_name,
    phone,
    roles,
    disabled,
    tipo_documento,
    numero_documento,
    nombres,
    apellidos,
    password_hash
) VALUES (
    'admin',
    'soportesistemas.comfaca@gmail.com',
    'soporte sistemas',
    '3157145942',
    JSON_ARRAY('administrator'),
    FALSE,
    'CC',
    '1',
    'administrador',
    'del sistema',
    'scrypt:32768:8:1$IrbXijQNUFKajwcP$4b6289e81b53bd3d926ccc37385462dfe3dd8ad9dfe367046eb547685e270287d490631b39f60541a9c5ab8ac380fc2a437068781cc30cd7890f8e0d43260b32'
);

-- Insertar Estados de Solicitud (basado en estados_solicitud.mongodb.js)
INSERT INTO estados_solicitud (codigo, nombre, descripcion, orden, color, activo) VALUES
('POSTULADO', 'Postulado', 'Solicitud recién creada y postulada', 1, '#6B7280', TRUE),
('DOCUMENTOS_CARGADOS', 'Documentos cargados', 'Todos los documentos han sido cargados', 2, '#3B82F6', TRUE),
('ENVIADO_VALIDACION', 'Enviado para validación', 'Enviado para validación de asesores', 3, '#F59E0B', TRUE),
('PENDIENTE_FIRMADO', 'Pendiente de firmado', 'Solicitud en proceso de firmado de solicitud de crédito', 4, '#f5e20bff', TRUE),
('FIRMADO', 'Firmado', 'Documentos de crédito firmados', 5, '#0D9488', TRUE),
('ENVIADO_PENDIENTE_APROBACION', 'Enviado (pendiente de aprobación)', 'Solicitud enviada y pendiente de aprobación', 6, '#8B5CF6', TRUE),
('APROBADO', 'Aprobado', 'Solicitud aprobada y lista para desembolso', 7, '#10B981', TRUE),
('DESEMBOLSADO', 'Desembolsado', 'Crédito desembolsado al solicitante', 8, '#059669', TRUE),
('FINALIZADO', 'Finalizado', 'Crédito pagado y finalizado', 9, '#6366F1', TRUE),
('RECHAZADO', 'Rechazado', 'Solicitud rechazada por no cumplir requisitos', 10, '#EF4444', TRUE),
('DESISTE', 'Desiste', 'El solicitante desiste de continuar con la solicitud', 11, '#F97316', TRUE),
('REQUIRE_CORRECCION', 'Requiere correccion', 'El solicitante debe corregir los datos para poder continuar con la solicitud', 12, '#16a6f9ff', TRUE);

-- Insertar Líneas de Inversión (basado en lineas_inversion.mongodb.js)
INSERT INTO lineas_inversion (linea_credito, monto_maximo_pesos, plazo_maximo, tasas_interes_anual, requisitos, categoria) VALUES
('EDUCACION SUPERIOR (20 SMLMV)', 23200000.00, '60 meses',
 JSON_OBJECT('categoria_a', '10%', 'categoria_b', '11%', 'categoria_c', '14%'),
 JSON_ARRAY('Formulario de solicitud de crédito', 'Fotocopia de cédula de ciudadanía al 150% del solicitante', 'Desprendible de nómina de los dos últimos meses', 'Certificado laboral (no mayor a 30 días)', 'Comprobante para pago de matrícula', 'Copia de un recibo de servicio público'),
 'B'),
('LIBRE INVERSION (25 SMLMV)', 29000000.00, '60 meses',
 JSON_OBJECT('categoria_a', '12%', 'categoria_b', '13%', 'categoria_c', '15%'),
 JSON_ARRAY('Formulario de solicitud de crédito', 'Fotocopia de cédula de ciudadanía al 150% del solicitante', 'Desprendible de nómina de los dos últimos meses', 'Certificado laboral (no mayor a 30 días)', 'Copia de un recibo de servicio público'),
 'B'),
('VIVIENDA REMODELACIÓN (30 SMLMV)', 34800000.00, '84 meses',
 JSON_OBJECT('categoria_a', '9%', 'categoria_b', '10%', 'categoria_c', '11%'),
 JSON_ARRAY('Formulario de solicitud de crédito', 'Fotocopia de cédula de ciudadanía al 150% del solicitante', 'Desprendible de nómina de los dos últimos meses', 'Certificado laboral (no mayor a 30 días)', 'Presupuesto de inversión en obra civil', 'Certificado de libertad y tradición', 'Certificado de riesgo no mitigable', 'Copia de un recibo de servicio público'),
 'B'),
('SALUD (15 SMLMV)', 17400000.00, '48 meses',
 JSON_OBJECT('categoria_a', '8%', 'categoria_b', '9%', 'categoria_c', '11%'),
 JSON_ARRAY('Formulario de solicitud de crédito', 'Fotocopia de cédula de ciudadanía al 150% del solicitante', 'Desprendible de nómina de los dos últimos meses', 'Certificado laboral (no mayor a 30 días)', 'Cotización del servicio médico o procedimiento', 'Copia de un recibo de servicio público'),
 'B'),
('TURISMO (10 SMLMV)', 11600000.00, '36 meses',
 JSON_OBJECT('categoria_a', '11%', 'categoria_b', '12%', 'categoria_c', '14%'),
 JSON_ARRAY('Formulario de solicitud de crédito', 'Fotocopia de cédula de ciudadanía al 150% del solicitante', 'Desprendible de nómina de los dos últimos meses', 'Certificado laboral (no mayor a 30 días)', 'Cotización del plan turístico o tiquetes', 'Copia de un recibo de servicio público'),
 'B');

-- Insertar Empresa de Prueba (basado en empresas_convenios.mongodb.js)
INSERT INTO empresas_convenio (nit, razon_social, fecha_convenio, fecha_vencimiento, estado, representante_documento, representante_nombre, telefono, correo) VALUES
(1221322333, 'Empresa de Prueba', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'Activo', '123456789', 'Representante Legal', '123456789', 'representante@empresa.com');

-- Insertar Secuencia de Solicitudes para año actual
INSERT INTO numero_solicitudes (radicado, numeric_secuencia, linea_credito, vigencia) VALUES
('000001-202501-03', 1, '03', YEAR(CURDATE()) * 100 + MONTH(CURDATE()));

-- =================================================================
-- VISTAS ÚTILES
-- =================================================================

-- Vista de Usuarios con Roles
CREATE VIEW vista_usuarios_completa AS
SELECT
    u.id,
    u.username,
    u.email,
    u.full_name,
    u.phone,
    u.roles,
    u.disabled,
    u.tipo_documento,
    u.numero_documento,
    u.nombres,
    u.apellidos,
    u.last_login,
    u.created_at,
    u.updated_at
FROM usuarios u;

-- Vista de Solicitudes con Detalles Completos
CREATE VIEW vista_solicitudes_detalle AS
SELECT
    sc.id,
    sc.numero_solicitud,
    sc.owner_username,
    sc.monto_solicitado,
    sc.monto_aprobado,
    sc.plazo_meses,
    sc.tasa_interes,
    sc.destino_credito,
    sc.descripcion,
    es.nombre as estado_nombre,
    es.color as estado_color,
    sc.created_at,
    sc.updated_at,
    -- Datos del solicitante
    ss.nombres_apellidos,
    ss.email as solicitante_email,
    ss.telefono_movil,
    ss.numero_identificacion,
    -- Contadores
    (SELECT COUNT(*) FROM solicitud_documentos sd WHERE sd.solicitud_id = sc.id AND sd.activo = TRUE) as documentos_count,
    (SELECT COUNT(*) FROM solicitud_timeline st WHERE st.solicitud_id = sc.id) as timeline_count,
    -- Empresa relacionada
    ss.empresa_nit,
    ss.empresa_razon_social
FROM solicitudes_credito sc
LEFT JOIN estados_solicitud es ON sc.estado_codigo = es.codigo
LEFT JOIN solicitud_solicitante ss ON sc.id = ss.solicitud_id;

-- Vista de Documentos de Solicitudes
CREATE VIEW vista_solicitudes_documentos AS
SELECT
    sd.id,
    sd.solicitud_id,
    sc.numero_solicitud,
    sd.documento_uuid,
    sd.documento_requerido_id,
    sd.nombre_original,
    sd.saved_filename,
    sd.tipo_mime,
    sd.tamano_bytes,
    sd.ruta_archivo,
    sd.activo,
    sd.created_at,
    sc.owner_username
FROM solicitud_documentos sd
JOIN solicitudes_credito sc ON sd.solicitud_id = sc.id;

-- Vista de Timeline de Solicitudes
CREATE VIEW vista_solicitudes_timeline AS
SELECT
    st.id,
    st.solicitud_id,
    sc.numero_solicitud,
    st.estado_codigo,
    es.nombre as estado_nombre,
    es.color as estado_color,
    st.fecha,
    st.detalle,
    st.usuario_username,
    u.full_name as usuario_nombre,
    st.automatico,
    st.created_at
FROM solicitud_timeline st
JOIN solicitudes_credito sc ON st.solicitud_id = sc.id
LEFT JOIN estados_solicitud es ON st.estado_codigo = es.codigo
LEFT JOIN usuarios u ON st.usuario_username = u.username
ORDER BY st.fecha DESC;

-- Vista de Payload de Solicitudes
CREATE VIEW vista_solicitudes_payload AS
SELECT
    sp.id,
    sp.solicitud_id,
    sc.numero_solicitud,
    sp.version,
    sp.encabezado,
    sp.solicitud,
    sp.producto_solicitado,
    sp.solicitante,
    sp.informacion_laboral,
    sp.ingresos_descuentos,
    sp.informacion_economica,
    sp.propiedades,
    sp.deudas,
    sp.referencias,
    sp.linea_credito,
    sp.save_xml,
    sp.created_at,
    sc.owner_username
FROM solicitud_payload sp
JOIN solicitudes_credito sc ON sp.solicitud_id = sc.id;

-- Vista de Estadísticas de Solicitudes
CREATE VIEW vista_estadisticas_solicitudes AS
SELECT
    es.codigo as estado_codigo,
    es.nombre as estado_nombre,
    es.color as estado_color,
    COUNT(sc.id) as cantidad,
    ROUND(COUNT(sc.id) * 100.0 / (SELECT COUNT(*) FROM solicitudes_credito), 2) as porcentaje,
    SUM(sc.monto_solicitado) as total_monto_solicitado,
    AVG(sc.monto_solicitado) as promedio_monto_solicitado
FROM estados_solicitud es
LEFT JOIN solicitudes_credito sc ON es.codigo = sc.estado_codigo
WHERE es.activo = TRUE
GROUP BY es.codigo, es.nombre, es.color
ORDER BY es.orden;

-- Vista de Solicitudes por Empresa
CREATE VIEW vista_solicitudes_empresa AS
SELECT
    ec.nit,
    ec.razon_social,
    ec.estado as empresa_estado,
    COUNT(sc.id) as cantidad_solicitudes,
    SUM(sc.monto_solicitado) as total_solicitado,
    AVG(sc.monto_solicitado) as promedio_solicitado,
    COUNT(CASE WHEN sc.estado_codigo = 'APROBADO' THEN 1 END) as aprobadas,
    COUNT(CASE WHEN sc.estado_codigo = 'RECHAZADO' THEN 1 END) as rechazadas
FROM empresas_convenio ec
LEFT JOIN solicitud_solicitante ss ON ec.nit = ss.empresa_nit
LEFT JOIN solicitudes_credito sc ON ss.solicitud_id = sc.id
GROUP BY ec.nit, ec.razon_social, ec.estado
ORDER BY cantidad_solicitudes DESC;

-- =================================================================
-- TRIGGERS
-- =================================================================

-- Trigger para generar número de solicitud automáticamente
DELIMITER //
CREATE TRIGGER generar_numero_solicitud
BEFORE INSERT ON solicitudes_credito
FOR EACH ROW
BEGIN
    DECLARE nuevo_numero INT;
    DECLARE anio_actual INT;

    SET anio_actual = YEAR(CURDATE());

    -- Obtener o crear secuencia para el año actual
    INSERT INTO secuencia_solicitudes (anio, ultimo_numero)
    VALUES (anio_actual, 0)
    ON DUPLICATE KEY UPDATE ultimo_numero = ultimo_numero + 1;

    -- Obtener el último número
    SELECT ultimo_numero + 1 INTO nuevo_numero
    FROM secuencia_solicitudes
    WHERE anio = anio_actual;

    -- Generar número de solicitud
    SET NEW.numero_solicitud = CONCAT(anio_actual, '-', LPAD(nuevo_numero, 6, '0'));

    -- Actualizar la secuencia
    UPDATE secuencia_solicitudes
    SET ultimo_numero = nuevo_numero
    WHERE anio = anio_actual;
END//
DELIMITER ;

-- Trigger para actualizar timeline cuando cambia el estado
DELIMITER //
CREATE TRIGGER actualizar_timeline_estado
AFTER UPDATE ON solicitudes_credito
FOR EACH ROW
BEGIN
    IF OLD.estado_codigo <> NEW.estado_codigo THEN
        INSERT INTO solicitud_timeline (solicitud_id, estado_codigo, detalle, usuario_username, automatico)
        VALUES (NEW.id, NEW.estado_codigo, 'Cambio de estado automático', NEW.owner_username, TRUE);
    END IF;
END//
DELIMITER ;

-- Trigger para crear registro inicial en timeline al crear solicitud
DELIMITER //
CREATE TRIGGER crear_timeline_inicial
AFTER INSERT ON solicitudes_credito
FOR EACH ROW
BEGIN
    INSERT INTO solicitud_timeline (solicitud_id, estado_codigo, detalle, usuario_username, automatico)
    VALUES (NEW.id, NEW.estado_codigo, 'Creación de solicitud', NEW.owner_username, TRUE);
END//
DELIMITER ;

-- =================================================================
-- PROCEDIMIENTOS ALMACENADOS
-- =================================================================

-- Procedimiento para obtener estadísticas generales
DELIMITER //
CREATE PROCEDURE sp_estadisticas_generales()
BEGIN
    -- Estadísticas de usuarios
    SELECT
        'usuarios' as tipo,
        COUNT(*) as total,
        COUNT(CASE WHEN disabled = FALSE THEN 1 END) as activos,
        COUNT(CASE WHEN disabled = TRUE THEN 1 END) as inactivos
    FROM usuarios

    UNION ALL

    -- Estadísticas de solicitudes
    SELECT
        'solicitudes' as tipo,
        COUNT(*) as total,
        COUNT(CASE WHEN estado_codigo IN ('POSTULADO', 'DOCUMENTOS_CARGADOS', 'ENVIADO_VALIDACION', 'PENDIENTE_FIRMADO', 'FIRMADO', 'ENVIADO_PENDIENTE_APROBACION') THEN 1 END) as activas,
        COUNT(CASE WHEN estado_codigo IN ('APROBADO', 'DESEMBOLSADO', 'FINALIZADO') THEN 1 END) as completadas
    FROM solicitudes_credito

    UNION ALL

    -- Estadísticas de empresas
    SELECT
        'empresas' as tipo,
        COUNT(*) as total,
        COUNT(CASE WHEN estado = 'Activo' THEN 1 END) as activas,
        COUNT(CASE WHEN estado != 'Activo' THEN 1 END) as inactivas
    FROM empresas_convenio;
END//
DELIMITER ;

-- =================================================================
-- COMENTARIOS FINALES
-- =================================================================

-- Este esquema relacional mantiene la integridad referencial y normalización
-- Se han convertido las estructuras de MongoDB a tablas relacionales
-- Se utilizan campos JSON para mantener flexibilidad donde sea necesario
-- Se incluyen índices para optimizar el rendimiento
-- Se agregan vistas, triggers y procedimientos para funcionalidad adicional

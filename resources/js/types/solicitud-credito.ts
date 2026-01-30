export type Moneda = 'COP';

export type RolEnSolicitud = 'solicitante' | 'codeudor';

export type TipoIdentificacion = 'CC' | 'CE';

export type ProductoTipo =
    | 'educacion'
    | 'salud'
    | 'vivienda'
    | 'electrodomesticos'
    | 'productos_hogar'
    | 'vestuario'
    | 'recreacion'
    | 'turismo';

export type Sexo = 'M' | 'F';

export type NivelEducativo =
    | 'primaria'
    | 'bachillerato'
    | 'tecnico'
    | 'universitario'
    | 'posgrado'
    | 'ninguno';

export type TipoVivienda = string | 'N' | 'F' | 'P' | 'A' | 'H';

export type TiempoServicioUnidad = 'meses' | 'anios';

export type TipoBien = 'vivienda' | 'vehiculo';

export interface WizardStep {
    key: string;
    title: string;
    short: string;
}

export interface WizardState {
    step: number;
    successModalOpen: boolean;
}

export interface SolicitudCreditoPayload {
    version: string;
    encabezado: {
        fecha_radicado: string;
    };
    solicitante: {
        tipo_identificacion: TipoIdentificacion;
        numero_identificacion: string;
        nombres: string;
        apellidos: string;
        sexo: Sexo;
        nivel_educativo: NivelEducativo;
        tipo_vivienda: TipoVivienda;
        direccion: string;
        telefono: string;
        email: string;
        empresa: {
            nombre: string;
            nit: string;
            direccion: string;
            telefono: string;
            ciudad: string;
        };
        cargo: string;
        salario: number;
        otros_ingresos: number;
        tiempo_servicio: {
            valor: number;
            unidad: TiempoServicioUnidad;
        };
    };
    codeudor?: {
        tipo_identificacion: TipoIdentificacion;
        numero_identificacion: string;
        nombres: string;
        apellidos: string;
        sexo: Sexo;
        nivel_educativo: NivelEducativo;
        tipo_vivienda: TipoVivienda;
        direccion: string;
        telefono: string;
        email: string;
        empresa: {
            nombre: string;
            nit: string;
            direccion: string;
            telefono: string;
            ciudad: string;
        };
        cargo: string;
        salario: number;
        otros_ingresos: number;
        tiempo_servicio: {
            valor: number;
            unidad: TiempoServicioUnidad;
        };
    };
    credito: {
        producto: ProductoTipo;
        monto: number;
        plazo: number;
        tasa: number;
        cuota_mensual: number;
        destino: string;
    };
    garantia?: {
        tipo: TipoBien;
        descripcion: string;
        valor: number;
        ubicacion: string;
    };
}

export interface SolicitudCredito {
    id: number;
    usuario_id: number;
    estado: string;
    fecha_radicado: string;
    datos: SolicitudCreditoPayload;
    created_at: string;
    updated_at: string;
}

export interface Convenio {
    id: number;
    nombre: string;
    descripcion: string;
    tasa_descuento: number;
    requisitos: string[];
    productos: ProductoTipo[];
    activo: boolean;
    created_at: string;
    updated_at: string;
}

export interface Documento {
    id: number;
    solicitud_id: number;
    tipo: string;
    nombre: string;
    ruta: string;
    tamaño: number;
    mime_type: string;
    estado: string;
    created_at: string;
    updated_at: string;
}

export interface DocumentoRequerido {
    id: string;
    tipo: string;
    nombre: string;
    descripcion: string;
    obligatorio: boolean;
    estado: string;
    fecha_limite?: string;
    formato_permitido: string[];
    tamano_maximo: number; // en bytes
}

export interface DocumentoCargado {
    id: string;
    solicitud_id: string;
    tipo: string;
    nombre: string;
    ruta: string;
    tamaño: number;
    mime_type: string;
    estado: string;
    fecha_subida: string;
    usuario_id: string;
    url_descarga?: string;
}

export interface UploadProgress {
    loaded: number;
    total: number;
    percentage: number;
}

export interface DocumentoValidation {
    valido: boolean;
    errores: string[];
    advertencias: string[];
}

export interface Firma {
    id: string;
    solicitud_id: string;
    usuario_id: string;
    rol: RolEnSolicitud;
    estado: string;
    fecha_firma?: string;
    ip_firma?: string;
    metodo_firma: string;
    created_at: string;
    updated_at: string;
}

export interface FirmadoIniciarResponse {
    success: boolean;
    data: {
        solicitud_id: string;
        transaccion_id: string;
        estado: string;
        urls_firma: Record<string, string>;
        firmantes: number;
        mensaje: string;
    };
    message: string;
}

export interface FirmadoEstadoResponse {
    success: boolean;
    data: {
        solicitud_id: string;
        transaccion_id: string;
        estado: string;
        firmantes_completados: number;
        firmantes_pendientes: number;
        fecha_consulta: string;
        firmantes?: Array<{
            id: string;
            nombre: string;
            email: string;
            rol: string;
            estado: string;
            fecha_firma?: string;
        }>;
    };
    message: string;
}

export type EstadoFirmado = 'PENDIENTE_FIRMADO' | 'FIRMADO' | 'RECHAZADO' | 'EXPIRADO';

export interface Firmante {
    id: string;
    nombre: string;
    email: string;
    rol: string;
    estado: string;
    fecha_firma?: string;
    url_firma?: string;
}

export interface ProcesoFirmado {
    solicitud_id: string;
    transaccion_id: string;
    estado: EstadoFirmado;
    firmantes: Firmante[];
    urls_firma: Record<string, string>;
    fecha_inicio: string;
    fecha_completado?: string;
}

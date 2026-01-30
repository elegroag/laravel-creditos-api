export type RolFirmante = 
    | 'solicitante'
    | 'codeudor'
    | 'garante'
    | 'representante_legal'
    | 'apoderado'
    | 'asesor'
    | 'administrador';

export type TipoIdentificacionFirma = 
    | 'CC'
    | 'CE'
    | 'NIT'
    | 'TI'
    | 'PP'
    | 'SS'
    | 'RC'
    | 'PASAPORTE';

export interface FirmaData {
    solicitud_filename: string;
    firmas_filename: string;
    rol_firmante: RolFirmante;
    aprobado: boolean;
    nombre_apellidos: string;
    tipo_identificacion: TipoIdentificacionFirma;
    numero_identificacion: string;
    fecha_firma: string;
    save_xml: boolean;
    clave_firma: string;
    clave_firma_confirm: string;
}

export interface FirmaDefaults {
    rol_firmante: RolFirmante;
    nombre_apellidos: string;
    tipo_identificacion: TipoIdentificacionFirma;
    numero_identificacion: string;
}

export interface FirmaResponse {
    success: boolean;
    message: string;
    data?: {
        firmas_filename: string;
        xml_content: string;
        firma_aplicada: boolean;
        timestamp: string;
        hash: string;
    };
}

export interface FirmaValidation {
    valida: boolean;
    mensaje: string;
    advertencias?: string[];
    timestamp: string;
    ip_address: string;
    dispositivo: string;
}

export interface FirmaShareToken {
    token: string;
    expires_at: string;
    firma_data: FirmaData;
    created_by: string;
    created_at: string;
    access_count: number;
    max_access: number;
}

export interface FirmaCompartir {
    email: string;
    rol_firmante: RolFirmante;
    mensaje?: string;
    expires_in_hours: number;
    max_access: number;
}

export interface FirmaCompartirResponse {
    success: boolean;
    message: string;
    data?: {
        share_url: string;
        token: string;
        expires_at: string;
    };
}

export interface FirmaStats {
    total_firmas: number;
    firmas_hoy: number;
    firmas_semana: number;
    firmas_mes: number;
    por_rol: Record<RolFirmante, number>;
    por_tipo_documento: Record<TipoIdentificacionFirma, number>;
    promedio_tiempo_firma: number;
    firmas_exitosas: number;
    firmas_fallidas: number;
}

export interface FirmaConfig {
    tiempo_expiracion_token: number; // en horas
    max_access_compartir: number;
    requerir_clave_firma: boolean;
    longitud_minima_clave: number;
    caracteres_especiales_requeridos: boolean;
    guardar_xml_automaticamente: boolean;
    formato_fecha: 'DD/MM/YYYY' | 'YYYY-MM-DD' | 'MM/DD/YYYY';
}

export interface FirmaLog {
    id: string;
    accion: string;
    firma_id: string;
    usuario: string;
    ip_address: string;
    dispositivo: string;
    timestamp: string;
    detalles?: any;
}

export interface FirmaTemplate {
    id: string;
    nombre: string;
    descripcion: string;
    template_xml: string;
    variables: Array<{
        nombre: string;
        tipo: 'string' | 'number' | 'date' | 'boolean';
        requerido: boolean;
        valor_defecto?: any;
    }>;
    rol_firmante: RolFirmante;
    activo: boolean;
    created_at: string;
    updated_at: string;
}

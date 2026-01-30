export type TipoIdentificacionExtendido = 
    | 'CC'
    | 'CE'
    | 'NIT'
    | 'TI'
    | 'PP'
    | 'SS'
    | 'RC'
    | 'CE'
    | 'PASAPORTE';

export interface EntidadDigitalData {
    tipo_identificacion: TipoIdentificacionExtendido;
    numero_identificacion: string;
    digito_verificacion: string;
    nombres: string;
    apellidos: string;
    email: string;
    telefono: string;
    direccion: string;
    ciudad: string;
    departamento: string;
    pais: string;
    actividad_economica: string;
    profesion: string;
    cargo: string;
    ingresos_mensuales: number;
    egresos_mensuales: number;
    patrimonio: number;
    activos: number;
    pasivos: number;
    fecha_constitucion: string;
    numero_matricula_mercantil?: string;
    representante_legal?: {
        tipo_identificacion: string;
        numero_identificacion: string;
        nombres: string;
        apellidos: string;
        email: string;
        telefono: string;
        cargo: string;
    };
}

export interface EntidadValidationResponse {
    valido: boolean;
    datos: EntidadDigitalData;
    mensaje: string;
    advertencias?: string[];
    score_confianza?: number;
}

export interface CapturaRequest {
    imagen: string;
    tipo_documento: string;
    numero_documento: string;
    tipo_identificacion: string;
    fecha_expiracion: string;
    callback_url?: string;
}

export interface CapturaResponse {
    session_id: string;
    qr_code: string;
    expires_at: string;
    callback_url?: string;
}

export interface VerificacionResponse {
    valido: boolean;
    datos: EntidadDigitalData;
    mensaje: string;
    timestamp: string;
    ip_address: string;
    dispositivo: string;
    user_agent: string;
}

export interface TokenValidationResponse {
    valido: boolean;
    expirado: boolean;
    tiempo_restante: number;
    timestamp: string;
}

export interface EntidadDigitalSession {
    session_id: string;
    datos: EntidadDigitalData;
    qr_code: string;
    expires_at: string;
    created_at: string;
    last_access: string;
    ip_address: string;
    dispositivo: string;
    user_agent: string;
}

export interface QrCodeState {
    url: string;
    loading: boolean;
    error: string | null;
    expired: boolean;
    timeRemaining: number;
    timeRemainingClass: string;
    isCapturasConfirmadas: boolean;
    socketResult: any;
}

export interface EntidadDigitalStats {
    total_validaciones: number;
    validaciones_exitosas: number;
    validaciones_fallidas: number;
    tipos_identificacion: Record<string, number>;
    ciudades_con_mas_validaciones: Array<{
        ciudad: string;
        cantidad: number;
    }>;
    promedio_tiempo_validacion: number;
}

export interface EntidadDigitalConfig {
    tiempo_expiracion_token: number; // en minutos
    intentos_maximos: number;
    tiempo_bloqueo: number; // en minutos
    requerir_captura: boolean;
    umbral_confianza: number; // score 0-100
    paises_permitidos: string[];
}

export interface Perfil {
    username: string;
    email: string;
    full_name: string;
    phone: string;
    tipo_documento: string;
    numero_documento: string;
    nombres: string;
    apellidos: string;
}

export interface PasswordData {
    password_actual: string;
    nueva_password: string;
    confirmar_password: string;
}

export interface PerfilUpdateData {
    username?: string;
    email?: string;
    full_name?: string;
    phone?: string;
    tipo_documento?: string;
    numero_documento?: string;
    nombres?: string;
    apellidos?: string;
}

export interface PerfilPreferences {
    tema: 'light' | 'dark' | 'auto';
    idioma: 'es' | 'en';
    notificaciones_email: boolean;
    notificaciones_push: boolean;
    formato_fecha: 'DD/MM/YYYY' | 'MM/DD/YYYY' | 'YYYY-MM-DD';
}

export interface PerfilSecurity {
    ultimo_login?: string;
    intentos_fallidos: number;
    fecha_bloqueo?: string;
    sesiones_activas: Array<{
        id: string;
        dispositivo: string;
        ip: string;
        fecha_inicio: string;
        ultimo_acceso: string;
        activa: boolean;
    }>;
}

export interface PerfilActivity {
    id: string;
    accion: string;
    descripcion: string;
    ip: string;
    dispositivo: string;
    fecha: string;
}

export interface PerfilStats {
    solicitudes_creadas: number;
    solicitudes_aprobadas: number;
    solicitudes_rechazadas: number;
    creditos_activos: number;
    total_prestado: number;
    ultima_solicitud?: string;
}

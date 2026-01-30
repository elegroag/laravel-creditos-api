export interface PuntoAsesoria {
    numero: string;
    oficina_afiliacion: string;
    estado: string;
    code_oficina: string;
    nombre_usuario: string;
    email: string;
}

export interface AdviserAuthResponse {
    access_token: string;
    token_type: string;
    expires_in: number;
    user: {
        username: string;
        roles: string[];
        permissions: string[];
        email: string;
        tipo_documento: string;
        numero_documento: string;
        nombres: string;
        apellidos: string;
        asesor?: {
            full_name: string;
            email: string;
            celular: string;
            codigo_funcionario: string;
            estado: string;
            tipo_funcionario: string;
        };
        trabajador?: any;
        puntos_asesorias?: PuntoAsesoria[];
    };
}

export interface AdviserUser {
    username: string;
    roles: string[];
    permissions: string[];
    email: string;
    tipo_documento: string;
    numero_documento: string;
    nombres: string;
    apellidos: string;
    asesor?: {
        full_name: string;
        email: string;
        celular: string;
        codigo_funcionario: string;
        estado: string;
        tipo_funcionario: string;
    };
    trabajador?: any;
    puntos_asesorias?: PuntoAsesoria[];
}

export interface AdviserProfile {
    codigo_funcionario: string;
    full_name: string;
    email: string;
    celular: string;
    estado: string;
    tipo_funcionario: string;
    puntos_asesorias: PuntoAsesoria[];
    estadisticas: {
        total_clientes: number;
        solicitudes_mes: number;
        solicitudes_aprobadas: number;
        tasa_aprobacion: number;
    };
}

export interface AdviserStats {
    total_asesores: number;
    asesores_activos: number;
    asesores_inactivos: number;
    total_clientes: number;
    solicitudes_mes: number;
    solicitudes_aprobadas: number;
    tasa_aprobacion_general: number;
    por_tipo_funcionario: Record<string, number>;
    por_estado: Record<string, number>;
}

export interface AdviserFilter {
    estado?: string;
    tipo_funcionario?: string;
    oficina_afiliacion?: string;
    search?: string;
}

export interface AdviserCreateForm {
    username: string;
    email: string;
    nombres: string;
    apellidos: string;
    tipo_documento: string;
    numero_documento: string;
    celular: string;
    tipo_funcionario: string;
    oficina_afiliacion: string;
    roles: string[];
    permissions: string[];
}

export interface AdviserUpdateForm {
    email?: string;
    nombres?: string;
    apellidos?: string;
    celular?: string;
    tipo_funcionario?: string;
    oficina_afiliacion?: string;
    estado?: string;
    roles?: string[];
    permissions?: string[];
}

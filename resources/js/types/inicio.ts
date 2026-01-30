export interface SolicitudResumen {
    id: string;
    numero_solicitud: string;
    estado: string;
    monto_solicitado: number;
    cuota_mensual: number;
    plazo_meses: number;
    producto: string;
    fecha_radicado: string;
    fecha_actualizacion: string;
    dias_en_estado: number;
    proximo_paso?: string;
    requiere_accion: boolean;
    documentos_pendientes: number;
    firmas_pendientes: number;
}

export interface EstadoSolicitud {
    nombre: string;
    descripcion: string;
    color: string;
    icono: string;
    requiere_accion: boolean;
    duracion_promedio_dias: number;
}

export interface EstadoSolicitudData {
    estado: string;
    count: number;
    avg_dias: number;
    total_monto: number;
}

export interface DashboardStats {
    solicitudes_totales: number;
    solicitudes_pendientes: number;
    solicitudes_aprobadas: number;
    solicitudes_rechazadas: number;
    monto_total_prestado: number;
    cuota_promedio: number;
    solicitudes_este_mes: number;
    aprobacion_rate: number;
}

export interface ActividadReciente {
    id: string;
    tipo: string;
    descripcion: string;
    fecha: string;
    usuario: string;
    solicitud_id?: string;
    numero_solicitud?: string;
}

export interface Notificacion {
    id: string;
    tipo: 'info' | 'success' | 'warning' | 'error';
    titulo: string;
    mensaje: string;
    fecha: string;
    leida: boolean;
    accion_url?: string;
    accion_texto?: string;
}

export interface QuickAction {
    id: string;
    titulo: string;
    descripcion: string;
    icono: string;
    url: string;
    color: string;
    disponible: boolean;
}

export interface ResumenFinanciero {
    total_prestado: number;
    total_recuperado: number;
    cartera_vigente: number;
    cartera_vencida: number;
    tasa_mora_promedio: number;
    proximos_pagos: Array<{
        solicitud_id: string;
        numero_solicitud: string;
        fecha_vencimiento: string;
        cuota: number;
        dias_para_vencer: number;
    }>;
}

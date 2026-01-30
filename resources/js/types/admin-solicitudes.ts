/**
 * Tipos para la administración de solicitudes
 */

export interface FiltrosSolicitudes {
    // Filtros de fecha
    fecha_desde?: string;
    fecha_hasta?: string;
    rango_fechas?: {
        inicio: string;
        fin: string;
    };

    // Filtros de usuario
    numero_documento?: string;
    nombre_usuario?: string;
    owner_username?: string;

    // Filtros de estado
    estados?: string[];

    // Filtros de solicitud
    numero_solicitud?: string;
    monto_minimo?: number;
    monto_maximo?: number;

    // Paginación
    skip?: number;
    limit?: number;

    // Ordenamiento
    ordenar_por?: string;
    orden_direccion?: 'asc' | 'desc';
}

export interface SolicitudAdmin {
    id: string;
    created_at: string;
    updated_at: string;
    estado: string;
    payload: {
        solicitante: {
            email: string;
            nombres_apellidos: string;
            numero_identificacion: string;
            telefono_movil: string;
            tipo_identificacion: string;
        };
        solicitud: {
            valor_solicitado?: number;
            cuota_mensual?: number;
            plazo_meses?: number;
            producto?: string;
        };
        credito?: {
            monto?: number;
            plazo?: number;
            tasa?: number;
            cuota_mensual?: number;
            destino?: string;
        };
    };
    owner: {
        id: string;
        username: string;
        email: string;
        nombres: string;
        apellidos: string;
    };
    numero_solicitud: string;
    monto_solicitado: number;
    cuota_mensual: number;
    plazo_meses: number;
    producto: string;
    fecha_radicado: string;
}

export interface EstadosCount {
    [estado: string]: number;
}

export interface OpcionesFiltro {
    estados: Array<{
        value: string;
        label: string;
    }>;
    usuarios: Array<{
        value: string;
        label: string;
    }>;
}

export interface CambioEstadoRequest {
    estado: string;
    descripcion?: string;
    notificar_usuario?: boolean;
}

export interface SolicitudDetalle extends SolicitudAdmin {
    historial_estados: Array<{
        estado: string;
        fecha: string;
        usuario: string;
        descripcion?: string;
    }>;
    documentos: Array<{
        id: string;
        tipo: string;
        nombre: string;
        ruta: string;
        tamaño: number;
        fecha_subida: string;
    }>;
    firmas: Array<{
        id: string;
        rol: string;
        estado: string;
        fecha_firma?: string;
        usuario: {
            nombres: string;
            apellidos: string;
        };
    }>;
}

export interface AccionesSolicitud {
    puede_cambiar_estado: boolean;
    puede_ver_detalle: boolean;
    puede_editar: boolean;
    puede_eliminar: boolean;
    puede_descargar: boolean;
    puede_firmar: boolean;
    estados_permitidos: string[];
}

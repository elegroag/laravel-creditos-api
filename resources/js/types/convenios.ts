export interface EmpresaConvenio {
    id: string;
    nit: string;
    razon_social: string;
    fecha_convenio: string;
    fecha_vencimiento: string;
    estado: string;
    representante_documento: string;
    representante_nombre: string;
    telefono: string;
    correo: string;
    createdAt: string;
    updatedAt: string;
}

export interface PaginationInfo {
    page: number;
    limit: number;
    total: number;
    total_pages: number;
    has_next: boolean;
    has_prev: boolean;
}

export interface ConveniosResponse {
    empresas: EmpresaConvenio[];
    pagination: PaginationInfo;
    conteo_estados: Record<string, number>;
}

export interface CreateConvenioForm {
    nit: string;
    razon_social: string;
    representante_documento: string;
    representante_nombre: string;
    telefono: string;
    correo: string;
    fecha_convenio: string;
    fecha_vencimiento: string;
    estado: string;
}

export interface UpdateConvenioForm extends Partial<CreateConvenioForm> {}

export interface ConvenioValidationRequest {
    nit: string;
    cedula_trabajador: string;
}

export interface ConvenioValidationResponse {
    valido: boolean;
    empresa?: EmpresaConvenio;
    trabajador?: {
        cedula: string;
        nombre_completo: string;
        estado: string;
        meses_servicio: number;
        fecha_afiliacion: string;
        salario: number;
        cargo: string;
        email: string;
    };
    mensaje: string;
}

export interface ConvenioStats {
    total_empresas: number;
    empresas_activas: number;
    empresas_vencidas: number;
    empresas_por_vencer: number;
    convenios_por_mes: Array<{
        mes: string;
        cantidad: number;
    }>;
}

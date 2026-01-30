export interface Usuario {
    id: string;
    username: string;
    nombres: string;
    apellidos: string;
    email: string;
    tipo_documento: string;
    numero_documento: string;
    rol: string;
    estado: string;
    ultimo_acceso: string;
    fecha_creacion: string;
    telefono?: string;
    codigo_categoria?: string;
    empresa_nit?: string;
    empresa_razon_social?: string;
    direccion?: string;
    ciudad?: string;
    barrio?: string;
    tipo_vivienda?: string;
    personas_a_cargo?: number;

    // Alias para compatibilidad con useShowUser
    nombre?: string;
    apellido?: string;
    tipo_identificacion?: string;
}

export interface Paginacion {
    limit: number;
    offset: number;
}

export interface CreateUserForm {
    // Información básica
    username: string;
    email: string;
    password: string;
    confirmPassword: string;
    roles: string[];
    disabled: boolean;

    // Datos personales
    nombre: string;
    apellido: string;
    tipo_documento: string;
    numero_documento: string;
    phone: string;
}

// Alias para EditUserForm - usa la misma estructura que CreateUserForm
export type EditUserForm = CreateUserForm;

export interface Empresa {
    ciudad_codigo: string;
    direccion: string;
    nit: string;
    razon_social: string;
    telefono: string;
}

export interface Trabajador {
    cargo: string;
    cedula: string;
    ciudad_codigo: string;
    ciudad_nacimiento: string;
    direccion: string;
    email: string;
    empresa?: Empresa | null;
    estado: string;
    estado_civil: string;
    fecha_afiliacion: string;
    fecha_nacimiento: string;
    fecha_salario: string;
    nivel_educativo: string;
    primer_apellido: string;
    primer_nombre: string;
    salario: number;
    segundo_apellido: string;
    segundo_nombre: string;
    sexo: string;
    telefono: string;
    tipo_documento: string;
    codigo_categoria?: string;
    tipo_vivienda?: string;
}

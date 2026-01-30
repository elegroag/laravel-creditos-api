export interface LoginData {
    username: string;
    password: string;
}

export interface RegistroData {
    tipo_documento: string;
    numero_documento: string;
    nombres: string;
    apellidos: string;
    telefono: string;
    email: string;
    username: string;
    password: string;
    confirmar_password: string;
}

export interface AuthResponse {
    access_token: string;
    token_type: string;
    user: SessionUser;
}

export interface SessionUser {
    id: number;
    username: string;
    roles: string[];
    permissions: string[];
    email: string;
    tipo_documento: string;
    numero_documento: string;
    nombres: string;
    apellidos: string;
    full_name: string;
    adviser_number?: string;
    asesor?: {
        full_name: string;
        email: string;
        celular: string;
        codigo_funcionario: string;
        estado: string;
        tipo_funcionario: string;
    };
    trabajador?: Trabajador | null;
    selected_punto?: PuntoAsesoria | null;
}

export interface SessionData {
    accessToken: string;
    tokenType: string;
    user: SessionUser | null;
}

export interface PuntoAsesoria {
    id: number;
    nombre: string;
    direccion: string;
    telefono: string;
    ciudad: string;
    estado: string;
}

// Importar tipos de trabajador para evitar duplicación
import type { Trabajador } from './trabajador';

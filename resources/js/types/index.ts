// Exportar todos los tipos desde un solo lugar
export * from './auth';
export * from './trabajador';
export * from './admin';
export * from './admin-solicitudes';
export * from './convenios';
export * from './simulador';
export * from './solicitud-credito';
export * from './perfil';
export * from './inicio';
export * from './layout';
export * from './entidad';
export * from './firmas';
export * from './xml';
export * from './conyuges';
export * from './adviser';
export * from './parametros';

// Tipos adicionales que se irán agregando
export interface ApiResponse<T = any> {
    data: T;
    message?: string;
    status: number;
}

export interface PaginatedResponse<T = any> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

export interface TableColumn {
    key: string;
    label: string;
    sortable?: boolean;
    width?: string;
}

export interface FilterOption {
    key: string;
    label: string;
    value: any;
    type: 'text' | 'select' | 'date' | 'number';
    options?: SelectOption[];
}

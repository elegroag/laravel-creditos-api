export type AuxiliarContable = {
    auxiliar: string;
    auxold: string | null;
    banco: string;
    centros: string;
    detalla: string;
    detalle: string;
    estado: string;
    tercero: string;
};

export type CentroCosto = {
    codcen: string;
    detalle: string;
};

export type DatoPeriodo = {
    ano: number;
    cierre: string;
    mes: number;
};

export type DatoGeneralCredito = {
    asejur: string;
    audtra: string;
    auxcaj: string;
    auxcas: string | null;
    auxexc: string;
    auxfonreg: string;
    auxmay: string;
    auxpigsub: string;
    carasejur: string;
    cardiradm: string;
    carjefadm: string;
    carjefcre: string;
    carjeffin: string;
    cnt: string;
    codapl: string;
    codcen: string;
    codcop: string;
    codgardef: string;
    consecutivo: number;
    conta: string;
    control: string;
    cueban: string | null;
    cuomax: number | null;
    diradm: string;
    facfonreg: string;
    forpagdef: string;
    jefadm: string;
    jefcre: string;
    jeffin: string;
    maxcre: number | null;
    numfac: string;
    numres: string;
    percap: string;
    percon: string;
    pergar: string;
    perint: string;
    perliq: string;
    perpre: string;
    perres: string;
    perseg: string;
    perusu: string;
    placre: number | null;
    segcre: string;
    segres: string;
    tipcre: string;
    tipcredef: string;
    tipcregar: string;
    tipcreliq: string;
    tipcrepre: string;
    tipcreseg: string;
    tipcreusu: string;
    tipres: string;
    tipusu: string;
    usucre: string;
    usures: string;
    ususeg: string;
    usuusu: string;
};

export type ParametroSistema = {
    id: string;
    clave: string;
    valor: string;
    descripcion: string;
    tipo: 'string' | 'number' | 'boolean' | 'json';
    categoria: string;
    editable: boolean;
    created_at: string;
    updated_at: string;
};

export type ConfiguracionGeneral = {
    empresa_nombre: string;
    empresa_nit: string;
    empresa_direccion: string;
    empresa_telefono: string;
    empresa_email: string;
    sistema_version: string;
    sistema_ambiente: 'development' | 'staging' | 'production';
    moneda_principal: string;
    formato_fecha: string;
    timezone: string;
    idioma: string;
};

export type ConfiguracionCredito = {
    tasa_interes_default: number;
    plazo_maximo_meses: number;
    monto_maximo_credito: number;
    monto_minimo_credito: number;
    cuota_minima: number;
    porcentaje_seguro: number;
    porcentaje_administracion: number;
    dias_gracia: number;
    frecuencia_pago: 'mensual' | 'quincenal' | 'semanal';
};

export type ConfiguracionNotificaciones = {
    email_enabled: boolean;
    sms_enabled: boolean;
    email_host: string;
    email_port: number;
    email_username: string;
    email_password: string;
    email_from: string;
    sms_provider: string;
    sms_api_key: string;
    sms_from: string;
};

export type ConfiguracionSeguridad = {
    password_min_length: number;
    password_require_uppercase: boolean;
    password_require_lowercase: boolean;
    password_require_numbers: boolean;
    password_require_symbols: boolean;
    session_timeout_minutes: number;
    max_login_attempts: number;
    lockout_duration_minutes: number;
    two_factor_enabled: boolean;
};

export type ConfiguracionIntegraciones = {
    api_bancaria_enabled: boolean;
    api_bancaria_url: string;
    api_bancaria_key: string;
    api_centrales_riesgo_enabled: boolean;
    api_centrales_riesgo_url: string;
    api_centrales_riesgo_key: string;
    api_notificaciones_enabled: boolean;
    api_notificaciones_url: string;
    api_notificaciones_key: string;
};

export type ParametroResponse = {
    success: boolean;
    data: ParametroSistema[];
    message?: string;
};

export type ParametroCreateForm = {
    clave: string;
    valor: string;
    descripcion: string;
    tipo: 'string' | 'number' | 'boolean' | 'json';
    categoria: string;
    editable: boolean;
};

export type ParametroUpdateForm = {
    valor?: string;
    descripcion?: string;
    editable?: boolean;
};

export type ParametroStats = {
    total_parametros: number;
    por_categoria: Record<string, number>;
    por_tipo: Record<string, number>;
    parametros_editables: number;
    parametros_no_editables: number;
    ultima_actualizacion: string;
};

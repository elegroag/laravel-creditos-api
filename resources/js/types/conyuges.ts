export interface ConyugeResponse {
    success: boolean;
    exception: number;
    data: ConyugeData[];
}

export interface ConyugeData {
    cedcon: string;
    coddoc: string;
    priape: string;
    segape: string;
    prinom: string;
    segnom: string;
    direccion: string;
    telefono: string;
    email: string;
    codzon: string;
    codcaj: string | null;
    codocu: string;
    nivedu: string;
    captra: string;
    salario: number;
    tipsal: string;
    fecsal: string;
    tippag: string;
    codcue: string;
    ofides: string;
    codgru: string;
    codban: string;
    numcue: string;
    tipcue: string;
    sexo: string;
    estciv: string;
    fecnac: string;
    ciunac: string;
    estado: string;
    fecest: string | null;
    numtar: string;
    giass: string;
    usuario: string;
    fecact: string;
    nombre: string;
    cedtra: string;
    comper: string;
    fecafi: string;
}

export interface ConyugeForm {
    cedcon: string;
    coddoc: string;
    priape: string;
    segape: string;
    prinom: string;
    segnom: string;
    direccion: string;
    telefono: string;
    email: string;
    codzon: string;
    codocu: string;
    nivedu: string;
    captra: string;
    salario: number;
    tipsal: string;
    fecsal: string;
    tippag: string;
    codcue: string;
    ofides: string;
    codgru: string;
    codban: string;
    numcue: string;
    tipcue: string;
    sexo: string;
    estciv: string;
    fecnac: string;
    ciunac: string;
    numtar: string;
    giass: string;
}

export interface ConyugeValidation {
    valid: boolean;
    errors: string[];
    warnings: string[];
}

export interface ConyugeStats {
    total_conyuges: number;
    conyuges_activos: number;
    conyuges_inactivos: number;
    promedio_salario: number;
    porcentaje_completos: number;
    por_estado: Record<string, number>;
    por_nivel_educativo: Record<string, number>;
}

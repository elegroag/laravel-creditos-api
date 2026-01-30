// Formateadores de moneda y números
export const formatCurrency = (value: number): string => {
    return value.toLocaleString('es-CO');
};

export const formatCurrencyIntl = (value: number): string => {
    return new Intl.NumberFormat('es-CO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
};

export const formatCurrencyWithSymbol = (value: number): string => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
};

export const formatCurrencyWithDecimals = (value: number, decimals: number = 2): string => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(value);
};

// Formateadores de fechas
export const formatDate = (dateString: string): string => {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-CO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    } catch (error) {
        return dateString;
    }
};

export const formatDateLong = (dateString: string): string => {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-CO', {
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });
    } catch (error) {
        return dateString;
    }
};

export const formatDateTime = (dateString: string): string => {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-CO', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return dateString;
    }
};

export const formatTime = (dateString: string): string => {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleTimeString('es-CO', {
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return dateString;
    }
};

export const formatDateRelative = (dateString: string): string => {
    if (!dateString) return '';
    
    try {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
        
        if (diffInSeconds < 60) {
            return 'hace un momento';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `hace ${minutes} minuto${minutes > 1 ? 's' : ''}`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `hace ${hours} hora${hours > 1 ? 's' : ''}`;
        } else if (diffInSeconds < 604800) {
            const days = Math.floor(diffInSeconds / 86400);
            return `hace ${days} día${days > 1 ? 's' : ''}`;
        } else {
            return formatDate(dateString);
        }
    } catch (error) {
        return dateString;
    }
};

// Formateador general de valores
export const formatValue = (value: any): string => {
    if (value === null || value === undefined || value === '') {
        return 'N/A';
    }

    if (typeof value === 'boolean') {
        return value ? 'Sí' : 'No';
    }

    if (typeof value === 'number') {
        return formatCurrencyWithSymbol(value);
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
};

// Formateador de claves de revisión
export const formatRevisionKey = (key: string): string => {
    const keyMap: Record<string, string> = {
        'linea_credito': 'Línea de Crédito',
        'tipo_credito': 'Tipo Crédito',
        'modelo_xml4': 'Modelo XML4',
        'codigo_cre': 'Código Cre',
        'codigo_cap': 'Código Cap',
        'codigo_ser': 'Código Ser',
        'numero_cuotas': 'Número Cuotas',
        'estado': 'Estado',
        'monto_simulado': 'Monto Simulado',
        'plazo_meses': 'Plazo Meses',
        'tasa_anual': 'Tasa Anual',
        'cuota_mensual': 'Cuota Mensual',
        'fecha_radicado': 'Fecha Radicado',
        'valor_solicitud': 'Valor Solicitud',
        'categoria': 'Categoría',
        'tipcre': 'Tipo Crédito',
        'modxml4': 'Modelo XML4',
        'detalle_modalidad': 'Modalidad de Crédito',
        'nombres_apellidos': 'Nombres y Apellidos',
        'tipo_identificacion': 'Tipo Identificación',
        'numero_identificacion': 'Número Identificación',
        'fecha_nacimiento': 'Fecha Nacimiento',
        'telefono_movil': 'Teléfono',
        'email': 'Email',
        'barrio_residencia': 'Dirección',
        'ciudad_residencia': 'Ciudad Residencia',
        'empresa_razon_social': 'Empresa',
        'empresa_nit': 'NIT',
        'cargo': 'Cargo',
        'fecha_ingreso': 'Fecha Ingreso',
        'salario_basico_mensual': 'Salario Básico',
        'subsidio_transporte': 'Subsidio Transporte',
        'salud_pension': 'Salud y Pensión'
    };

    return keyMap[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

// Formateador de índices de revisión
export const formatRevisionIndex = (index: number | string): string => {
    const numIndex = typeof index === 'string' ? parseInt(index, 10) : index;
    return String(numIndex + 1);
};

// Formateador de documentos de identidad
export const formatIdentificacion = (tipo: string, numero: string): string => {
    if (!numero) return '';
    
    const cleanNumero = numero.replace(/[^\d]/g, '');
    
    switch (tipo.toUpperCase()) {
        case 'NIT':
            // Formateo NIT: XXX.XXX.XXX-X
            if (cleanNumero.length <= 8) {
                return cleanNumero;
            }
            const digitoVerificador = cleanNumero.slice(-1);
            const base = cleanNumero.slice(0, -1);
            const formattedBase = base.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
            return `${formattedBase}-${digitoVerificador}`;
            
        case 'CC':
        case 'CE':
            // Formateo cédula: XXX.XXX.XXX
            if (cleanNumero.length <= 6) {
                return cleanNumero;
            }
            return cleanNumero.replace(/(\d{3})(\d{3})(\d{3})/, '$1.$2.$3');
            
        case 'TI':
            // Formateo tarjeta de identidad: XXX.XXX
            if (cleanNumero.length <= 6) {
                return cleanNumero;
            }
            return cleanNumero.replace(/(\d{3})(\d{3})/, '$1.$2');
            
        default:
            return numero;
    }
};

// Formateador de teléfonos
export const formatTelefono = (telefono: string): string => {
    if (!telefono) return '';
    
    const cleanTelefono = telefono.replace(/[^\d]/g, '');
    
    if (cleanTelefono.length === 7) {
        // Teléfono fijo local: XXX XXXX
        return cleanTelefono.replace(/(\d{3})(\d{4})/, '$1 $2');
    } else if (cleanTelefono.length === 10) {
        // Celular: XXX XXX XXXX
        return cleanTelefono.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3');
    }
    
    return telefono;
};

// Formateador de porcentajes
export const formatPercentage = (value: number, decimals: number = 2): string => {
    return `${(value * 100).toFixed(decimals)}%`;
};

// Formateador de números grandes
export const formatLargeNumber = (value: number): string => {
    if (value >= 1000000000) {
        return `${(value / 1000000000).toFixed(1)}B`;
    } else if (value >= 1000000) {
        return `${(value / 1000000).toFixed(1)}M`;
    } else if (value >= 1000) {
        return `${(value / 1000).toFixed(1)}K`;
    }
    return value.toString();
};

// Formateador de tamaño de archivos
export const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Formateador de duración
export const formatDuration = (seconds: number): string => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    
    if (hours > 0) {
        return `${hours}h ${minutes}m ${remainingSeconds}s`;
    } else if (minutes > 0) {
        return `${minutes}m ${remainingSeconds}s`;
    } else {
        return `${remainingSeconds}s`;
    }
};

// Formateador de estado
export const formatEstado = (estado: string): string => {
    const estadoMap: Record<string, string> = {
        'A': 'Activo',
        'I': 'Inactivo',
        'P': 'Pendiente',
        'R': 'Rechazado',
        'C': 'Cancelado',
        'E': 'En proceso',
        'D': 'Desactivado',
        'S': 'Suspendido'
    };
    
    return estadoMap[estado.toUpperCase()] || estado;
};

// Formateador de género
export const formatGenero = (genero: string): string => {
    const generoMap: Record<string, string> = {
        'M': 'Masculino',
        'F': 'Femenino',
        'O': 'Otro'
    };
    
    return generoMap[genero.toUpperCase()] || genero;
};

// Formateador de estado civil
export const formatEstadoCivil = (estadoCivil: string): string => {
    const estadoCivilMap: Record<string, string> = {
        'S': 'Soltero/a',
        'C': 'Casado/a',
        'D': 'Divorciado/a',
        'V': 'Viudo/a',
        'U': 'Unión libre'
    };
    
    return estadoCivilMap[estadoCivil.toUpperCase()] || estadoCivil;
};

// Formateador de nivel educativo
export const formatNivelEducativo = (nivel: string): string => {
    const nivelMap: Record<string, string> = {
        'P': 'Primaria',
        'S': 'Secundaria',
        'T': 'Técnico',
        'U': 'Universitario',
        'G': 'Posgrado'
    };
    
    return nivelMap[nivel.toUpperCase()] || nivel;
};

// Formateador de tipo de contrato
export const formatTipoContrato = (tipo: string): string => {
    const tipoMap: Record<string, string> = {
        'I': 'Indefinido',
        'F': 'Fijo',
        'O': 'Obra o labor',
        'A': 'Aprendizaje',
        'P': 'Práctica'
    };
    
    return tipoMap[tipo.toUpperCase()] || tipo;
};

// Formateador de frecuencia de pago
export const formatFrecuenciaPago = (frecuencia: string): string => {
    const frecuenciaMap: Record<string, string> = {
        'M': 'Mensual',
        'Q': 'Quincenal',
        'S': 'Semanal',
        'D': 'Diario'
    };
    
    return frecuenciaMap[frecuencia.toUpperCase()] || frecuencia;
};

// Formateador de método de pago
export const formatMetodoPago = (metodo: string): string => {
    const metodoMap: Record<string, string> = {
        'E': 'Efectivo',
        'T': 'Transferencia',
        'D': 'Débito automático',
        'C': 'Tarjeta de crédito'
    };
    
    return metodoMap[metodo.toUpperCase()] || metodo;
};

// Formateador de tipo de cuenta bancaria
export const formatTipoCuenta = (tipo: string): string => {
    const tipoMap: Record<string, string> = {
        'A': 'Ahorros',
        'C': 'Corriente'
    };
    
    return tipoMap[tipo.toUpperCase()] || tipo;
};

// Formateador de número de cuenta bancaria
export const formatCuentaBancaria = (cuenta: string): string => {
    if (!cuenta) return '';
    
    const cleanCuenta = cuenta.replace(/[^\d]/g, '');
    
    // Formateo común para cuentas colombianas: XXXX-XXXX-XXXX-XXXX
    if (cleanCuenta.length >= 16) {
        return cleanCuenta.replace(/(\d{4})(\d{4})(\d{4})(\d{4})/, '$1-$2-$3-$4');
    } else if (cleanCuenta.length >= 12) {
        return cleanCuenta.replace(/(\d{4})(\d{4})(\d{4})/, '$1-$2-$3');
    } else if (cleanCuenta.length >= 8) {
        return cleanCuenta.replace(/(\d{4})(\d{4})/, '$1-$2');
    }
    
    return cuenta;
};

// Formateador de dirección
export const formatDireccion = (direccion: string): string => {
    if (!direccion) return '';
    
    return direccion
        .replace(/\bCalle\b/gi, 'Cll')
        .replace(/\bCarrera\b/gi, 'Cr')
        .replace(/\bAvenida\b/gi, 'Av')
        .replace(/\bTransversal\b/gi, 'Tv')
        .replace(/\bDiagonal\b/gi, 'Dg')
        .replace(/\#/, '#');
};

// Formateador de texto (capitalización)
export const formatText = (text: string): string => {
    if (!text) return '';
    
    return text
        .toLowerCase()
        .split(' ')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

// Formateador de texto con preposiciones
export const formatTextWithPrepositions = (text: string): string => {
    if (!text) return '';
    
    const prepositions = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'o', 'con', 'por', 'para', 'en', 'a'];
    
    return text
        .toLowerCase()
        .split(' ')
        .map((word, index) => {
            if (index === 0 || !prepositions.includes(word)) {
                return word.charAt(0).toUpperCase() + word.slice(1);
            }
            return word;
        })
        .join(' ');
};

// Formateador de texto para mayúsculas
export const formatUpperCase = (text: string): string => {
    return text ? text.toUpperCase() : '';
};

// Formateador de texto para minúsculas
export const formatLowerCase = (text: string): string => {
    return text ? text.toLowerCase() : '';
};

// Formateador de texto para título
export const formatTitleCase = (text: string): string => {
    if (!text) return '';
    
    return text.replace(/\w\S*/g, (txt) => 
        txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
    );
};

// Formateador de texto truncado
export const formatTruncate = (text: string, maxLength: number = 50): string => {
    if (!text || text.length <= maxLength) return text;
    
    return text.substring(0, maxLength) + '...';
};

// Formateador de texto con puntos suspensivos
export const formatEllipsis = (text: string, maxLength: number = 50): string => {
    if (!text || text.length <= maxLength) return text;
    
    return text.substring(0, maxLength - 3) + '...';
};

// Formateador de texto para slug
export const formatSlug = (text: string): string => {
    return text
        .toLowerCase()
        .replace(/[^\w ]+/g, '')
        .replace(/ +/g, '-');
};

// Formateador de texto para URL amigable
export const formatUrlFriendly = (text: string): string => {
    return text
        .toLowerCase()
        .replace(/[áàäâ]/g, 'a')
        .replace(/[éèëê]/g, 'e')
        .replace(/[íìïî]/g, 'i')
        .replace(/[óòöô]/g, 'o')
        .replace(/[úùüû]/g, 'u')
        .replace(/[ñ]/g, 'n')
        .replace(/[^\w ]+/g, '')
        .replace(/ +/g, '-');
};

// Formateador de texto para búsqueda
export const formatSearchText = (text: string): string => {
    return text
        .toLowerCase()
        .replace(/[áàäâ]/g, 'a')
        .replace(/[éèëê]/g, 'e')
        .replace(/[íìïî]/g, 'i')
        .replace(/[óòöô]/g, 'o')
        .replace(/[úùüû]/g, 'u')
        .replace(/[ñ]/g, 'n')
        .replace(/[^\w\s]/g, '')
        .trim();
};

// Formateador de texto para validación
export const formatValidationText = (text: string): string => {
    return text
        .replace(/[^a-zA-Z0-9\s]/g, '')
        .trim();
};

// Formateador de texto para números de teléfono internacional
export const formatTelefonoInternacional = (telefono: string, codigoPais: string = '+57'): string => {
    if (!telefono) return '';
    
    const cleanTelefono = telefono.replace(/[^\d]/g, '');
    
    if (cleanTelefono.length === 10) {
        return `${codigoPais} ${cleanTelefono.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3')}`;
    }
    
    return telefono;
};

// Formateador de texto para código postal
export const formatCodigoPostal = (codigo: string): string => {
    if (!codigo) return '';
    
    const cleanCodigo = codigo.replace(/[^\d]/g, '');
    
    if (cleanCodigo.length === 6) {
        return cleanCodigo.replace(/(\d{3})(\d{3})/, '$1-$2');
    }
    
    return codigo;
};

// Formateador de texto para RFC (México)
export const formatRFC = (rfc: string): string => {
    if (!rfc) return '';
    
    const cleanRFC = rfc.toUpperCase().replace(/[^A-Z0-9]/g, '');
    
    if (cleanRFC.length === 12) {
        return cleanRFC.replace(/([A-Z]{3})(\d{6})([A-Z0-9]{3})/, '$1-$2-$3');
    } else if (cleanRFC.length === 13) {
        return cleanRFC.replace(/([A-Z]{4})(\d{6})([A-Z0-9]{3})/, '$1-$2-$3');
    }
    
    return rfc;
};

// Formateador de texto para CURP (México)
export const formatCURP = (curp: string): string => {
    if (!curp) return '';
    
    const cleanCURP = curp.toUpperCase().replace(/[^A-Z0-9]/g, '');
    
    if (cleanCURP.length === 18) {
        return cleanCURP.replace(/([A-Z]{4})(\d{6})([A-Z]{6})([A-Z0-9]{2})/, '$1-$2-$3-$4');
    }
    
    return curp;
};

// Exportación por defecto con todos los formateadores
export default {
    // Moneda y números
    formatCurrency,
    formatCurrencyIntl,
    formatCurrencyWithSymbol,
    formatCurrencyWithDecimals,
    
    // Fechas
    formatDate,
    formatDateLong,
    formatDateTime,
    formatTime,
    formatDateRelative,
    
    // General
    formatValue,
    formatRevisionKey,
    formatRevisionIndex,
    
    // Documentos y datos personales
    formatIdentificacion,
    formatTelefono,
    formatTelefonoInternacional,
    formatCuentaBancaria,
    
    // Porcentajes y números grandes
    formatPercentage,
    formatLargeNumber,
    formatFileSize,
    formatDuration,
    
    // Estados y clasificaciones
    formatEstado,
    formatGenero,
    formatEstadoCivil,
    formatNivelEducativo,
    formatTipoContrato,
    formatFrecuenciaPago,
    formatMetodoPago,
    formatTipoCuenta,
    
    // Dirección y ubicación
    formatDireccion,
    formatCodigoPostal,
    
    // Texto
    formatText,
    formatTextWithPrepositions,
    formatUpperCase,
    formatLowerCase,
    formatTitleCase,
    formatTruncate,
    formatEllipsis,
    formatSlug,
    formatUrlFriendly,
    formatSearchText,
    formatValidationText,
    
    // Internacionales
    formatRFC,
    formatCURP
};

import { ref, computed } from 'vue';
import type {
    SolicitudCreditoPayload,
    RolEnSolicitud,
    ProductoTipo,
    TipoIdentificacion,
    Sexo,
    NivelEducativo,
    TipoVivienda,
    TiempoServicioUnidad,
    TipoBien
} from '@/types/solicitud-credito';

export const useSolicitudCreditoForm = () => {
    // Formulario principal
    const form = ref<SolicitudCreditoPayload>({
        version: '1.0',
        encabezado: {
            fecha_radicado: new Date().toISOString().split('T')[0],
        },
        solicitante: {
            tipo_identificacion: 'CC' as TipoIdentificacion,
            numero_identificacion: '',
            nombres: '',
            apellidos: '',
            sexo: 'M' as Sexo,
            nivel_educativo: 'bachillerato' as NivelEducativo,
            tipo_vivienda: 'F' as TipoVivienda,
            direccion: '',
            telefono: '',
            email: '',
            empresa: {
                nombre: '',
                nit: '',
                direccion: '',
                telefono: '',
                ciudad: '',
            },
            cargo: '',
            salario: 0,
            otros_ingresos: 0,
            tiempo_servicio: {
                valor: 0,
                unidad: 'meses' as TiempoServicioUnidad,
            },
        },
        credito: {
            producto: 'vivienda' as ProductoTipo,
            monto: 0,
            plazo: 0,
            tasa: 0,
            cuota_mensual: 0,
            destino: '',
        },
    });

    // Estado del formulario
    const tieneConyuge = ref(false);
    const conyugeTrabajaMismaEmpresa = ref(false);
    const loading = ref(false);
    const error = ref<string | null>(null);

    // Propiedades adicionales
    const propiedades = ref<Array<{
        tipo: string;
        direccion: string;
        valor: number;
        ownership: string;
    }>>([]);

    const deudas = ref<Array<{
        tipo: string;
        entidad: string;
        valor: number;
        cuota_mensual: number;
    }>>([]);

    const referencias = ref<Array<{
        tipo: string;
        nombres: string;
        apellidos: string;
        telefono: string;
        parentesco: string;
    }>>([]);

    // Computed properties
    const ingresosTotales = computed(() => {
        return form.value.solicitante.salario + form.value.solicitante.otros_ingresos;
    });

    const tienePropiedades = computed(() => propiedades.value.length > 0);
    const tieneDeudas = computed(() => deudas.value.length > 0);
    const tieneReferencias = computed(() => referencias.value.length >= 2);

    // Validaciones
    const validarSolicitante = computed(() => {
        const s = form.value.solicitante;
        return s.tipo_identificacion &&
            s.numero_identificacion &&
            s.nombres &&
            s.apellidos &&
            s.email &&
            s.telefono &&
            s.direccion &&
            s.empresa.nombre &&
            s.cargo &&
            s.salario > 0;
    });

    const validarCredito = computed(() => {
        const c = form.value.credito;
        return c.producto &&
            c.monto > 0 &&
            c.plazo > 0 &&
            c.destino;
    });

    const formularioValido = computed(() => {
        return validarSolicitante.value && validarCredito.value;
    });

    // Métodos para manipular el formulario
    const toggleConyuge = () => {
        tieneConyuge.value = !tieneConyuge.value;
        if (!tieneConyuge.value) {
            // Limpiar datos de cónyuge si existen
            delete form.value.codeudor;
        }
    };

    const toggleEmpresaConyuge = () => {
        conyugeTrabajaMismaEmpresa.value = !conyugeTrabajaMismaEmpresa.value;
        if (conyugeTrabajaMismaEmpresa.value && form.value.codeudor) {
            // Copiar datos de empresa del solicitante al cónyuge
            form.value.codeudor!.empresa = { ...form.value.solicitante.empresa };
        }
    };

    const autocalcularIngresos = () => {
        // Lógica para autocalcular ingresos basados en salario y otros factores
        const salario = form.value.solicitante.salario;
        const otrosIngresos = Math.max(0, salario * 0.1); // 10% del salario como otros ingresos
        form.value.solicitante.otros_ingresos = otrosIngresos;
    };

    const actualizarCuotaMensual = (cuota: number) => {
        form.value.credito.cuota_mensual = cuota;
    };

    const actualizarTasa = (tasa: number) => {
        form.value.credito.tasa = tasa;
    };

    // Métodos para propiedades
    const addPropiedad = () => {
        propiedades.value.push({
            tipo: '',
            direccion: '',
            valor: 0,
            ownership: 'propio',
        });
    };

    const removePropiedad = (index: number) => {
        propiedades.value.splice(index, 1);
    };

    const updatePropiedad = (index: number, field: string, value: any) => {
        if (propiedades.value[index]) {
            propiedades.value[index][field] = value;
        }
    };

    // Métodos para deudas
    const addDeuda = () => {
        deudas.value.push({
            tipo: '',
            entidad: '',
            valor: 0,
            cuota_mensual: 0,
        });
    };

    const removeDeuda = (index: number) => {
        deudas.value.splice(index, 1);
    };

    const updateDeuda = (index: number, field: string, value: any) => {
        if (deudas.value[index]) {
            deudas.value[index][field] = value;
        }
    };

    // Métodos para referencias
    const addReferencia = () => {
        referencias.value.push({
            tipo: 'personal',
            nombres: '',
            apellidos: '',
            telefono: '',
            parentesco: '',
        });
    };

    const removeReferencia = (index: number) => {
        referencias.value.splice(index, 1);
    };

    const updateReferencia = (index: number, field: string, value: any) => {
        if (referencias.value[index]) {
            referencias.value[index][field] = value;
        }
    };

    // Métodos de utilidad
    const resetearFormulario = () => {
        form.value = {
            version: '1.0',
            encabezado: {
                fecha_radicado: new Date().toISOString().split('T')[0],
            },
            solicitante: {
                tipo_identificacion: 'CC' as TipoIdentificacion,
                numero_identificacion: '',
                nombres: '',
                apellidos: '',
                sexo: 'M' as Sexo,
                nivel_educativo: 'bachillerato' as NivelEducativo,
                tipo_vivienda: 'F' as TipoVivienda,
                direccion: '',
                telefono: '',
                email: '',
                empresa: {
                    nombre: '',
                    nit: '',
                    direccion: '',
                    telefono: '',
                    ciudad: '',
                },
                cargo: '',
                salario: 0,
                otros_ingresos: 0,
                tiempo_servicio: {
                    valor: 0,
                    unidad: 'meses' as TiempoServicioUnidad,
                },
            },
            credito: {
                producto: 'vivienda' as ProductoTipo,
                monto: 0,
                plazo: 0,
                tasa: 0,
                cuota_mensual: 0,
                destino: '',
            },
        };

        propiedades.value = [];
        deudas.value = [];
        referencias.value = [];
        tieneConyuge.value = false;
        conyugeTrabajaMismaEmpresa.value = false;
        error.value = null;
    };

    const prepararParaEnvio = (): SolicitudCreditoPayload => {
        const payload = { ...form.value };

        // Agregar datos adicionales si existen
        if (tieneConyuge.value && form.value.codeudor) {
            payload.codeudor = form.value.codeudor;
        }

        // Agregar garantía si hay propiedades
        if (propiedades.value.length > 0) {
            const propiedadPrincipal = propiedades.value[0];
            payload.garantia = {
                tipo: 'vivienda' as TipoBien,
                descripcion: propiedadPrincipal.tipo,
                valor: propiedadPrincipal.valor,
                ubicacion: propiedadPrincipal.direccion,
            };
        }

        return payload;
    };

    // Cargar datos desde storage si existen
    const cargarDesdeStorage = (datos: Partial<SolicitudCreditoPayload>) => {
        if (datos.solicitante) {
            form.value.solicitante = { ...form.value.solicitante, ...datos.solicitante };
        }
        if (datos.credito) {
            form.value.credito = { ...form.value.credito, ...datos.credito };
        }
        if (datos.codeudor) {
            form.value.codeudor = datos.codeudor;
            tieneConyuge.value = true;
        }
    };

    return {
        // Estado
        form,
        tieneConyuge,
        conyugeTrabajaMismaEmpresa,
        loading,
        error,
        propiedades,
        deudas,
        referencias,

        // Computed
        ingresosTotales,
        tienePropiedades,
        tieneDeudas,
        tieneReferencias,
        validarSolicitante,
        validarCredito,
        formularioValido,

        // Métodos principales
        toggleConyuge,
        toggleEmpresaConyuge,
        autocalcularIngresos,
        actualizarCuotaMensual,
        actualizarTasa,

        // Métodos de propiedades
        addPropiedad,
        removePropiedad,
        updatePropiedad,

        // Métodos de deudas
        addDeuda,
        removeDeuda,
        updateDeuda,

        // Métodos de referencias
        addReferencia,
        removeReferencia,
        updateReferencia,

        // Utilidades
        resetearFormulario,
        prepararParaEnvio,
        cargarDesdeStorage,
    };
};

import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSession } from '@/composables/useSession';
import { useApi } from '@/composables/useApi';
import { useStorage } from '@/composables/useStorage';
import type { 
    RolFirmante, 
    TipoIdentificacionFirma,
    FirmaData,
    FirmaDefaults,
    FirmaResponse,
    FirmaValidation,
    FirmaShareToken,
    FirmaCompartir,
    FirmaCompartirResponse,
    FirmaStats,
    FirmaConfig,
    FirmaLog,
    FirmaTemplate
} from '@/types/firmas';

const FIRMA_DEFAULTS_STORAGE_KEY = 'comfaca_credito_firma_defaults';

export function useFirmas() {
    const { user, isAuthenticated } = useSession();
    const { postJson, getJson } = useApi();
    const storage = useStorage();

    // Form state
    const solicitudFilename = ref('solicitud-credito.xml');
    const firmasFilename = ref('');
    const rolFirmante = ref<RolFirmante>('solicitante');
    const aprobado = ref(true);
    const nombreApellidos = ref('');
    const tipoIdentificacion = ref<TipoIdentificacionFirma>('CC');
    const numeroIdentificacion = ref('');
    const fechaFirma = ref('');
    const saveXml = ref(true);
    const claveFirma = ref('');
    const claveFirmaConfirm = ref('');

    // UI state
    const loading = ref(false);
    const error = ref('');
    const success = ref(false);
    const xmlText = ref('');
    const savedFilename = ref('');

    // Estado para compartir
    const shareToken = ref('');
    const shareUrl = ref('');
    const shareLoading = ref(false);
    const shareError = ref('');

    // Estado para templates
    const templates = ref<FirmaTemplate[]>([]);
    const selectedTemplate = ref<FirmaTemplate | null>(null);

    // Computed
    const isFormValid = computed(() => {
        return solicitudFilename.value &&
               rolFirmante.value &&
               nombreApellidos.value.trim() &&
               tipoIdentificacion.value &&
               numeroIdentificacion.value.trim() &&
               fechaFirma.value &&
               (!saveXml.value || (claveFirma.value && claveFirmaConfirm.value && claveFirma.value === claveFirmaConfirm.value));
    });

    const isPasswordValid = computed(() => {
        if (!saveXml.value) return true;
        return claveFirma.value.length >= 8 &&
               claveFirma.value === claveFirmaConfirm.value &&
               /[A-Z]/.test(claveFirma.value) &&
               /[a-z]/.test(claveFirma.value) &&
               /[0-9]/.test(claveFirma.value);
    });

    const rolFirmanteLabel = computed(() => {
        const labels: Record<RolFirmante, string> = {
            'solicitante': 'Solicitante',
            'codeudor': 'Codeudor',
            'garante': 'Garante',
            'representante_legal': 'Representante Legal',
            'apoderado': 'Apoderado',
            'asesor': 'Asesor',
            'administrador': 'Administrador'
        };
        return labels[rolFirmante.value] || rolFirmante.value;
    });

    const tipoIdentificacionLabel = computed(() => {
        const labels: Record<TipoIdentificacionFirma, string> = {
            'CC': 'Cédula de Ciudadanía',
            'CE': 'Cédula de Extranjería',
            'NIT': 'Número de Identificación Tributaria',
            'TI': 'Tarjeta de Identidad',
            'PP': 'Pasaporte',
            'SS': 'Salvo Social',
            'RC': 'Registro Civil',
            'PASAPORTE': 'Pasaporte'
        };
        return labels[tipoIdentificacion.value] || tipoIdentificacion.value;
    });

    // Métodos principales
    const firmarDocumento = async () => {
        if (!isFormValid.value) {
            throw new Error('Por favor complete todos los campos requeridos');
        }

        loading.value = true;
        error.value = '';
        success.value = false;

        try {
            const firmaData: FirmaData = {
                solicitud_filename: solicitudFilename.value,
                firmas_filename: firmasFilename.value || `firmas-${Date.now()}.xml`,
                rol_firmante: rolFirmante.value,
                aprobado: aprobado.value,
                nombre_apellidos: nombreApellidos.value.trim(),
                tipo_identificacion: tipoIdentificacion.value,
                numero_identificacion: numeroIdentificacion.value.trim(),
                fecha_firma: fechaFirma.value,
                save_xml: saveXml.value,
                clave_firma: claveFirma.value,
                clave_firma_confirm: claveFirmaConfirm.value
            };

            const response = await postJson<FirmaResponse>('/api/firmas/firmar', firmaData, { auth: true });

            if (response.success && response.data) {
                xmlText.value = response.data.xml_content;
                savedFilename.value = response.data.firmas_filename;
                success.value = true;

                // Guardar defaults en localStorage
                await saveDefaults();

                // Limpiar campos de contraseña
                claveFirma.value = '';
                claveFirmaConfirm.value = '';

                return response.data;
            } else {
                throw new Error(response.message || 'Error al firmar el documento');
            }
        } catch (err: any) {
            console.error('Error firmando documento:', err);
            error.value = err.message || 'Error al firmar el documento';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const validarFirma = async (firmaId: string) => {
        try {
            const response = await getJson<FirmaValidation>(`/api/firmas/validar/${firmaId}`, { auth: true });
            return response;
        } catch (err: any) {
            console.error('Error validando firma:', err);
            error.value = err.message || 'Error al validar la firma';
            throw err;
        }
    };

    // Métodos de compartir
    const generarShareToken = async (compartirData: FirmaCompartir) => {
        shareLoading.value = true;
        shareError.value = '';

        try {
            const response = await postJson<FirmaCompartirResponse>('/api/firmas/compartir', compartirData, { auth: true });

            if (response.success && response.data) {
                shareToken.value = response.data.token;
                shareUrl.value = response.data.share_url;
                return response.data;
            } else {
                throw new Error(response.message || 'Error al generar token de compartir');
            }
        } catch (err: any) {
            console.error('Error generando share token:', err);
            shareError.value = err.message || 'Error al generar token de compartir';
            throw err;
        } finally {
            shareLoading.value = false;
        }
    };

    const compartirFirma = async (email: string, mensaje?: string) => {
        const compartirData: FirmaCompartir = {
            email,
            rol_firmante: rolFirmante.value,
            mensaje: mensaje || `Por favor firma este documento como ${rolFirmanteLabel.value}`,
            expires_in_hours: 24,
            max_access: 3
        };

        return await generarShareToken(compartirData);
    };

    const validarShareToken = async (token: string) => {
        try {
            const response = await getJson<{ success: boolean; data: FirmaShareToken }>(`/api/firmas/share/${token}`, { auth: false });
            
            if (response.success && response.data) {
                // Cargar los datos de la firma en el formulario
                const firmaData = response.data.firma_data;
                rolFirmante.value = firmaData.rol_firmante;
                nombreApellidos.value = firmaData.nombre_apellidos;
                tipoIdentificacion.value = firmaData.tipo_identificacion;
                numeroIdentificacion.value = firmaData.numero_identificacion;
                solicitudFilename.value = firmaData.solicitud_filename;
                
                return response.data;
            } else {
                throw new Error('Token inválido o expirado');
            }
        } catch (err: any) {
            console.error('Error validando share token:', err);
            error.value = err.message || 'Error al validar el token';
            throw err;
        }
    };

    // Métodos de templates
    const cargarTemplates = async () => {
        try {
            const response = await getJson<{ success: boolean; data: FirmaTemplate[] }>('/api/firmas/templates', { auth: true });
            
            if (response.success) {
                templates.value = response.data;
            }
        } catch (err: any) {
            console.error('Error cargando templates:', err);
        }
    };

    const seleccionarTemplate = (template: FirmaTemplate) => {
        selectedTemplate.value = template;
        
        // Aplicar valores del template al formulario
        rolFirmante.value = template.rol_firmante;
        
        // Aplicar variables del template si existen
        if (template.variables) {
            template.variables.forEach(variable => {
                if (variable.nombre === 'nombre_apellidos' && variable.valor_defecto) {
                    nombreApellidos.value = variable.valor_defecto;
                }
                if (variable.nombre === 'tipo_identificacion' && variable.valor_defecto) {
                    tipoIdentificacion.value = variable.valor_defecto;
                }
                if (variable.nombre === 'numero_identificacion' && variable.valor_defecto) {
                    numeroIdentificacion.value = variable.valor_defecto;
                }
            });
        }
    };

    // Métodos de utilidad
    const saveDefaults = async () => {
        if (!process.client) return;

        try {
            const defaults: FirmaDefaults = {
                rol_firmante: rolFirmante.value,
                nombre_apellidos: nombreApellidos.value.trim(),
                tipo_identificacion: tipoIdentificacion.value,
                numero_identificacion: numeroIdentificacion.value.trim()
            };

            await storage.setItem(FIRMA_DEFAULTS_STORAGE_KEY, JSON.stringify(defaults));
        } catch (err: any) {
            console.error('Error guardando defaults:', err);
        }
    };

    const loadDefaults = async () => {
        if (!process.client) return;

        try {
            const raw = await storage.getItem(FIRMA_DEFAULTS_STORAGE_KEY);
            if (!raw) return;

            const parsed = JSON.parse(raw) as FirmaDefaults;
            
            // Solo cargar defaults si los campos están vacíos
            if (!rolFirmante.value || rolFirmante.value === 'solicitante') {
                rolFirmante.value = parsed.rol_firmante;
            }
            if (!nombreApellidos.value.trim()) {
                nombreApellidos.value = parsed.nombre_apellidos;
            }
            if (!tipoIdentificacion.value || tipoIdentificacion.value === 'CC') {
                tipoIdentificacion.value = parsed.tipo_identificacion;
            }
            if (!numeroIdentificacion.value.trim()) {
                numeroIdentificacion.value = parsed.numero_identificacion;
            }
        } catch (err: any) {
            console.error('Error cargando defaults:', err);
        }
    };

    const resetForm = () => {
        solicitudFilename.value = 'solicitud-credito.xml';
        firmasFilename.value = '';
        rolFirmante.value = 'solicitante';
        aprobado.value = true;
        nombreApellidos.value = '';
        tipoIdentificacion.value = 'CC';
        numeroIdentificacion.value = '';
        fechaFirma.value = '';
        saveXml.value = true;
        claveFirma.value = '';
        claveFirmaConfirm.value = '';
        error.value = '';
        success.value = false;
        xmlText.value = '';
        savedFilename.value = '';
        selectedTemplate.value = null;
    };

    const formatIdentificacion = (tipo: string, numero: string): string => {
        if (tipo === 'NIT') {
            // Formateo NIT colombiano: XXX.XXX.XXX-X
            const clean = numero.replace(/[^\d]/g, '');
            if (clean.length <= 8) {
                return clean;
            }
            return `${clean.slice(0, 8)}.${clean.slice(8, 11)}.${clean.slice(11)}`;
        }
        return numero;
    };

    const formatFecha = (fecha: string): string => {
        if (!fecha) return '';
        
        try {
            const date = new Date(fecha);
            return date.toLocaleDateString('es-CO', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            });
        } catch (err) {
            return fecha;
        }
    };

    const getCurrentDate = (): string => {
        return formatFecha(new Date().toISOString());
    };

    // Métodos de estadísticas
    const getStats = async () => {
        try {
            const response = await getJson<{ success: boolean; data: FirmaStats }>('/api/firmas/stats', { auth: true });
            
            if (response.success) {
                return response.data;
            }
        } catch (err: any) {
            console.error('Error obteniendo estadísticas:', err);
        }
    };

    // Inicialización
    const initialize = async () => {
        // Establecer fecha actual por defecto
        if (!fechaFirma.value) {
            fechaFirma.value = getCurrentDate();
        }

        // Cargar defaults desde localStorage
        await loadDefaults();

        // Cargar templates
        await cargarTemplates();

        // Cargar parámetros de URL si existen
        const urlParams = new URLSearchParams(window.location.search);
        const filename = urlParams.get('solicitud_filename') || urlParams.get('filename') || urlParams.get('xml');
        if (filename) {
            solicitudFilename.value = filename;
        }
    };

    return {
        // Estado del formulario
        solicitudFilename,
        firmasFilename,
        rolFirmante,
        aprobado,
        nombreApellidos,
        tipoIdentificacion,
        numeroIdentificacion,
        fechaFirma,
        saveXml,
        claveFirma,
        claveFirmaConfirm,

        // Estado UI
        loading,
        error,
        success,
        xmlText,
        savedFilename,

        // Estado de compartir
        shareToken,
        shareUrl,
        shareLoading,
        shareError,

        // Estado de templates
        templates,
        selectedTemplate,

        // Computed
        isFormValid,
        isPasswordValid,
        rolFirmanteLabel,
        tipoIdentificacionLabel,

        // Métodos principales
        firmarDocumento,
        validarFirma,

        // Métodos de compartir
        generarShareToken,
        compartirFirma,
        validarShareToken,

        // Métodos de templates
        cargarTemplates,
        seleccionarTemplate,

        // Métodos de utilidad
        saveDefaults,
        loadDefaults,
        resetForm,
        formatIdentificacion,
        formatFecha,
        getCurrentDate,

        // Métodos de estadísticas
        getStats,

        // Inicialización
        initialize
    };
}

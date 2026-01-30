import { ref, computed } from 'vue';
import { useSession } from '@/composables/useSession';
import { useApi } from '@/composables/useApi';
import type { 
    XmlExtractRequest, 
    XmlExtractResponse,
    XmlNode,
    XmlValidationRule,
    XmlValidationResult,
    XmlSchema,
    XmlTransform,
    XmlTemplate,
    XmlDocument,
    XmlProcessingOptions
} from '@/types/xml';

export function useXmlExtract() {
    const { isAuthenticated } = useSession();
    const { postJson, getJson } = useApi();

    // Form state
    const filename = ref('solicitud-credito.xml');
    const validate = ref(true);
    const xpath = ref('');
    const namespace = ref('');

    // UI state
    const loading = ref(false);
    const error = ref('');
    const data = ref<any>(null);
    const metadata = ref<XmlExtractResponse['metadata'] | null>(null);
    const validation = ref<XmlValidationResult | null>(null);

    // Estado para templates y transforms
    const templates = ref<XmlTemplate[]>([]);
    const transforms = ref<XmlTransform[]>([]);
    const selectedTemplate = ref<XmlTemplate | null>(null);
    const selectedTransform = ref<XmlTransform | null>(null);

    // Processing options
    const processingOptions = ref<XmlProcessingOptions>({
        preserveWhitespace: false,
        ignoreComments: true,
        formatOutput: true,
        encoding: 'UTF-8',
        namespaceAware: true,
        validateSchema: true
    });

    // Computed properties
    const pretty = computed(() => {
        if (!data.value) return '';
        return JSON.stringify(data.value, null, 2);
    });

    const isValid = computed(() => {
        return validation.value?.valid ?? true;
    });

    const hasErrors = computed(() => {
        return validation.value?.errors.length > 0;
    });

    const hasWarnings = computed(() => {
        return validation.value?.warnings.length > 0;
    });

    const errorCount = computed(() => {
        return validation.value?.errors.length ?? 0;
    });

    const warningCount = computed(() => {
        return validation.value?.warnings.length ?? 0;
    });

    const isFormValid = computed(() => {
        return filename.value.trim() !== '';
    });

    // Métodos principales
    const extraer = async () => {
        if (!isAuthenticated.value) {
            throw new Error('Usuario no autenticado');
        }

        if (!isFormValid.value) {
            throw new Error('Por favor ingrese un nombre de archivo válido');
        }

        loading.value = true;
        error.value = '';
        data.value = null;
        metadata.value = null;
        validation.value = null;

        try {
            const request: XmlExtractRequest = {
                filename: filename.value.trim(),
                validate: validate.value,
                xpath: xpath.value.trim() || undefined,
                namespace: namespace.value.trim() || undefined
            };

            const response = await postJson<XmlExtractResponse>('/api/xml/extract', request, { auth: true });

            if (response.success && response.data) {
                data.value = response.data;
                metadata.value = response.metadata || null;
                validation.value = response.validation || null;
            } else {
                throw new Error(response.error || 'Error extrayendo XML');
            }
        } catch (err: any) {
            console.error('Error extrayendo XML:', err);
            error.value = err.message || 'Error extrayendo XML';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const validarXml = async (xmlContent: string, schema?: string) => {
        try {
            const response = await postJson<XmlValidationResult>('/api/xml/validate', {
                content: xmlContent,
                schema: schema,
                options: processingOptions.value
            }, { auth: true });

            validation.value = response;
            return response;
        } catch (err: any) {
            console.error('Error validando XML:', err);
            error.value = err.message || 'Error validando XML';
            throw err;
        }
    };

    const parseXml = async (xmlContent: string) => {
        try {
            const response = await postJson<{ success: boolean; data: XmlNode }>('/api/xml/parse', {
                content: xmlContent,
                options: processingOptions.value
            }, { auth: true });

            if (response.success) {
                return response.data;
            } else {
                throw new Error('Error parseando XML');
            }
        } catch (err: any) {
            console.error('Error parseando XML:', err);
            error.value = err.message || 'Error parseando XML';
            throw err;
        }
    };

    // Métodos de templates
    const cargarTemplates = async () => {
        try {
            const response = await getJson<{ success: boolean; data: XmlTemplate[] }>('/api/xml/templates', { auth: true });
            
            if (response.success) {
                templates.value = response.data;
            }
        } catch (err: any) {
            console.error('Error cargando templates:', err);
        }
    };

    const seleccionarTemplate = (template: XmlTemplate) => {
        selectedTemplate.value = template;
        
        // Aplicar variables del template si existen
        if (template.variables) {
            template.variables.forEach(variable => {
                // Aquí podrías cargar los valores de las variables desde un formulario
                // o desde algún estado global
            });
        }
    };

    const aplicarTemplate = async (templateData: Record<string, any>) => {
        if (!selectedTemplate.value) {
            throw new Error('No hay template seleccionado');
        }

        try {
            const response = await postJson<{ success: boolean; data: string }>('/api/xml/apply-template', {
                template: selectedTemplate.value.template,
                variables: templateData,
                options: processingOptions.value
            }, { auth: true });

            if (response.success) {
                return response.data;
            } else {
                throw new Error('Error aplicando template');
            }
        } catch (err: any) {
            console.error('Error aplicando template:', err);
            error.value = err.message || 'Error aplicando template';
            throw err;
        }
    };

    // Métodos de transforms
    const cargarTransforms = async () => {
        try {
            const response = await getJson<{ success: boolean; data: XmlTransform[] }>('/api/xml/transforms', { auth: true });
            
            if (response.success) {
                transforms.value = response.data;
            }
        } catch (err: any) {
            console.error('Error cargando transforms:', err);
        }
    };

    const aplicarTransform = async (transformData: any) => {
        if (!selectedTransform.value) {
            throw new Error('No hay transform seleccionada');
        }

        try {
            const response = await postJson<{ success: boolean; data: any }>('/api/xml/transform', {
                transform: selectedTransform.value,
                data: transformData
            }, { auth: true });

            if (response.success) {
                return response.data;
            } else {
                throw new Error('Error aplicando transform');
            }
        } catch (err: any) {
            console.error('Error aplicando transform:', err);
            error.value = err.message || 'Error aplicando transform';
            throw err;
        }
    };

    // Métodos de utilidad
    const resetForm = () => {
        filename.value = 'solicitud-credito.xml';
        validate.value = true;
        xpath.value = '';
        namespace.value = '';
        error.value = '';
        data.value = null;
        metadata.value = null;
        validation.value = null;
        selectedTemplate.value = null;
        selectedTransform.value = null;
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const formatDate = (dateString: string): string => {
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-CO', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (err) {
            return dateString;
        }
    };

    const downloadXml = (content: string, filename?: string) => {
        const blob = new Blob([content], { type: 'application/xml' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename || 'extracted.xml';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    };

    const downloadJson = (jsonData: any, filename?: string) => {
        const content = JSON.stringify(jsonData, null, 2);
        const blob = new Blob([content], { type: 'application/json' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename || 'extracted.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    };

    const copyToClipboard = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            console.error('Error copiando al clipboard:', err);
            return false;
        }
    };

    // Métodos de búsqueda
    const searchInXml = (searchTerm: string): any[] => {
        if (!data.value || !searchTerm) return [];

        const results: any[] = [];
        const searchLower = searchTerm.toLowerCase();

        const searchNode = (node: any, path: string = '') => {
            if (typeof node === 'object' && node !== null) {
                for (const [key, value] of Object.entries(node)) {
                    const currentPath = path ? `${path}.${key}` : key;
                    
                    if (typeof value === 'string' && value.toLowerCase().includes(searchLower)) {
                        results.push({
                            path: currentPath,
                            value: value,
                            type: 'string'
                        });
                    } else if (typeof value === 'object' && value !== null) {
                        searchNode(value, currentPath);
                    }
                }
            }
        };

        searchNode(data.value);
        return results;
    };

    // Métodos de validación
    const addValidationRule = (rule: XmlValidationRule) => {
        // Aquí podrías agregar reglas de validación personalizadas
        // y aplicarlas al XML extraído
    };

    const validateAgainstSchema = async (schemaName: string) => {
        try {
            const response = await postJson<XmlValidationResult>('/api/xml/validate-schema', {
                data: data.value,
                schema: schemaName,
                options: processingOptions.value
            }, { auth: true });

            validation.value = response;
            return response;
        } catch (err: any) {
            console.error('Error validando contra schema:', err);
            error.value = err.message || 'Error validando contra schema';
            throw err;
        }
    };

    // Inicialización
    const initialize = async () => {
        await Promise.all([
            cargarTemplates(),
            cargarTransforms()
        ]);
    };

    return {
        // Estado del formulario
        filename,
        validate,
        xpath,
        namespace,

        // Estado UI
        loading,
        error,
        data,
        metadata,
        validation,

        // Estado de templates y transforms
        templates,
        transforms,
        selectedTemplate,
        selectedTransform,

        // Processing options
        processingOptions,

        // Computed
        pretty,
        isValid,
        hasErrors,
        hasWarnings,
        errorCount,
        warningCount,
        isFormValid,

        // Métodos principales
        extraer,
        validarXml,
        parseXml,

        // Métodos de templates
        cargarTemplates,
        seleccionarTemplate,
        aplicarTemplate,

        // Métodos de transforms
        cargarTransforms,
        aplicarTransform,

        // Métodos de utilidad
        resetForm,
        formatFileSize,
        formatDate,
        downloadXml,
        downloadJson,
        copyToClipboard,

        // Métodos de búsqueda
        searchInXml,

        // Métodos de validación
        addValidationRule,
        validateAgainstSchema,

        // Inicialización
        initialize
    };
}

import { ref, computed } from 'vue';
import { useApi } from '@/composables/useApi';
import { useSession } from '@/composables/useSession';
import type { 
    DocumentoRequerido, 
    DocumentoCargado, 
    UploadProgress,
    DocumentoValidation,
    Firma
} from '@/types/solicitud-credito';

export const useDocumentos = (solicitudId: string) => {
    const { postJson, putJson, deleteJson } = useApi();
    const { isAuthenticated } = useSession();

    // Estado
    const loading = ref(false);
    const error = ref<string | null>(null);
    const uploading = ref(false);
    const uploadProgress = ref<UploadProgress>({ loaded: 0, total: 0, percentage: 0 });
    const documentosCargados = ref<DocumentoCargado[]>([]);
    const documentosRequeridos = ref<DocumentoRequerido[]>([]);
    const selectedFiles = ref<File[]>([]);

    // Estado para modales
    const showUploadModal = ref(false);
    const showPreviewModal = ref(false);
    const documentoPreview = ref<DocumentoCargado | null>(null);
    const showValidationModal = ref(false);
    const validationResult = ref<DocumentoValidation | null>(null);

    // Computed properties
    const progresoGeneral = computed(() => {
        if (documentosRequeridos.value.length === 0) return 0;
        
        const requeridos = documentosRequeridos.value.filter(doc => doc.obligatorio);
        const cargadosRequeridos = requeridos.filter(doc => 
            documentosCargados.value.some(cargado => cargado.tipo === doc.tipo)
        );
        
        return requeridos.length > 0 ? (cargadosRequeridos.length / requeridos.length) * 100 : 0;
    });

    const documentosCompletos = computed(() => {
        return documentosRequeridos.value.filter(doc => 
            documentosCargados.value.some(cargado => cargado.tipo === doc.tipo && cargado.estado === 'aprobado')
        );
    });

    const todosDocumentosCompletos = computed(() => {
        if (documentosRequeridos.value.length === 0) return false;
        return documentosCompletos.value.length === documentosRequeridos.value.length;
    });

    const puedeSubirDocumentos = computed(() => {
        return isAuthenticated.value && !todosDocumentosCompletos.value;
    });

    // Validación de archivos
    const validarArchivo = (file: File): string | null => {
        const MAX_SIZE = 5 * 1024 * 1024; // 5MB
        const ALLOWED_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];

        if (file.size > MAX_SIZE) {
            return `El archivo excede el tamaño máximo permitido de 5MB`;
        }

        if (!ALLOWED_TYPES.includes(file.type)) {
            return `Formato de archivo no válido. Se permiten: PDF, JPG, PNG`;
        }

        return null;
    };

    const validarDocumentosRequeridos = (): DocumentoValidation => {
        const errores: string[] = [];
        const advertencias: string[] = [];

        documentosRequeridos.value.forEach(doc => {
            const cargado = documentosCargados.value.find(c => c.tipo === doc.tipo);
            
            if (doc.obligatorio && !cargado) {
                errores.push(`El documento "${doc.nombre}" es obligatorio`);
            }
            
            if (cargado && cargado.estado === 'rechazado') {
                errores.push(`El documento "${doc.nombre}" fue rechazado. Por favor sube una versión válida.`);
            }
            
            if (doc.fecha_limite) {
                const fechaLimite = new Date(doc.fecha_limite);
                const hoy = new Date();
                if (hoy > fechaLimite && !cargado) {
                    advertencias.push(`El documento "${doc.nombre}" debió subirse antes del ${doc.fecha_limite}`);
                }
            }
        });

        return {
            valido: errores.length === 0,
            errores,
            advertencias
        };
    };

    // Métodos principales
    const cargarDocumentos = async () => {
        if (!isAuthenticated.value) return;

        loading.value = true;
        error.value = null;

        try {
            // Cargar documentos cargados
            const responseCargados = await postJson<{ success: boolean; data: DocumentoCargado[]; count: number }>(`/api/solicitudes/${solicitudId}/documentos`, {}, { auth: true });

            if (responseCargados.success) {
                documentosCargados.value = responseCargados.data;
            }

            // Cargar documentos requeridos
            const responseRequeridos = await postJson<{ success: boolean; data: DocumentoRequerido[]; count: number }>(`/api/solicitudes/${solicitudId}/documentos/requeridos`, {}, { auth: true });

            if (responseRequeridos.success) {
                documentosRequeridos.value = responseRequeridos.data;
            }
        } catch (err: any) {
            console.error('Error cargando documentos:', err);
            error.value = err.message || 'Error al cargar los documentos';
        } finally {
            loading.value = false;
        }
    };

    const subirDocumento = async (file: File, tipoDocumento: string) => {
        uploading.value = true;
        uploadProgress.value = { loaded: 0, total: 0, percentage: 0 };
        error.value = null;

        try {
            const validacion = validarArchivo(file);
            if (validacion) {
                throw new Error(validacion);
            }

            const formData = new FormData();
            formData.append('archivo', file);
            formData.append('tipo', tipoDocumento);
            formData.append('solicitud_id', solicitudId);

            // Configurar upload con progreso
            const xhr = new XMLHttpRequest();
            
            // Monitorear progreso
            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    const loaded = event.loaded;
                    const total = event.total;
                    const percentage = Math.round((loaded / total) * 100);
                    
                    uploadProgress.value = { loaded, total, percentage };
                }
            });

            // Crear promesa para el upload
            const uploadPromise = new Promise<DocumentoCargado>((resolve, reject) => {
                xhr.onload = () => {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            resolve(response.data);
                        } catch (error) {
                            reject(new Error('Error procesando respuesta del servidor'));
                        }
                    } else {
                        reject(new Error(`Error en la carga: ${xhr.statusText}`));
                    }
                };

                xhr.onerror = () => {
                    reject(new Error('Error de conexión al subir el archivo'));
                };

                xhr.open('POST', `/api/solicitudes/${solicitudId}/documentos/subir`);
                xhr.setRequestHeader('Authorization', `Bearer ${localStorage.getItem('access_token')}`);
                xhr.send(formData);
            });

            const documento = await uploadPromise;
            
            // Agregar a la lista de documentos cargados
            documentosCargados.value.push(documento);
            
            return documento;
        } catch (err: any) {
            console.error('Error subiendo documento:', err);
            error.value = err.message || 'Error al subir el documento';
            throw err;
        } finally {
            uploading.value = false;
            uploadProgress.value = { loaded: 0, total: 0, percentage: 0 };
        }
    };

    const subirMultiplesDocumentos = async (files: File[]) => {
        const resultados: { success: DocumentoCargado[]; errors: string[] } = {
            success: [],
            errors: []
        };

        for (const file of files) {
            try {
                // Determinar el tipo de documento basado en el nombre o tipo
                const tipoDocumento = await determinarTipoDocumento(file);
                const documento = await subirDocumento(file, tipoDocumento);
                resultados.success.push(documento);
            } catch (err: any) {
                resultados.errors.push(`Error subiendo ${file.name}: ${err.message}`);
            }
        }

        return resultados;
    };

    const determinarTipoDocumento = async (file: File): Promise<string> => {
        // Lógica para determinar el tipo de documento basado en el nombre o contenido
        const nombre = file.name.toLowerCase();
        
        // Buscar coincidencias con documentos requeridos
        for (const doc of documentosRequeridos.value) {
            if (nombre.includes(doc.tipo.toLowerCase()) || 
                nombre.includes(doc.nombre.toLowerCase())) {
                return doc.tipo;
            }
        }

        // Si no se encuentra coincidencia, usar el tipo como "otros"
        return 'otros';
    };

    const eliminarDocumento = async (documentoId: string) => {
        try {
            await deleteJson(`/api/solicitudes/${solicitudId}/documentos/${documentoId}`, { auth: true });
            
            // Eliminar de la lista local
            documentosCargados.value = documentosCargados.value.filter(doc => doc.id !== documentoId);
        } catch (err: any) {
            console.error('Error eliminando documento:', err);
            error.value = err.message || 'Error al eliminar el documento';
            throw err;
        }
    };

    const descargarDocumento = (documento: DocumentoCargado) => {
        if (documento.url_descarga) {
            window.open(documento.url_descarga, '_blank');
        } else {
            error.value = 'URL de descarga no disponible';
        }
    };

    // Métodos de modales
    const abrirModalSubida = () => {
        selectedFiles.value = [];
        showUploadModal.value = true;
    };

    const cerrarModalSubida = () => {
        showUploadModal.value = false;
        selectedFiles.value = [];
    };

    const abrirModalPreview = (documento: DocumentoCargado) => {
        documentoPreview.value = documento;
        showPreviewModal.value = true;
    };

    const cerrarModalPreview = () => {
        showPreviewModal.value = false;
        documentoPreview.value = null;
    };

    const abrirModalValidacion = () => {
        validationResult.value = validarDocumentosRequeridos();
        showValidationModal.value = true;
    };

    const cerrarModalValidacion = () => {
        showValidationModal.value = false;
        validationResult.value = null;
    };

    // Manejo de archivos seleccionados
    const handleFileSelect = (event: Event) => {
        const target = event.target as HTMLInputElement;
        const files = Array.from(target.files || []);
        
        // Validar archivos
        const archivosValidos: File[] = [];
        const errores: string[] = [];

        files.forEach(file => {
            const validacion = validarArchivo(file);
            if (validacion) {
                errores.push(`${file.name}: ${validacion}`);
            } else {
                archivosValidos.push(file);
            }
        });

        if (errores.length > 0) {
            error.value = errores.join(' | ');
        } else {
            selectedFiles.value = archivosValidos;
        }
    };

    // Utilidades
    const formatoTamano = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const formatoFecha = (fecha: string): string => {
        return new Date(fecha).toLocaleDateString('es-CO', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const getEstadoColor = (estado: string): string => {
        const colores: Record<string, string> = {
            'pendiente': 'yellow',
            'procesando': 'blue',
            'aprobado': 'green',
            'rechazado': 'red',
            'expirado': 'orange'
        };
        return colores[estado] || 'gray';
    };

    const getEstadoIcono = (estado: string): string => {
        const iconos: Record<string, string> = {
            'pendiente': '⏳',
            'procesando': '🔄',
            'aprobado': '✅',
            'rechazado': '❌',
            'expirado': '⚠️'
        };
        return iconos[estado] || '📄';
    };

    // Inicialización
    const inicializar = async () => {
        await cargarDocumentos();
    };

    return {
        // Estado
        loading,
        error,
        uploading,
        uploadProgress,
        documentosCargados,
        documentosRequeridos,
        selectedFiles,
        showUploadModal,
        showPreviewModal,
        documentoPreview,
        showValidationModal,
        validationResult,

        // Computed
        progresoGeneral,
        documentosCompletos,
        todosDocumentosCompletos,
        puedeSubirDocumentos,

        // Métodos principales
        cargarDocumentos,
        subirDocumento,
        subirMultiplesDocumentos,
        eliminarDocumento,
        descargarDocumento,

        // Validación
        validarArchivo,
        validarDocumentosRequeridos,

        // Modales
        abrirModalSubida,
        cerrarModalSubida,
        abrirModalPreview,
        cerrarModalPreview,
        abrirModalValidacion,
        cerrarModalValidacion,

        // Manejo de archivos
        handleFileSelect,

        // Utilidades
        formatoTamano,
        formatoFecha,
        getEstadoColor,
        getEstadoIcono,

        // Inicialización
        inicializar,
    };
};

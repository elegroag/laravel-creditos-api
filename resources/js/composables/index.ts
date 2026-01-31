// Exportar composables principales
export * from './useApi';
export * from './useSession';
export * from './useStorage';

// Exportar composables de autenticación
export * from './auth/useLogin';
export * from './auth/useRegister';

// Exportar composables de administración
export * from './admin/useAdminUsers';
export * from './admin/useAdminSolicitudes';
export * from './admin/useAdminConvenios';

// Exportar composables de simulador
export * from './simulador/useSimulador';
export * from './simulador/useSimuladorConConvenio';

// Exportar composables de solicitud
export * from './solicitud/useSolicitudCreditoForm';
export * from './solicitud/useWizardSolicitud';
export * from './solicitud/useConvenioValidation';
export * from './solicitud/useDocumentos';
export * from './solicitud/useFirmadoDigital';

// Exportar composables de usuario
export * from './perfil/usePerfil';
export * from './inicio/useInicio';

// Exportar composables de layout
export * from './layout/useLayout';

// Exportar composables de entidad
export * from './entidad/useEntidadDigital';

// Exportar composables de firmas
export * from './firmas/useFirmas';

// Exportar composables de XML
export * from './xml/useXmlExtract';

// Todos los composables han sido migrados
// El sistema está completo y funcional

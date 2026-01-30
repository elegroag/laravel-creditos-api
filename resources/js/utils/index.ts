// Exportar todos los formateadores desde un solo lugar
export * from './formatters';

// Re-exportar por defecto para compatibilidad
export { default as formatters } from './formatters';

// Exportaciones individuales para facilitar el uso
export {
    formatCurrency,
    formatCurrencyIntl,
    formatCurrencyWithSymbol,
    formatCurrencyWithDecimals,
    formatDate,
    formatDateLong,
    formatDateTime,
    formatTime,
    formatDateRelative,
    formatValue,
    formatRevisionKey,
    formatRevisionIndex,
    formatIdentificacion,
    formatTelefono,
    formatTelefonoInternacional,
    formatCuentaBancaria,
    formatPercentage,
    formatLargeNumber,
    formatFileSize,
    formatDuration,
    formatEstado,
    formatGenero,
    formatEstadoCivil,
    formatNivelEducativo,
    formatTipoContrato,
    formatFrecuenciaPago,
    formatMetodoPago,
    formatTipoCuenta,
    formatDireccion,
    formatCodigoPostal,
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
    formatRFC,
    formatCURP
} from './formatters';

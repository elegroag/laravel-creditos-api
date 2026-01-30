<template>
    <div class="form-group">
        <label v-if="label" :for="selectId" class="form-label">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        
        <select
            :id="selectId"
            v-model="selectedValue"
            :disabled="disabled"
            :class="selectClasses"
            @change="handleChange"
        >
            <option value="" disabled>{{ placeholder }}</option>
            <option
                v-for="option in normalizedOptions"
                :key="option.value"
                :value="option.value"
            >
                {{ getDisplayLabel(option) }}
            </option>
        </select>
        
        <!-- Mensaje de error -->
        <div v-if="hasError" class="error-message">
            <span class="text-red-500 text-sm">{{ errorMessage }}</span>
        </div>
        
        <!-- Mensaje de ayuda -->
        <div v-if="helpText && !errorMessage" class="help-message">
            <span class="text-gray-400 text-sm">{{ helpText }}</span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

// Types
interface SelectOption {
    label: string;
    value: string | number | boolean;
    description?: string;
    [key: string]: any;
}

type SelectValue = string | number | null;
type RawOption = SelectOption | string | number;

interface Props {
    modelValue?: SelectValue;
    options?: RawOption[];
    label?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    loading?: boolean;
    clearable?: boolean;
    searchable?: boolean;
    multiple?: boolean;
    closeOnSelect?: boolean;
    maxHeight?: number;
    variant?: 'default' | 'filter';
    size?: 'default' | 'lg';
    errorMessage?: string;
    helpText?: string;
    asyncSearch?: boolean;
    searchDebounce?: number;
}

interface Emits {
    'update:modelValue': [value: SelectValue];
    'search': [query: string];
    'option:selected': [option: SelectOption];
    'option:deselected': [option: SelectOption];
    'open': [];
    'close': [];
}

// Props con valores por defecto
const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    options: () => [],
    label: '',
    placeholder: 'Seleccionar opción...',
    required: false,
    disabled: false,
    loading: false,
    clearable: true,
    searchable: true,
    multiple: false,
    closeOnSelect: true,
    maxHeight: 200,
    variant: 'default',
    size: 'default',
    errorMessage: '',
    helpText: '',
    asyncSearch: false,
    searchDebounce: 300
});

const emit = defineEmits<Emits>();

// ID único
let selectCounter = 0;
const selectId = computed(() => `custom-select-${++selectCounter}`);

// Computed properties
const selectedValue = computed<SelectValue>({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
});

const hasError = computed(() => Boolean(props.errorMessage));

const selectClasses = computed(() => [
    'custom-select',
    'form-select',
    {
        'filter-select': props.variant === 'filter',
        'form-select-lg': props.size === 'lg',
        'has-error': hasError.value,
        'is-disabled': props.disabled
    }
]);

// Funciones
const normalizeOption = (option: RawOption): SelectOption => {
    if (typeof option === 'string' || typeof option === 'number') {
        return {
            label: String(option),
            value: option,
            description: ''
        };
    }
    return option as SelectOption;
};

const normalizedOptions = computed(() => 
    props.options.map(normalizeOption)
);

const getDisplayLabel = (option: SelectOption | RawOption) => {
    if (typeof option === 'string' || typeof option === 'number') {
        return String(option);
    }
    return (option as SelectOption).label || String(option);
};

// Event handlers
const handleChange = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    const value = target.value;
    const selectedOption = normalizedOptions.value.find(opt => String(opt.value) === value);
    
    if (selectedOption) {
        emit('option:selected', selectedOption);
    }
};

const handleSearch = (query: string): void => {
    emit('search', query);
};

const handleSelect = (option: SelectOption): void => {
    emit('option:selected', option);
};

const handleDeselect = (option: SelectOption): void => {
    emit('option:deselected', option);
};

const handleOpen = (): void => {
    emit('open');
};

const handleClose = (): void => {
    emit('close');
};
</script>

<style scoped>
/* Estilos básicos para el select nativo */
.custom-select {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    background-color: white;
    font-size: 0.875rem;
    line-height: 1.25rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.custom-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.custom-select.has-error {
    border-color: #ef4444;
}

.custom-select.is-disabled {
    background-color: #f3f4f6;
    color: #6b7280;
    cursor: not-allowed;
}

.custom-select.filter-select {
    max-width: 300px;
}

.custom-select.form-select-lg {
    padding: 0.75rem 1rem;
    font-size: 1rem;
}

.form-label {
    display: block;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
}

.error-message,
.help-message {
    margin-top: 0.25rem;
}

/* Responsive */
@media (max-width: 768px) {
    .custom-select {
        font-size: 0.8rem;
        padding: 0.4rem 0.6rem;
    }
    
    .custom-select.form-select-lg {
        padding: 0.6rem 0.8rem;
        font-size: 0.9rem;
    }
}
</style>

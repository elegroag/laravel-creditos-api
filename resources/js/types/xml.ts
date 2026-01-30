export interface XmlExtractRequest {
    filename: string;
    validate: boolean;
    xpath?: string;
    namespace?: string;
}

export interface XmlExtractResponse {
    success: boolean;
    data?: any;
    error?: string;
    metadata?: {
        filename: string;
        size: number;
        created_at: string;
        modified_at: string;
        encoding: string;
        version: string;
    };
    validation?: {
        valid: boolean;
        errors: string[];
        warnings: string[];
    };
}

export interface XmlNode {
    name: string;
    value?: string;
    attributes?: Record<string, string>;
    children?: XmlNode[];
    text?: string;
    type: 'element' | 'text' | 'comment' | 'cdata';
}

export interface XmlValidationRule {
    xpath: string;
    required: boolean;
    pattern?: string;
    minLength?: number;
    maxLength?: number;
    type?: 'string' | 'number' | 'date' | 'boolean';
    customValidator?: (value: string) => boolean;
}

export interface XmlValidationResult {
    valid: boolean;
    errors: Array<{
        xpath: string;
        message: string;
        severity: 'error' | 'warning';
    }>;
    warnings: Array<{
        xpath: string;
        message: string;
    }>;
}

export interface XmlSchema {
    name: string;
    version: string;
    namespace: string;
    elements: Array<{
        name: string;
        type: string;
        required: boolean;
        minOccurs?: number;
        maxOccurs?: number;
        attributes?: Array<{
            name: string;
            type: string;
            required: boolean;
            defaultValue?: string;
        }>;
    }>;
}

export interface XmlTransform {
    id: string;
    name: string;
    description: string;
    sourceXpath: string;
    targetFormat: 'json' | 'csv' | 'xml';
    transformRules: Array<{
        source: string;
        target: string;
        type: 'field' | 'array' | 'object';
        transform?: (value: any) => any;
    }>;
}

export interface XmlTemplate {
    id: string;
    name: string;
    description: string;
    template: string;
    variables: Array<{
        name: string;
        type: 'string' | 'number' | 'date' | 'boolean';
        required: boolean;
        defaultValue?: any;
    }>;
    schema?: XmlSchema;
}

export interface XmlDocument {
    id: string;
    filename: string;
    content: string;
    size: number;
    encoding: string;
    version: string;
    namespace: string;
    created_at: string;
    modified_at: string;
    metadata: Record<string, any>;
    validation?: XmlValidationResult;
}

export interface XmlProcessingOptions {
    preserveWhitespace: boolean;
    ignoreComments: boolean;
    formatOutput: boolean;
    encoding: string;
    namespaceAware: boolean;
    validateSchema: boolean;
}

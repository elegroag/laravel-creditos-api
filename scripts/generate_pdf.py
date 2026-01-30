#!/usr/bin/env python3
"""
Script para generar PDF de solicitudes de crédito.
Este script es llamado desde Laravel para generar PDFs usando las capacidades de Python.
"""

import sys
import json
import os
import logging
from datetime import datetime
from pathlib import Path
from typing import Dict, Any, Optional
from pymongo import MongoClient
from reportlab.lib.pagesizes import letter, A4
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
from reportlab.lib import colors
from reportlab.pdfgen import canvas
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT

# Configuración de logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class PDFGenerator:
    """Generador de PDFs para solicitudes de crédito."""
    
    def __init__(self, db_host: str = "localhost", db_port: int = 27017, db_name: str = "comfaca_credito"):
        self.db_host = db_host
        self.db_port = db_port
        self.db_name = db_name
        self.db = None
        self.styles = getSampleStyleSheet()
        
    def connect_to_db(self) -> bool:
        """Conecta a la base de datos MongoDB."""
        try:
            self.db = MongoClient(self.db_host, self.db_port)[self.db_name]
            return True
        except Exception as e:
            logger.error(f"Error conectando a MongoDB: {e}")
            return False
    
    def generar_pdf_solicitud(self, solicitud_id: str, incluir_convenio: bool = True, incluir_firmantes: bool = True, output_dir: str = None) -> Dict[str, Any]:
        """
        Genera el PDF de una solicitud de crédito.
        
        Args:
            solicitud_id: ID de la solicitud
            incluir_convenio: Si se debe incluir información del convenio
            incluir_firmantes: Si se debe incluir información de firmantes
            output_dir: Directorio de salida para el PDF
            
        Returns:
            Diccionario con el resultado de la operación
        """
        try:
            if not self.connect_to_db():
                return {
                    'success': False,
                    'error': 'No se pudo conectar a la base de datos'
                }
            
            # Obtener datos de la solicitud
            solicitud = self.db.solicitudes_credito.find_one({"_id": solicitud_id})
            if not solicitud:
                return {
                    'success': False,
                    'error': f'Solicitud no encontrada: {solicitud_id}'
                }
            
            # Configurar directorio de salida
            if output_dir is None:
                output_dir = os.path.join(os.getcwd(), 'pdfs', 'solicitudes')
            
            os.makedirs(output_dir, exist_ok=True)
            
            # Generar nombre de archivo
            timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
            filename = f"solicitud_{solicitud_id}_{timestamp}.pdf"
            filepath = os.path.join(output_dir, filename)
            
            # Crear el PDF
            doc = SimpleDocTemplate(
                filepath,
                pagesize=A4,
                rightMargin=72,
                leftMargin=72,
                topMargin=72,
                bottomMargin=18
            )
            
            # Contenido del PDF
            story = []
            
            # Título
            title_style = ParagraphStyle(
                'CustomTitle',
                fontSize=18,
                spaceAfter=30,
                alignment=TA_CENTER,
                textColor=colors.darkblue
            )
            story.append(Paragraph("SOLICITUD DE CRÉDITO", title_style))
            story.append(Spacer(1))
            
            # Información básica de la solicitud
            self._agregar_info_solicitud(story, solicitud)
            
            # Información del solicitante
            self._agregar_info_solicitante(story, solicitud)
            
            # Información del crédito
            self._agregar_info_credito(story, solicitud)
            
            # Convenio (si aplica)
            if incluir_convenio:
                self._agregar_info_convenio(story, solicitud)
            
            # Firmantes (si aplica)
            if incluir_firmantes:
                self._agregar_info_firmantes(story, solicitud)
            
            # Documentos adjuntos
            self._agregar_info_documentos(story, solicitud)
            
            # Timeline
            self._agregar_timeline(story, solicitud)
            
            # Generar PDF
            doc.build(story)
            
            # Verificar que el archivo se creó
            if not os.path.exists(filepath):
                return {
                    'success': False,
                    'error': 'No se pudo crear el archivo PDF'
                }
            
            # Obtener tamaño del archivo
            file_size = os.path.getsize(filepath)
            
            resultado = {
                'success': True,
                'data': {
                    'filename': filename,
                    'path': filepath,
                    'tamano': file_size,
                    'generado_en': datetime.now().isoformat(),
                    'incluir_convenio': incluir_convenio,
                    'incluir_firmantes': incluir_firmantes
                }
            }
            
            logger.info(f"PDF generado exitosamente: {filepath}")
            return resultado
            
        except Exception as e:
            logger.error(f"Error generando PDF: {e}")
            return {
                'success': False,
                'error': str(e)
            }
    
    def _agregar_info_solicitud(self, story: list, solicitud: Dict[str, Any]):
        """Agrega información básica de la solicitud."""
        story.append(Paragraph("INFORMACIÓN DE LA SOLICITUD", self.styles['Heading2']))
        
        data = [
            ['Número de Solicitud:', solicitud.get('numero_solicitud', 'N/A')],
            ['Fecha de Creación:', self._format_date(solicitud.get('created_at'))],
            ['Estado Actual:', solicitud.get('estado', 'N/A')],
            ['Usuario:', solicitud.get('owner_username', 'N/A')]
        ]
        
        table = Table(data, colWidths=[3*inch, 4*inch])
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), colors.lightgrey),
            ('TEXTCOLOR', (0, 0), (-1, -1), colors.black),
            ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
            ('FONTNAME', (0, 0), (-1, -1), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 10),
        ]))
        
        story.append(table)
        story.append(Spacer(1))
    
    def _agregar_info_solicitante(self, story: list, solicitud: Dict[str, Any]):
        """Agrega información del solicitante."""
        story.append(Paragraph("DATOS DEL SOLICITANTE", self.styles['Heading2']))
        
        solicitante = solicitud.get('solicitante', {})
        
        data = [
            ['Nombre Completo:', f"{solicitante.get('nombres_apellidos', 'N/A')}"],
            ['Tipo Documento:', solicitante.get('tipo_identificacion', 'N/A')],
            ['Número Documento:', solicitante.get('numero_identificacion', 'N/A')],
            ['Email:', solicitante.get('email', 'N/A')],
            ['Teléfono:', solicitante.get('telefono_movil', 'N/A')],
            ['Dirección:', solicitante.get('direccion', 'N/A')],
            ['Ciudad:', solicitante.get('ciudad', 'N/A')]
        ]
        
        table = Table(data, colWidths=[2*inch, 4*inch])
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), colors.lightgrey),
            ('TEXTCOLOR', (0, 0), (-1, -1), colors.black),
            ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
            ('FONTNAME', (0, 0), (-1, -1), 'Helvetica'),
            ('FONTSIZE', (0, 0), (-1, -1), 10),
        ]))
        
        story.append(table)
        story.append(Spacer(1))
    
    def _agregar_info_credito(self, story: list, solicitud: Dict[str, Any]):
        """Agrega información del crédito solicitado."""
        story.append(Paragraph("INFORMACIÓN DEL CRÉDITO", self.styles['Heading2']))
        
        data = [
            ['Monto Solicitado:', f"${solicitud.get('monto_solicitado', 0):,.2f}"],
            ['Plazo (meses):', str(solicitud.get('plazo_meses', 0))],
            ['Línea de Crédito:', self._obtener_linea_credito(solicitud)],
            ['Tasa de Interés:', self._obtener_tasa_interes(solicitud)],
            ['Cuota Mensual:', self._calcular_cuota(solicitud)]
        ]
        
        table = Table(data, colWidths=[2.5*inch, 3.5*inch])
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), colors.lightgrey),
            ('TEXTCOLOR', (0, 0), (-1, -1), colors.black),
            ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
            ('FONTNAME', (0, 0), (-1, -1), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 10),
        ]))
        
        story.append(table)
        story.append(Spacer(1))
    
    def _agregar_info_convenio(self, story: list, solicitud: Dict[str, Any]):
        """Agrega información del convenio si aplica."""
        story.append(Paragraph("INFORMACIÓN DEL CONVENIO", self.styles['Heading2']))
        
        # Aquí se agregaría la lógica para obtener información del convenio
        # Por ahora, mostramos información básica
        data = [
            ['Convenio:', 'Información del convenio'],
            ['Empresa:', self._obtener_empresa_convenio(solicitud)],
            ['Nit:', self._obtener_nit_empresa(solicitud)],
            ['Estado:', 'Activo']
        ]
        
        table = Table(data, colWidths=[2*inch, 4*inch])
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), colors.lightgrey),
            ('TEXTCOLOR', (0, 0), (-1, -1), colors.black),
            ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
            ('FONTNAME', (0, 0), (-1, -1), 'Helvetica'),
            ('FONTSIZE', (0, 0), (-1, -1), 10),
        ]))
        
        story.append(table)
        story.append(Spacer(1))
    
    def _agregar_info_firmantes(self, story: list, solicitud: Dict[str, Any]):
        """Agrega información de firmantes si aplica."""
        story.append(Paragraph("INFORMACIÓN DE FIRMANTES", self.styles['Heading2']))
        
        # Aquí se agregaría la lógica para obtener información de firmantes
        # Por ahora, mostramos información básica
        data = [
            ['Firmante 1:', 'Información del firmante'],
            ['Cargo:', 'Cargo del firmante'],
            ['Fecha:', datetime.now().strftime('%Y-%m-%d')]
        ]
        
        table = Table(data, colWidths=[2*inch, 4*inch])
        table.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), colors.lightgrey),
            ('TEXTCOLOR', (0, 0), (-1, -1), colors.black),
            ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
            ('FONTNAME', (0, 0), (-1, -1), 'Helvetica'),
            ('FONTSIZE', (0, 0), (-1, -1), 10),
        ]))
        
        story.append(table)
        story.append(Spacer(1))
    
    def _agregar_info_documentos(self, story: list, solicitud: Dict[str, Any]):
        """Agrega información de documentos adjuntos."""
        documentos = solicitud.get('documentos', [])
        
        if documentos:
            story.append(Paragraph("DOCUMENTOS ADJUNTOS", self.styles['Heading2']))
            
            data = [['Nombre', 'Tipo', 'Fecha de Subida']]
            
            for doc in documentos:
                data.append([
                    doc.get('nombre_original', 'N/A'),
                    doc.get('tipo_mime', 'N/A'),
                    self._format_date(doc.get('fecha_subida'))
                ])
            
            table = Table(data, colWidths=[3*inch, 2*inch, 2*inch])
            table.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (-1, 0), colors.lightgrey),
                ('TEXTCOLOR', (0, 0), (-1, 0), colors.black),
                ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
                ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
                ('FONTSIZE', (0, 0), (-1, 0), 9),
            ]))
            
            story.append(table)
            story.append(Spacer(1))
        else:
            story.append(Paragraph("No se han adjuntado documentos", self.styles['Normal']))
            story.append(Spacer(1))
    
    def _agregar_timeline(self, story: list, solicitud: Dict[str, Any]):
        """Agrega el timeline de la solicitud."""
        timeline = solicitud.get('timeline', [])
        
        if timeline:
            story.append(Paragraph("HISTORIAL DE LA SOLICITUD", self.styles['Heading2']))
            
            data = [['Fecha', 'Estado', 'Detalle']]
            
            for event in timeline:
                data.append([
                    self._format_date(event.get('fecha')),
                    event.get('estado', 'N/A'),
                    event.get('detalle', 'N/A')
                ])
            
            table = Table(data, colWidths=[2*inch, 2*inch, 3*inch])
            table.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (-1, 0), colors.lightgrey),
                ('TEXTCOLOR', (0, 0), (-1, 0), colors.black),
                ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
                ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
                ('FONTSIZE', (0, 0), (-1, 0), 9),
            ]))
            
            story.append(table)
        else:
            story.append(Paragraph("No hay eventos en el timeline", self.styles['Normal']))
    
    def _format_date(self, date_str: str) -> str:
        """Formatea una fecha string a un formato legible."""
        if not date_str:
            return 'N/A'
        
        try:
            if isinstance(date_str, str):
                # Intentar parsear como ISO format
                date_obj = datetime.fromisoformat(date_str.replace('Z', '+00:00'))
                return date_obj.strftime('%Y-%m-%d %H:%M:%S')
            return str(date_str)
        except:
            return str(date_str)
    
    def _obtener_linea_credito(self, solicitud: Dict[str, Any]) -> str:
        """Obtiene la línea de crédito de la solicitud."""
        payload = solicitud.get('payload', {})
        linea_credito = payload.get('linea_credito', {})
        return linea_credito.get('tipcre', 'N/A')
    
    def _obtener_tasa_interes(self, solicitud: Dict[str, Any]) -> str:
        """Obtiene la tasa de interés de la solicitud."""
        # Aquí se implementaría la lógica para obtener la tasa real
        return "12% anual"
    
    def _calcular_cuota(self, solicitud: Dict[str, Any]) -> str:
        """Calcula la cuota mensual aproximada."""
        monto = solicitud.get('monto_solicitado', 0)
        plazo = solicitud.get('plazo_meses', 0)
        tasa_anual = 0.12  # 12% anual
        
        if monto > 0 and plazo > 0:
            tasa_mensual = tasa_anual / 12
            cuota = monto * (tasa_mensual * (1 + tasa_mensual) ** plazo) / ((1 + tasa_mensual) ** plazo - 1)
            return f"${cuota:,.2f}"
        
        return "N/A"
    
    def _obtener_empresa_convenio(self, solicitud: Dict[str, Any]) -> str:
        """Obtiene la empresa del convenio."""
        # Aquí se implementaría la lógica para obtener la empresa real
        return "Empresa Ejemplo S.A."
    
    def _obtener_nit_empresa(self, solicitud: Dict[str, Any]) -> str:
        """Obtiene el NIT de la empresa del convenio."""
        # Aquí se implementaría la lógica para obtener el NIT real
        return "900123456"


def main():
    """Función principal que procesa los argumentos de línea de comandos."""
    if len(sys.argv) != 2:
        print(json.dumps({
            'success': False,
            'error': 'Se requiere un argumento JSON con los parámetros'
        }))
        sys.exit(1)
    
    try:
        params = json.loads(sys.argv[1])
    except json.JSONDecodeError as e:
        print(json.dumps({
            'success': False,
            'error': f'Error en JSON: {e}'
        }))
        sys.exit(1)
    
    # Validar parámetros requeridos
    required_params = ['solicitud_id']
    for param in required_params:
        if param not in params:
            print(json.dumps({
                'success': False,
                'error': f'Parámetro requerido faltante: {param}'
            }))
            sys.exit(1)
    
    # Extraer parámetros
    solicitud_id = params['solicitud_id']
    incluir_convenio = params.get('incluir_convenio', True)
    incluir_firmantes = params.get('incluir_firmantes', True)
    output_dir = params.get('output_dir')
    db_host = params.get('db_host', 'localhost')
    db_port = params.get('db_port', 27017)
    db_name = params.get('db_name', 'comfaca_credito')
    
    # Generar PDF
    generator = PDFGenerator(db_host, db_port, db_name)
    resultado = generator.generar_pdf_solicitud(
        solicitud_id=solicitud_id,
        incluir_convenio=incluir_convenio,
        incluir_firmantes=incluir_firmantes,
        output_dir=output_dir
    )
    
    # Imprimir resultado
    print(json.dumps(resultado, indent=2, default=str))


if __name__ == "__main__":
    main()

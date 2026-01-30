#!/bin/bash

# Script para cambiar de MySQL a SQLite y ejecutar migraciones
echo "🔄 Cambiando configuración de base de datos a SQLite..."

# Limpiar caché de configuración
php artisan config:clear

# Ejecutar migraciones con SQLite
echo "📊 Ejecutando migraciones con SQLite..."
php artisan migrate:fresh --seed

echo "✅ Migraciones completadas exitosamente!"

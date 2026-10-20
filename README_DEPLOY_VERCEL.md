# Proyecto UMSS listo para Vercel

Esta versión mantiene la interfaz y los módulos del proyecto original.

## Cambios realizados
- PHP se mantiene.
- Vercel Functions con `vercel-php@0.9.0`.
- Soporte para PostgreSQL externo (recomendado: Neon).
- Sigue siendo compatible con MySQL/XAMPP en local.
- Carga XLSX/CSV conservada.
- Si la base de datos está vacía, carga automáticamente las bases CSV incluidas.
- No se añadió la opción visual “Base incluida”.

## Despliegue
1. Sube esta carpeta a un repositorio de GitHub.
2. En Vercel: Add New > Project e importa el repositorio.
3. En Storage / Marketplace crea una base Neon Postgres.
4. Verifica que el proyecto tenga una variable de entorno con la conexión.
   La aplicación reconoce `DATABASE_URL`, `POSTGRES_URL` o `NEON_DATABASE_URL`.
5. Añade `AUTO_SEED=1`.
6. Haz Redeploy.
7. Abre la URL pública de Vercel.

En el primer acceso se crean las tablas automáticamente y se cargan las bases incluidas si están vacías.

## Módulos
Inicio, Datos, Titulados, Empleadores, Cruces, Competencias, Simulación y Reportes.


## Administración de datos
El módulo Datos permite:
- Agregar registros: conserva la base actual y suma las nuevas respuestas.
- Cargar nueva base: reemplaza la base activa por el archivo seleccionado.
- Vaciar base: elimina todos los registros del tipo seleccionado, con confirmación previa.

La corrección de conexión Neon mediante DATABASE_URL_UNPOOLED y endpoint explícito está incluida.

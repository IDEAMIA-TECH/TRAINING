# Guía de Implementación en cPanel

## 1. Preparación Local
1. Asegúrate de tener todos los archivos listos:
   ```bash
   composer install --no-dev
   ```
2. Elimina archivos innecesarios:
   - Carpeta .git/
   - .gitignore
   - README.md
   - archivos de desarrollo

## 2. Acceso a cPanel
1. Accede a tu cPanel con las credenciales proporcionadas por tu hosting
2. Localiza la sección "Archivos" o "File Manager"

## 3. Configuración del Dominio
1. En cPanel, ve a "Dominios" o "Domains"
2. Configura el dominio principal o subdominio
3. Anota la ruta del directorio público (usualmente public_html)

## 4. Base de Datos
1. En cPanel, ve a "MySQL Databases"
2. Crea una nueva base de datos:
   - Anota el nombre de la base de datos
   - Crea un nuevo usuario
   - Asigna todos los permisos al usuario
3. Guarda las credenciales:
   - Nombre de la base de datos
   - Usuario
   - Contraseña
   - Host (usualmente localhost)

## 5. Subida de Archivos
1. En File Manager, navega al directorio público
2. Crea una carpeta temporal (ej: temp_upload)
3. Sube el archivo ZIP con todo el proyecto
4. Descomprime el archivo
5. Mueve todos los archivos al directorio correcto
6. Elimina el ZIP y la carpeta temporal

## 6. Configuración del Sistema
1. Renombra config.example.php a config.php
2. Edita config.php con los datos correctos:
   ```php
   // Base de datos
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'tu_base_de_datos');
   define('DB_USER', 'tu_usuario');
   define('DB_PASS', 'tu_password');

   // URL base
   define('BASE_URL', 'https://tu-dominio.com');

   // Configuración de correo
   define('SMTP_HOST', 'tu-servidor-smtp.com');
   define('SMTP_USER', 'tu-email@dominio.com');
   define('SMTP_PASS', 'tu-password');
   define('SMTP_PORT', 587);

   // Claves de APIs
   define('PAYPAL_CLIENT_ID', 'tu-client-id');
   define('PAYPAL_SECRET', 'tu-secret');
   define('STRIPE_PUBLIC_KEY', 'tu-public-key');
   define('STRIPE_SECRET_KEY', 'tu-secret-key');
   ```

## 7. Permisos de Archivos
1. Configura los permisos correctos:
   ```bash
   # Directorios
   find . -type d -exec chmod 755 {} \;
   
   # Archivos
   find . -type f -exec chmod 644 {} \;
   
   # Directorios que necesitan escritura
   chmod -R 777 cache/
   chmod -R 777 assets/uploads/
   chmod -R 777 logs/
   ```

## 8. Importar Base de Datos
1. En cPanel, ve a "phpMyAdmin"
2. Selecciona la base de datos creada
3. Ve a la pestaña "Importar"
4. Sube el archivo database/schema.sql
5. Ejecuta la importación

## 9. Configuración de SSL
1. En cPanel, busca "SSL/TLS Status"
2. Instala el certificado SSL gratuito (Let's Encrypt)
3. Activa HTTPS en la configuración

## 10. Verificación
1. Prueba el acceso al sitio web
2. Verifica el registro de usuarios
3. Prueba la creación de cursos
4. Verifica los pagos de prueba
5. Comprueba el envío de correos

## 11. Mantenimiento
1. Configura copias de seguridad automáticas:
   - En cPanel, busca "Backup"
   - Configura backups diarios/semanales
2. Monitorea los logs del sistema:
   - Revisa logs/system.log
   - Revisa logs/error.log

## 12. Seguridad Adicional
1. Configura el archivo .htaccess:
   ```apache
   # Proteger archivos sensibles
   <FilesMatch "^(config\.php|.*\.log)">
       Order allow,deny
       Deny from all
   </FilesMatch>

   # Forzar HTTPS
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

   # Prevenir listado de directorios
   Options -Indexes

   # Proteger contra XSS y otros ataques
   <IfModule mod_headers.c>
       Header set X-XSS-Protection "1; mode=block"
       Header set X-Frame-Options "SAMEORIGIN"
       Header set X-Content-Type-Options "nosniff"
   </IfModule>
   ```

## Solución de Problemas Comunes

### Error de Conexión a Base de Datos
1. Verifica las credenciales en config.php
2. Confirma que el usuario tenga permisos
3. Prueba el host (localhost o IP específica)

### Errores de Permisos
1. Verifica los permisos de directorios
2. Asegúrate que el usuario de PHP pueda escribir en las carpetas necesarias
3. Revisa los logs de error de PHP

### Problemas con Correos
1. Verifica la configuración SMTP
2. Prueba diferentes puertos (587, 465)
3. Confirma que el hosting permita envío de correos

### Errores de Pago
1. Verifica las claves API en modo prueba/producción
2. Confirma la configuración de webhooks
3. Revisa los logs de transacciones

Para más información, consulta la documentación completa en docs/ 
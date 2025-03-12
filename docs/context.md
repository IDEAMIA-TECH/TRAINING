# 📖 Documentación del Flujo y Características del Sitio Web para Cursos Presenciales

## 📌 1. Introducción
Este documento detalla el flujo de funcionamiento y las características principales del sitio web diseñado para la gestión de cursos presenciales. La plataforma permite a los administradores gestionar entrenamientos y a los clientes registrarse y pagar cursos a través de **PayPal o Stripe**.

---

## 🚀 2. Flujo del Usuario

## tech stack
frontend: html, css, javascript
backend: php, mysql
database: mysql
hosting: cpanel
domain: devgdlhost.com

### 🏠 2.1. Página de Inicio
- El usuario accede a la página principal.
- Se muestra un **banner rotatorio** con entrenamientos próximos.
  - El administrador puede **cambiar imágenes y enlaces de acción** en este banner.
- Se presenta una **sección de cursos futuros**, categorizados según los criterios definidos por el administrador.

### 🔑 2.2. Registro e Inicio de Sesión del Cliente
- Los usuarios pueden **registrarse** con su correo electrónico y datos personales.
- Una vez registrados, pueden **iniciar sesión** para:
  - Visualizar los cursos en los que están inscritos.
  - Consultar el historial de pagos.

### 📅 2.3. Exploración y Registro a Cursos
- Los usuarios pueden navegar por los cursos disponibles con la siguiente información:
  - **Fecha y hora** del curso.
  - **Cupo disponible**.
  - **Costo del curso**.
  - **Temario y galería de imágenes**.
- Pueden **registrarse** en un curso y **realizar el pago en línea** mediante **PayPal o Stripe**.
- Una vez realizado el pago, el usuario recibe un **correo de confirmación** con los detalles del curso y el comprobante de pago.
- El administrador es notificado automáticamente sobre el nuevo registro.

### 🎓 2.4. Panel del Cliente
- El usuario tiene acceso a:
  - Su lista de cursos registrados.
  - Un **calendario** con los cursos futuros.
  - La opción de inscribirse en nuevos cursos y gestionar sus pagos.

---

## 🛠️ 3. Flujo del Administrador

### 🔒 3.1. Panel de Administración
- Acceso restringido mediante **credenciales de administrador**.

### 🎭 3.2. Gestión del Banner Rotatorio
- Funcionalidad para **agregar, modificar o eliminar imágenes y enlaces** en el banner de la página principal.

### 📚 3.3. Gestión de Cursos
- Posibilidad de **crear, modificar y eliminar cursos**.
- Los cursos deben incluir:
  - **Nombre del curso**.
  - **Fecha y hora**.
  - **Capacidad máxima de usuarios**.
  - **Costo del curso**.
  - **Temario y galería de imágenes**.

### 👥 3.4. Gestión de Clientes Registrados
- Visualización de la lista de clientes registrados.
- **Calendario interactivo** con los cursos programados y la lista de inscritos.
- **Reportes de pago**, con detalles de clientes que han pagado y fechas de los entrenamientos.
- **Envío de correos masivos** a usuarios registrados en un curso específico.

---

## 📩 4. Notificaciones Automáticas
El sistema envía notificaciones automáticas para mantener informados a los usuarios y administradores:

- **Al registrarse en un curso**: 
  - El usuario recibe un **correo de confirmación** con los detalles de su inscripción y pago.
  - El administrador recibe una **notificación con los datos del usuario y el curso**.
- **Correos masivos**: 
  - El administrador puede enviar mensajes a todos los usuarios inscritos en un curso.

---

## 💻 5. Requisitos Técnicos
Para garantizar un rendimiento óptimo del sistema, se establecen los siguientes requisitos técnicos:

- **Diseño 100% responsivo** para computadoras y dispositivos móviles.
- **Integración con PayPal y Stripe** para pagos en línea seguros.
- **Panel de administración intuitivo** para gestionar cursos, clientes y reportes.
- **Base de datos optimizada** para almacenar información de clientes y cursos de forma segura.
- **Sistema automatizado de notificaciones** para mantener la comunicación fluida con los usuarios.

---

## 🎯 6. Conclusión
Este sistema está diseñado para **facilitar la administración y gestión de cursos presenciales**, proporcionando una plataforma eficiente para que los clientes puedan **registrarse y pagar de manera rápida y segura**, mientras que los administradores pueden **gestionar cursos y usuarios de forma óptima**.

---

📌 **Notas Adicionales:**
- Posibles mejoras futuras incluyen la **implementación de notificaciones por SMS** y la integración con **Google Calendar** para sincronización de eventos.
- Se recomienda implementar **métricas de seguimiento** para analizar el rendimiento de la plataforma.

✨ *Un sistema diseñado para optimizar la gestión de entrenamientos y mejorar la experiencia del usuario.* 🚀

---

## 🗄️ 6. Estructura de la Base de Datos

### Tablas Principales:

#### users
- id (PK)
- name
- email
- password
- role (admin/client)
- phone
- created_at
- updated_at
- status (active/inactive)

#### courses
- id (PK)
- title
- description
- start_date
- end_date
- capacity
- price
- status (active/inactive)
- created_at
- updated_at

#### course_registrations
- id (PK)
- course_id (FK)
- user_id (FK)
- payment_id (FK)
- status (pending/confirmed/cancelled)
- created_at
- updated_at

#### payments
- id (PK)
- user_id (FK)
- course_id (FK)
- amount
- payment_method (paypal/stripe)
- transaction_id
- status (pending/completed/failed)
- created_at

#### banner_slides
- id (PK)
- image_url
- title
- description
- link
- status (active/inactive)
- order
- created_at
- updated_at

#### course_images
- id (PK)
- course_id (FK)
- image_url
- is_main
- created_at

---

## 📁 7. Estructura de Carpetas
project_root/
├── admin/ # Panel de administración
│ ├── courses/ # Gestión de cursos
│ ├── users/ # Gestión de usuarios
│ ├── payments/ # Gestión de pagos
│ ├── banner/ # Gestión del banner
│ └── reports/ # Reportes y estadísticas
├── assets/
│ ├── css/
│ ├── js/
│ ├── images/
│ └── uploads/ # Imágenes subidas por usuarios
├── config/ # Configuración de la aplicación
├── includes/ # Archivos PHP compartidos
│ ├── database.php
│ ├── functions.php
│ └── auth.php
├── public/ # Archivos públicos
│ ├── index.php
│ ├── courses.php
│ └── register.php
├── templates/ # Plantillas HTML
├── vendor/ # Dependencias de terceros
└── api/ # Endpoints de API
├── payments/
└── notifications/


[... contenido existente desde Conclusión ...]

---

## 🔐 8. Seguridad y Validaciones

### Validaciones de Base de Datos
- Todas las tablas incluyen timestamps para auditoría
- Claves foráneas con integridad referencial
- Índices optimizados para búsquedas frecuentes

### Seguridad de Datos
- Contraseñas hasheadas con algoritmos seguros
- Validación de datos en frontend y backend
- Protección contra SQL injection
- Manejo de sesiones seguro
- Sanitización de inputs


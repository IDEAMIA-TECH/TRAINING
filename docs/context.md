# Documentación del Flujo y Características del Sitio Web

## 1. Introducción

Este documento describe el flujo y las características del sitio web para la gestión de entrenamientos en línea, permitiendo a los clientes registrarse, pagar y acceder a entrenamientos, así como a los administradores gestionar los cursos y usuarios.

## 2. Flujo del Usuario

#Tech Stack

- backend: php, 
- frontend: html, css, javascript
- database: mysql
- hosting: cpanel
- domain: devgdlhost.com
- whatsapp: +52 1 33 16129810
- Extras:
    - Google Analytics
    - PWA (Progressive Web App): Para permitir funcionamiento offline en dispositivos móviles.
    - Integración con WhatsApp: API de WhatsApp Business para notificaciones directas a clientes.



### 2.1. Inicio de la Experiencia

- El cliente accede a la página de inicio
- Visualiza un banner rotatorio con entrenamientos futuros
  - Este banner cuenta con un panel de administración para cambiar imágenes y enlaces de acción

### 2.2. Exploración de Cursos

- En la página de inicio, se muestra una sección de cursos futuros
  - Los cursos se organizan en secciones definidas por el administrador
- Cada curso muestra:
  - Fecha y hora del entrenamiento
  - Cantidad de cupos disponibles
  - Costo del curso
  - Temario y fotos opcionales

### 2.3. Registro y Pago

- El cliente puede registrar una cuenta en la plataforma
- Puede inscribirse en un curso
- Tiene la opción de pagar en línea mediante PayPal o Stripe
- Tras la inscripción y pago, el sistema envía un correo de confirmación al cliente y al administrador con:
  - Datos del cliente
  - Fecha de registro
  - Curso seleccionado

### 2.4. Panel del Cliente

El cliente puede acceder a su panel personal donde encontrará:

- Cursos inscritos y precio pagado
- Calendario con cursos disponibles
- Opción para registrarse y pagar cursos desde el calendario

## 3. Flujo del Administrador

### 3.1. Panel de Administración

El administrador tiene acceso a un panel de control con las siguientes funcionalidades:

- Lista de clientes registrados
- Creación de nuevos cursos con:
  - Fecha y hora
  - Descripción del curso
  - Cupo máximo
  - Costo
  - Foto y temario
- Gestión del calendario de entrenamientos programados

### 3.2. Reportes y Notificaciones

El calendario administrativo muestra:

- Entrenamientos programados
- Clientes registrados por curso
- Reporte de pagos recibidos y fechas
- Sistema de envío de correos masivos a usuarios registrados en cursos específicos

## 4. Características del Sitio Web

### 4.1. Responsividad

El sitio web es 100% responsivo, optimizado para:

- Dispositivos móviles
- Tablets
- Computadoras de escritorio

### 4.2. Seguridad y Pagos

- Integración con múltiples métodos de pago:
  - PayPal
  - Stripe
- Sistema seguro de registro de usuarios
- Sistema automatizado de confirmaciones por correo electrónico

### 4.3. Administración Dinámica

- Panel de administración completo
- Control de cupos y reportes
- Gestión de entrenamientos por usuario

## 5. Conclusión

Este flujo asegura una navegación intuitiva y eficiente tanto para los clientes como para los administradores. La plataforma facilita la inscripción, pago y gestión de entrenamientos de manera organizada y automatizada.


## 6. Estructura de la Base de Datos

### 6.1. Tablas Principales

#### Users
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('admin', 'client') DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Courses
```sql
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    max_capacity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    syllabus TEXT,
    image_url VARCHAR(255),
    status ENUM('active', 'cancelled', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Enrollments
```sql
CREATE TABLE enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_method ENUM('paypal', 'stripe') NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
);
```

### 6.2. Tablas Complementarias

#### Banner_Images
```sql
CREATE TABLE banner_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    image_url VARCHAR(255) NOT NULL,
    title VARCHAR(100),
    action_url VARCHAR(255),
    active BOOLEAN DEFAULT true,
    order_index INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Course_Materials
```sql
CREATE TABLE course_materials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    file_url VARCHAR(255) NOT NULL,
    file_type ENUM('pdf', 'image', 'video') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id)
);
```

#### Notifications
```sql
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    course_id INT,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('email', 'whatsapp') NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
);
```

### 6.3. Índices Importantes
```sql
-- Índices para optimizar búsquedas frecuentes
ALTER TABLE courses ADD INDEX idx_start_date (start_date);
ALTER TABLE enrollments ADD INDEX idx_payment_status (payment_status);
ALTER TABLE notifications ADD INDEX idx_status_type (status, type);
ALTER TABLE users ADD INDEX idx_email (email);
```

### 6.4. Relaciones y Restricciones

- La tabla `enrollments` conecta usuarios con cursos (relación muchos a muchos)
- Cada curso puede tener múltiples materiales asociados
- Las notificaciones pueden estar asociadas a un usuario y opcionalmente a un curso
- Los banners tienen un orden específico para su visualización
- Se mantiene un registro de timestamps para auditoría

## 7. Estructura de Carpetas del Proyecto

```
project_root/
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   └── responsive.css
│   ├── js/
│   │   ├── main.js
│   │   ├── calendar.js
│   │   └── payment.js
│   └── images/
│       ├── banners/
│       └── courses/
├── config/
│   ├── database.php
│   ├── constants.php
│   └── settings.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── functions.php
├── admin/
│   ├── dashboard.php
│   ├── courses/
│   │   ├── create.php
│   │   ├── edit.php
│   │   └── list.php
│   ├── users/
│   │   ├── manage.php
│   │   └── reports.php
│   └── assets/
│       ├── css/
│       └── js/
├── client/
│   ├── dashboard.php
│   ├── profile.php
│   └── courses/
│       ├── enroll.php
│       └── my-courses.php
├── api/
│   ├── payments/
│   │   ├── paypal.php
│   │   └── stripe.php
│   └── notifications/
│       ├── email.php
│       └── whatsapp.php
├── uploads/
│   ├── courses/
│   ├── materials/
│   └── temp/
├── vendor/
├── docs/
│   ├── context.md
│   └── api.md
├── .htaccess
├── index.php
└── README.md
```

### 7.1. Descripción de Carpetas

#### assets/
- Archivos estáticos del frontend
- CSS, JavaScript e imágenes
- Recursos multimedia del sitio

#### config/
- Configuraciones globales
- Conexión a base de datos
- Constantes y settings

#### includes/
- Componentes PHP reutilizables
- Headers y footers
- Funciones helpers

#### admin/
- Panel de administración
- Gestión de cursos
- Gestión de usuarios
- Assets específicos del admin

#### client/
- Panel del cliente
- Perfil de usuario
- Gestión de inscripciones

#### api/
- Endpoints para pagos
- Integraciones con servicios
- Notificaciones

#### uploads/
- Archivos subidos por usuarios
- Materiales de cursos
- Directorio temporal

#### vendor/
- Dependencias de terceros
- Librerías externas

#### docs/
- Documentación del proyecto
- Especificaciones técnicas
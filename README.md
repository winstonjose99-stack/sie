# Sistema de Control de Asistencia Escolar (SIE)

Plataforma web de gestión académica y control de asistencias por secciones con generación de reportes en PDF y notificaciones automatizadas mediante **WhatsApp Cloud API (Meta)** y **Webhooks de Zapier**.

## Características Principales

- **Panel Gerencial (Administración):**
  - Métricas KPI en tiempo real (Estudiantes, Docentes, Secciones Activas e Inasistencias).
  - Gestión completa (CRUD) de Instituciones, Docentes, Materias, Secciones, Representantes y Estudiantes.
  - Administración de Períodos Académicos con promoción automática de matrícula y secciones.
  - Reporte e historial general de inasistencias con filtros y exportación a PDF.
- **Portal Docente:**
  - Toma de asistencia diaria filtrada por sección y período activo (`Presente`, `Ausente`, `Justificado` + motivo).
  - Apartado de **Asistencias Guardadas** por clase y fecha, con pre-carga y edición de registros.
  - Generador nativo de planillas y nóminas en **PDF blanco y negro** listo para impresión.
- **Notificaciones Automatizadas:**
  - Integración con **Webhooks by Zapier** (`HTTP POST` en formato `JSON`) desde `config/zapier.php` y panel de pruebas en `api/webhook_zapier.php`.
  - Integración con **WhatsApp Cloud API** desde `config/whatsapp.php`.

## Requisitos e Instalación en XAMPP

1. Clonar o copiar el proyecto en `C:\xampp\htdocs\Asistencias`.
2. Iniciar **Apache** y **MySQL** en el Panel de Control de XAMPP.
3. Importar el esquema de base de datos desde [`database/sistema_asistencias.sql`](database/sistema_asistencias.sql) en **phpMyAdmin**.
4. (Opcional) Configurar credenciales locales:
   - Copiar `config/whatsapp.local.example.php` a `config/whatsapp.local.php`.
   - Configurar la URL del Webhook de Zapier desde `http://localhost/Asistencias/api/webhook_zapier.php`.
5. Abrir `http://localhost/Asistencias/` en el navegador.

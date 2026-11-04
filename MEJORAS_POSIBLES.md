# Hoja de Ruta y Futuras Mejoras: TallerPro

Documento estratégico con las propuestas de evolución técnica y funcional para consolidar **TallerPro** como una solución de referencia empresarial para talleres mecánicos sobre Dolibarr ERP/CRM.

---

## 1. Operativa en Recepción y Taller (A Pie de Vehículo)

### 1.1. Croquis Interactivo de Inspección de Daños
* **Objetivo**: Evitar reclamaciones de clientes por golpes o arañazos preexistentes.
* **Funcionalidad**:
  * Diagrama interactivo del vehículo (vistas frontal, trasera, laterales y techo).
  * Marcado táctil de desperfectos con tipologías: rayadura, abolladura, rotura o pintura.
  * Registro del nivel de combustible en el momento de entrada (1/4, 1/2, 3/4, lleno) y kilometraje verificado.
  * Adjunto directo de fotografías tomadas desde la cámara del móvil o tableta.

### 1.2. Firma Digital Táctil del Cliente
* **Objetivo**: Cumplimiento legal ágil sin necesidad de imprimir papel en la recepción.
* **Funcionalidad**:
  * Canvas interactivo en modal para capturar la firma del cliente al entregar el vehículo (depósito) o al retirar el vehículo reparado.
  * Incrustación automática de la firma y fecha en el documento PDF de la Orden de Reparación y Resguardo de Depósito.

### 1.3. Presupuestos Previos y Cotizaciones
* **Objetivo**: Gestionar la fase de valoración antes de iniciar los trabajos.
* **Funcionalidad**:
  * Estado o documento de **Presupuesto**.
  * Conversión con un solo clic a **Orden de Trabajo Activa** una vez recibido el visto bueno del cliente (presencial o telefónico).

---

## 2. Automatización y Fidelización de Clientes (B2C)

### 2.1. Notificaciones Rápidas por WhatsApp y SMS
* **Objetivo**: Reducir el tiempo dedicado a llamadas telefónicas y agilizar la recogida de vehículos.
* **Funcionalidad**:
  * Botón directo *"Avisar por WhatsApp"* en órdenes finalizadas.
  * Apertura automática de WhatsApp Web / App con plantilla personalizada:
    > *"Estimado/a [Cliente], su vehículo [Marca] [Modelo] ([Matrícula]) ya está listo para ser recogido en el taller. Importe total: [Total] €. Horario: [Horario]."*

### 2.2. Sistema de Alertas de Mantenimiento e ITV
* **Objetivo**: Generar negocio recurrente fidelizando a la cartera de clientes.
* **Funcionalidad**:
  * Campos de *Próxima ITV* y *Próximo mantenimiento periódico* (por fecha o kilometraje estimado).
  * Panel en el Dashboard con listado de vehículos pendientes de revisión en los próximos 30 días.
  * Envío programado de recordatorios de cita previa.

### 2.3. Seguimiento Online de Estado para el Cliente (Portal QR)
* **Objetivo**: Reducir la incertidumbre del cliente y proyectar una imagen tecnológica moderna.
* **Funcionalidad**:
  * Generación de un código QR en el resguardo de entrada.
  * Enlace público securizado por token donde el cliente puede consultar en tiempo real si su vehículo está *"En diagnóstico"*, *"Esperando repuestos"*, *"En reparación"* o *"Listo para entrega"*.

---

## 3. Planificación de Taller, Boxes y Mecánicos

### 3.1. Calendario / Agenda de Citas y Elevadores
* **Objetivo**: Evitar cuellos de botella en los puestos de trabajo.
* **Funcionalidad**:
  * Vista de calendario semanal con división por elevadores / boxes de trabajo.
  * Asignación visual de citas y duración estimada de la intervención.

### 3.2. Asignación de Mecánicos y Control de Tiempos
* **Objetivo**: Conocer la rentabilidad real de la mano de obra.
* **Funcionalidad**:
  * Asignación de operarios/mecánicos a cada orden de trabajo.
  * Cronómetro simple (*Iniciar / Pausar / Finalizar*) para medir las horas reales trabajadas frente a las horas facturadas al cliente según baremo.

---

## 4. Gestión Económica y Análisis de Rentabilidad

### 4.1. Anticipos y Pagos Parciales
* **Objetivo**: Facilitar cobros a cuenta en reparaciones de alto importe antes de solicitar materiales costosos.
* **Funcionalidad**:
  * Registro de depósitos o entregas a cuenta vinculados a la orden o factura borrador.
  * Desglose en factura: *Total trabajo - Anticipo recibido = Pendiente de cobro*.

### 4.2. Exportación Analítica (Excel / CSV)
* **Objetivo**: Dotar a la gerencia del taller de herramientas de análisis contable.
* **Funcionalidad**:
  * Exportación en un clic de listados filtrados de facturación, márgenes en repuestos, horas de mano de obra y facturas por aseguradora.

---

## 5. Arquitectura Técnica y Escalabilidad

### 5.1. Paginación Server-Side (AJAX)
* **Objetivo**: Rendimiento ultra-rápido en talleres con históricos de más de 5.000 clientes o 10.000 facturas.
* **Funcionalidad**:
  * Implementación de paginación asíncrona (`LIMIT / OFFSET`) para cargar en memoria únicamente los registros visibles en pantalla.

### 5.2. Desacoplamiento de Controladores y Vistas (Patrón MVC Ligero)
* **Objetivo**: Mantenibilidad y facilidad de testing en futuras versiones.
* **Funcionalidad**:
  * Separar la recepción de acciones POST/GET y llamadas a modelos Dolibarr en controladores independientes (`controllers/`).
  * Dejar los archivos principales orientados a la orquestación y renderizado de plantillas limpias (`views/`).

### 5.3. Integración con Catálogos de Recambios (TecDoc / Proveedores)
* **Objetivo**: Búsqueda inmediata de piezas por matrícula o bastidor sin salir de la orden de reparación.
* **Funcionalidad**:
  * Módulos de conexión vía API con distribuidores locales de recambios para consultar precio de coste, stock y tiempo de entrega.

---

## Matriz de Priorización Recomendada

| Fase | Característica | Impacto de Negocio | Esfuerzo Técnico |
| :---: | :--- | :---: | :---: |
| **Fase 1 (Inmediata)** | Aviso WhatsApp de vehículo listo | Alto | Bajo |
| **Fase 1 (Inmediata)** | Exportación de datos a Excel / CSV | Medio | Bajo |
| **Fase 2 (Corto Plazo)** | Croquis de daños y firma táctil | Muy Alto | Medio |
| **Fase 2 (Corto Plazo)** | Alertas preventivas de ITV y mantenimientos | Alto | Medio |
| **Fase 3 (Medio Plazo)**| Calendario de boxes y agenda de citas | Alto | Medio-Alto |
| **Fase 3 (Medio Plazo)**| Control de tiempos por operario / cronómetro | Medio | Medio |
| **Fase 4 (Escala)** | Paginación server-side y arquitectura MVC | Alto | Alto |

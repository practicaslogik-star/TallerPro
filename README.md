# TallerPro - Módulo de Taller para [Dolibarr ERP & CRM](https://www.dolibarr.org)

**TallerPro** es un módulo integral y moderno para la gestión de talleres mecánicos, electromecánicos y de automoción en Dolibarr ERP/CRM. Ofrece una interfaz visual reactiva, sobria y de alto rendimiento optimizada para el flujo de trabajo real del taller: recepción de vehículos, clientes, órdenes de trabajo, control de recambios/stock, aseguradoras y facturación oficial.

---

## Características principales

- **Gestión de Clientes 360°**:
  - Registro y edición modal in situ de clientes particulares y empresas.
  - Sincronización transparente con el módulo nativo de Terceros (`Societe`) de Dolibarr.
  - Ficha de detalle con historial unificado de vehículos, reparaciones y facturas asociadas.
- **Control Técnico de Vehículos**:
  - Fichas completas con matrícula, marca, modelo, año, color, kilometraje y número de bastidor (VIN).
  - Pestañas integradas con historial de reparaciones, coberturas de pólizas de seguro e información del propietario.
- **Órdenes de Reparación y Mano de Obra**:
  - Seguimiento de estados operativos: *En curso*, *Finalizada*, *Facturada*.
  - Partes de trabajo con secciones simétricas e independientes para **Materiales / Recambios** y **Reparaciones / Mano de Obra**.
  - Cálculo automático en tiempo real de bases imponibles, descuentos, tipos de IVA y totales.
  - Facturación con un solo clic directamente desde la orden de trabajo.
- **Catálogo de Materiales y Control de Stock**:
  - Integración directa con los productos y almacenes de Dolibarr.
  - Alertas visuales de existencias mínimas (*En stock*, *Stock bajo*, *Sin existencias*).
- **Gestión de Pólizas y Aseguradoras**:
  - Control de aseguradoras, números de póliza, vigencias y tipos de cobertura (Todo riesgo con/sin franquicia, terceros básico/ampliado, etc.).
- **Facturación Profesional de Taller**:
  - Numeración correlativa y protegida del ERP.
  - Ciclo de estados seguro: *Borrador* (con edición exclusiva de fecha, notas y estado) $\rightarrow$ *Pendiente* $\rightarrow$ *Pagada*.
  - Generación e impresión directa en formato PDF oficial de taller con plantilla moderna (`pdf_factura.lib.php`).
- **Experiencia de Usuario (UI / UX) Moderna y Adaptativa**:
  - Sistema de tokens de diseño (`:root`) con paleta profesional en modo oscuro y acentos semánticos.
  - **Diseño Responsive para Móvil y Tablet**: Menú lateral tipo *drawer* y conversión inteligente de tablas a tarjetas táctiles en pantallas estrechas ($\le$ 820px) para operar cómodamente a pie de vehículo.
  - Invalidador automático de caché de estilos (`filemtime`) en entornos de producción.
- **Seguridad y Robustez Empresarial**:
  - Validación de tokens anti-CSRF en todas las operaciones de escritura (creación, edición, borrado, facturación y cambios de estado).
  - Restricción estricta de métodos HTTP (bloqueo de peticiones no-POST para mutación de datos).
  - Scripts administrativos y de empaquetado aislados de accesos externos directos.

---

## Requisitos previos

- **Dolibarr ERP & CRM**: Versión 16.0 o superior (probado y compatible hasta Dolibarr 22+).
- **PHP**: 7.4 o superior (compatible con PHP 8.1, 8.2 y 8.3).
- **Base de datos**: MySQL 5.7+ o MariaDB 10.3+.

---

## Instalación

### Opción 1: Clonar el repositorio con Git

1. Accede al directorio `custom` de tu instalación de Dolibarr:
   ```bash
   cd /ruta/a/dolibarr/htdocs/custom
   ```
2. Clona el repositorio dentro de la carpeta `taller`:
   ```bash
   git clone https://github.com/practicaslogik-star/TallerPro.git taller
   ```

### Opción 2: Instalación manual (ZIP)

1. Descarga el archivo comprimido del módulo.
2. Extrae o copia la carpeta del módulo dentro del directorio `htdocs/custom/` de Dolibarr asegurándote de que la ruta final sea:
   ```text
   htdocs/custom/taller/
   ```

> **Nota**: Si el directorio `custom` no está habilitado en tu instalación, asegúrate de tener las siguientes líneas configuradas en tu archivo `htdocs/conf/conf.php`:
> ```php
> $dolibarr_main_url_root_alt = '/custom';
> $dolibarr_main_document_root_alt = '/ruta/a/dolibarr/htdocs/custom';
> ```

---

## Activación del módulo

1. Inicia sesión en Dolibarr como usuario con privilegios de administrador.
2. Dirígete a **Inicio > Configuración > Módulos/Aplicaciones**.
3. En el buscador escribe **Taller** y localiza el módulo **TallerPro**.
4. Haz clic en el interruptor para **Activar**.
5. Se habilitará el acceso en el menú principal para comenzar a operar.

---

## Estructura del proyecto

```text
custom/taller/
├── build/                  # Scripts de empaquetado para distribución
├── core/modules/           # Descriptor del módulo Dolibarr (modTaller.class.php)
├── css/                    # Hojas de estilo refactorizadas (tallerpro.css)
├── js/                     # Lógica interactiva y clientes modales (tallerpro.js)
├── lib/                    # Librerías auxiliares (generación de PDF de factura)
├── sql/                    # Scripts SQL de estructura y actualización
├── clientes.php            # Gestión y ficha detallada de clientes
├── vehiculos.php           # Fichas técnicas de vehículos e historial
├── reparaciones.php        # Órdenes de trabajo, partes de taller y facturación
├── materiales.php          # Catálogo de recambios y control de stock
├── seguros.php             # Pólizas de seguro y entidades aseguradoras
├── facturas.php            # Ciclo de facturación oficial y vista documental
├── configuracion.php       # Panel de configuración y parámetros del taller
├── tallerindex.php         # Dashboard principal con métricas y KPIs
├── MEJORAS_POSIBLES.md     # Hoja de ruta y backlog de futuras funcionalidades
└── README.md               # Documentación general del módulo
```

---

## Hoja de Ruta y Futuras Mejoras

Para consultar el backlog detallado con propuestas de evolución (croquis interactivo de inspección de daños, firma digital táctil, avisos por WhatsApp, control de tiempos de mecánicos y planificación de boxes), consulta el documento:
👉 **[MEJORAS_POSIBLES.md](MEJORAS_POSIBLES.md)**

---

## Licencia

- **Código fuente**: Publicado bajo licencia [GPLv3](https://www.gnu.org/licenses/gpl-3.0.html) o cualquier versión posterior.
- **Documentación**: Licencia [GFDL](https://www.gnu.org/licenses/fdl-1.3.html).

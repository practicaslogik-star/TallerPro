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
- **Facturación y Presupuestos de Taller**:
  - Presupuestos comerciales integrados con el módulo nativo `Propal` (`llx_propal`) y enlazados directamente a cada orden de reparación.
  - Numeración correlativa y protegida del ERP para facturas oficiales (`Facture`).
  - Ciclo de estados seguro: *Borrador* (con edición exclusiva de fecha, notas y estado) $\rightarrow$ *Pendiente* $\rightarrow$ *Pagada* (con registro real de cobros `Paiement`).
  - Generación e impresión directa en formato PDF de taller para facturas y presupuestos (`lib/pdf_factura.lib.php`).
- **Internacionalización y Recursos Locales**:
  - Interfaz traducida a **Español (`es_ES`)**, **Inglés (`en_US`)** y **Francés (`fr_FR`)** mediante archivos estándar `.lang` de Dolibarr.
  - Fuente tipográfica `Plus Jakarta Sans` autoalojada localmente (`css/fonts/`) sin dependencias externas en tiempo de ejecución.
- **Experiencia de Usuario (UI / UX) Moderna y Adaptativa**:
  - Diseño limpio optimizado para escritorio, tablet y móvil (menú lateral colapsable y tablas adaptativas en pantallas pequeñas).
- **Seguridad y Robustez Empresarial**:
  - Validación de tokens anti-CSRF, control de permisos por rol (`read`, `write`, `delete`, `facturar`), aislamiento multi-entidad (`getEntity`) y escape HTML en salidas.
  - Empaquetado limpio para distribución y DoliStore mediante `build/buildzip.php`.

---

## Requisitos previos

- **Dolibarr ERP & CRM**: Versión 16.0 o superior (compatible hasta Dolibarr 22+).
- **Módulos nativos requeridos**: Terceros (`modSociete`), Facturación (`modFacture`), Presupuestos comerciales (`modPropale`), Productos (`modProduct`) y Almacenes / Stock (`modStock`). Módulos recomendados: Bancos/Cajas (`modBanque`).
- **PHP**: 7.4 o superior (compatible con PHP 8.1, 8.2 y 8.3).
- **Base de datos**: MySQL 5.7+ o MariaDB 10.3+ (los scripts SQL de `sql/` utilizan sintaxis InnoDB específica para MySQL/MariaDB).

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
├── admin/                  # Páginas de configuración y acerca de Dolibarr
├── build/                  # Scripts de empaquetado para DoliStore (buildzip.php)
├── class/                  # Clases de negocio DAO (vehículos, reparaciones, materiales, seguros)
├── core/modules/           # Descriptor principal del módulo (modTaller.class.php)
├── css/                    # Hoja de estilos (tallerpro.css) y fuentes locales (css/fonts/)
├── img/                    # Iconos PNG y recursos gráficos del módulo
├── js/                     # Lógica interactiva en cliente (tallerpro.js)
├── langs/                  # Archivos de traducción (es_ES, en_US, fr_FR)
├── lib/                    # Librerías auxiliares, i18n JS y generador PDF TCPDF
├── sql/                    # Scripts SQL de tablas para MySQL/MariaDB
├── ChangeLog.md            # Registro de versiones y cambios
├── COPYING                 # Licencia GNU GPLv3
├── README.md               # Documentación del módulo
├── clientes.php            # Gestión y ficha detallada de clientes
├── configuracion.php       # Panel de preferencias del taller e IBAN cifrado
├── facturas.php            # Gestión de facturas (Facture) y presupuestos (Propal)
├── materiales.php          # Catálogo de recambios y control de stock
├── reparaciones.php        # Órdenes de trabajo, partes de taller y facturación
├── seguros.php             # Pólizas de seguro y aseguradoras
├── tallerindex.php         # Dashboard principal con KPIs y búsqueda global
└── vehiculos.php           # Fichas técnicas de vehículos, adjuntos e historial
```

---

## Licencia

- **Licencia**: Publicado bajo la licencia [GNU General Public License v3.0 (GPLv3)](https://www.gnu.org/licenses/gpl-3.0.html) o cualquier versión posterior (véase [COPYING](COPYING)).

---

## English Overview (DoliStore)

**TallerPro** (`taller`) is an automotive workshop management module for **Dolibarr ERP & CRM** (16.0+ up to 22+). It integrates directly with core Dolibarr entities while providing a responsive workflow tailored to garages and repair shops.

### Key Features

- **Customers (`clientes.php`)**: Uses native Dolibarr Third Parties (`Societe`) with direct access to owned vehicles and invoices.
- **Vehicles (`vehiculos.php`)**: Technical vehicle profiles (license plate, brand, model, year, VIN, mileage), repair history, materials used, insurance policies, and attached files (PDF, images, docs).
- **Work Orders & Repairs (`reparaciones.php`)**: Order lifecycle (`en_curso` / `finalizada`), spare parts and labor line items, real-time tax/discount calculations, automatic warehouse stock adjustments, one-click native Quotes (`Propal`) and draft Invoices (`Facture`).
- **Quotes & Invoicing (`facturas.php`, `lib/pdf_factura.lib.php`)**: Unified management of commercial proposals (`Propal`, prefixed `PRE...`) and invoices (`Facture`: Draft $\rightarrow$ Pending $\rightarrow$ Paid with real `Paiement` entries) plus clean TCPDF export.
- **Spare Parts & Stock (`materiales.php`)**: Local parts catalog synchronized with Dolibarr `Product` and `Stock`.
- **Insurance Policies (`seguros.php`)**: Tracks insurer, policy number, coverage type, and validity period per vehicle.
- **Workshop Settings (`configuracion.php`)**: Commercial workshop details and encrypted bank IBAN (`dolEncrypt`) for invoice/PDF display.
- **Languages & Self-Hosted Assets**: Ready in `es_ES`, `en_US`, and `fr_FR`, with local `Plus Jakarta Sans` font files (`css/fonts/`).

### Requirements & Installation

- **Dolibarr**: 16.0+
- **Required Core Modules**: Third Parties (`modSociete`), Invoices (`modFacture`), Commercial Proposals (`modPropale`), Products (`modProduct`), Warehouses/Stock (`modStock`). Recommended: Banks/Cash (`modBanque`).
- **PHP**: 7.4+ (tested on PHP 8.x).
- **Database**: MySQL 5.7+ or MariaDB 10.3+ (InnoDB SQL scripts in `sql/`).
- **Install**: Upload or clone into `htdocs/custom/taller/` (or install the DoliStore ZIP package via **Home > Setup > Modules/Applications > Deploy/install external app**), then enable **TallerPro** under **Modules/Applications**.

### License

Licensed under the [GNU General Public License v3.0 (GPLv3+)](https://www.gnu.org/licenses/gpl-3.0.html). See `COPYING`.

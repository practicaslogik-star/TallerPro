# TallerPro - Módulo de Taller para [Dolibarr ERP & CRM](https://www.dolibarr.org)

**TallerPro** es un módulo integral y moderno para la gestión de talleres mecánicos y de automoción en Dolibarr ERP/CRM. Ofrece una interfaz visual optimizada para gestionar el flujo de trabajo diario: clientes, vehículos, reparaciones, materiales, aseguradoras y facturación.

---

## Características principales

- **Gestión de Clientes**: Registro completo de clientes particulares y empresas, vinculados automáticamente con terceros de Dolibarr.
- **Control de Vehículos**: Fichas técnicas completas con matrícula, marca, modelo, año, kilometraje, número de bastidor (VIN) y cliente propietario.
- **Órdenes de Reparación y Mano de Obra**: Registro y seguimiento del estado de reparaciones (pendiente, en proceso, terminada, facturada), tiempos y descripción de averías.
- **Catálogo de Materiales y Recambios**: Base de datos de piezas con referencia, descripción y precios unitarios. Integración directa al agregar líneas en facturas o reparaciones.
- **Gestión de Aseguradoras**: Control de pólizas de seguros, números de póliza y entidades aseguradoras asociadas a los vehículos.
- **Facturación Profesional de Taller**:
  - Numeración correlativa y automática anual bajo el estándar `NNNN/YY` (ej. `0001/26`), protegida contra modificaciones accidentales.
  - Vista documental detallada con secciones independientes para materiales, mano de obra y desglose de totales (subtotal, descuentos, base imponible e IVA).
  - Generación e impresión directa en formato PDF.
  - Notas de factura visibles de forma permanente.

---

## Requisitos previos

- **Dolibarr ERP & CRM**: Versión 16.0 o superior (compatible con Dolibarr 20, 21 y 22).
- **PHP**: 7.4 o superior (incluyendo PHP 8.1, 8.2 y 8.3).
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

1. Descarga el código del módulo.
2. Copia la carpeta del módulo dentro del directorio `htdocs/custom/` de Dolibarr asegurándote de que la ruta final sea:
   ```text
   htdocs/custom/taller/
   ```

> **Nota**: Si el directorio `custom` no está habilitado, asegúrate de tener las siguientes líneas descomentadas en tu archivo `htdocs/conf/conf.php`:
> ```php
> $dolibarr_main_url_root_alt = '/custom';
> $dolibarr_main_document_root_alt = '/ruta/a/dolibarr/htdocs/custom';
> ```

---

## Activación del módulo

1. Inicia sesión en Dolibarr como usuario administrador.
2. Dirígete a **Inicio > Configuración > Módulos/Aplicaciones**.
3. En la pestaña de módulos o buscando por "Taller", localiza el módulo **TallerPro**.
4. Haz clic en el interruptor para **Activar**.
5. Se creará automáticamente la entrada en el menú principal para acceder a **TallerPro**.

---

## Estructura del proyecto

```text
custom/taller/
├── admin/                  # Páginas de configuración del módulo
├── core/modules/           # Descriptor del módulo Dolibarr (modTaller.class.php)
├── css/                    # Hojas de estilo personalizadas (tallerpro.css)
├── js/                     # Scripts interactivos y lógica AJAX (tallerpro.js)
├── langs/                  # Archivos de idiomas y traducciones
├── sql/                    # Scripts SQL de creación y migración de tablas
├── clientes.php            # Vista y gestión de clientes
├── vehiculos.php           # Vista y gestión de vehículos
├── reparaciones.php        # Vista y gestión de órdenes de trabajo
├── materiales.php          # Catálogo de materiales y recambios
├── seguros.php             # Control de pólizas y aseguradoras
├── facturas.php            # Facturación y vista documental
└── tallerindex.php         # Panel de inicio / Dashboard de TallerPro
```

---

## Licencia

- **Código fuente**: Publicado bajo licencia [GPLv3](https://www.gnu.org/licenses/gpl-3.0.html) o cualquier versión posterior.
- **Documentación**: Licencia [GFDL](https://www.gnu.org/licenses/fdl-1.3.html).

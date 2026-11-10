# CHANGELOG MODULE TALLER FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0.0

Initial release of **TallerPro** (`taller`), a workshop management module for Dolibarr ERP & CRM:

- **Customers (`clientes.php`)**: Native integration with Dolibarr Third Parties (`Societe`) including vehicles and invoices overview.
- **Vehicles (`vehiculos.php`)**: Full vehicle master records (`llx_taller_vehiculo`), repair history, materials used, insurance details, and document uploads.
- **Work Orders & Repairs (`reparaciones.php`)**: Repair lifecycle management (`llx_taller_reparacion`), spare parts and labor breakdown with automatic VAT/discount calculation, native stock consumption, direct quote creation, and draft invoice generation (`Facture`).
- **Quotes & Invoicing (`facturas.php`, `lib/pdf_factura.lib.php`)**: Native Dolibarr commercial proposals (`Propal` / `PRE...`) for workshop quotes, native `Facture` workflow (`Borrador` -> `Pendiente` -> `Pagada`) with real `Paiement` recording, and custom TCPDF export. Automatic idempotent migration of legacy draft `PRE...` records to `llx_propal`.
- **Parts & Stock (`materiales.php`)**: Spare parts catalog (`llx_taller_material`) synchronized with Dolibarr `Product` and `Stock` warehouses.
- **Insurance Policies (`seguros.php`)**: Insurance companies, policy numbers, coverage types, and validity dates linked to vehicles (`llx_taller_seguro`).
- **Workshop Preferences (`configuracion.php`)**: Workshop branding, encrypted IBAN storage (`dolEncrypt`), and display toggle for invoices/PDFs.
- **Internationalization & Offline Assets**: Translations in Spanish (`es_ES`), English (`en_US`), and French (`fr_FR`), plus self-hosted `Plus Jakarta Sans` font files (`css/fonts/`).
- **Security & Packaging**: Permission enforcement, CSRF token verification, `POST`-only state mutations, HTML output escaping, and clean DoliStore archive builder (`build/buildzip.php`).

# INVOICE

Facturas y presupuestos para un autónomo o una pequeña empresa de España.

> ## ⚠️ Esta aplicación no tiene login
>
> INVOICE está pensada para **una sola empresa o persona** y no tiene cuentas de
> usuario ni contraseña. Cualquiera que llegue a la dirección de la aplicación puede ver
> y emitir facturas.
>
> - Ejecútala en tu equipo (`http://127.0.0.1:8000`) o en una red privada.
> - Si necesitas acceder desde fuera, pon delante un proxy con contraseña o una VPN.
>   Tienes ejemplos en [docs/deploy.md](docs/deploy.md).
> - Nunca la publiques directamente en Internet.

## Qué hace

- Configura tu nombre, tu empresa y tu logotipo, que aparecen en todas las facturas.
- Gestiona clientes y un catálogo de productos y servicios.
- Crea presupuestos, conviértelos en facturas y emite rectificativas.
- Calcula IVA, recargo de equivalencia e IRPF con el redondeo que valida Hacienda.
- Numera las facturas de forma correlativa y sin huecos, y las bloquea al emitir.
- Genera el PDF y lo envía por email al cliente.
- Marca las facturas como cobradas para ver de un vistazo qué está pendiente o vencido.
  El cobro lo gestiona el cliente fuera de la aplicación.

Ámbito fiscal: España, territorio común. La preparación para VeriFactu está en el
modelo de datos; el envío a la AEAT llegará en una fase posterior.

## Requisitos

- PHP 8.4 con las extensiones `bcmath`, `intl`, `pdo_pgsql`, `fileinfo`, `gd` y `zip`
- Composer 2
- Node 22 y npm
- PostgreSQL 17
- Google Chrome o Chromium, para generar los PDF

## Puesta en marcha en local

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
# Edita .env: datos de PostgreSQL, cuenta de correo y ruta de Chrome.

php artisan migrate --seed
php artisan storage:link

npm run dev
php artisan serve
php artisan queue:work
```

Abre `http://127.0.0.1:8000`. Lo primero que pide la aplicación es configurar el
emisor: tu nombre, tu empresa (opcional), tu logotipo y tus datos fiscales.

Si prefieres Docker, consulta [docs/deploy.md](docs/deploy.md).

## Tests

Los tests usan una base PostgreSQL real llamada `invoice_test`.

```bash
php artisan test
php artisan test --group=concurrency
npm run lint
npm run types
```

## Ramas

- `develop`: desarrollo. Cada tarea es un commit.
- `main`: producción. Solo recibe versiones probadas.

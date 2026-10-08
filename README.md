# INVOICE

**Facturación para autónomos y pequeñas empresas de España**, hecha con el rigor que
exige Hacienda: importes exactos al céntimo, numeración sin huecos y facturas que no se
pueden modificar una vez emitidas.

[![CI](https://github.com/AndyGEINF/Invoice-app/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/AndyGEINF/Invoice-app/actions/workflows/ci.yml)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![React 19](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)
![TypeScript](https://img.shields.io/badge/TypeScript-strict-3178C6?logo=typescript&logoColor=white)
![PostgreSQL 17](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql&logoColor=white)

> **⚠️ La aplicación no tiene login.** Está pensada para una sola persona o empresa y no
> tiene cuentas de usuario. Ejecútala en tu equipo o en una red privada; si necesitas
> acceder desde fuera, ponla detrás de una VPN o de un proxy con contraseña
> ([docs/deploy.md](docs/deploy.md)). Nunca la publiques directamente en Internet.

---

## En pocas palabras

| | |
|---|---|
| **Qué es** | Una aplicación web para crear facturas y presupuestos, gestionar clientes y un catálogo, y saber qué está pendiente de cobro. |
| **Para quién** | Un autónomo o una pyme española (territorio común, sin País Vasco, Navarra ni Canarias). |
| **Qué la diferencia** | Las reglas fiscales no dependen de la buena voluntad del código: la base de datos impide modificar una factura emitida y un test lanza 50 emisiones simultáneas para comprobar que la numeración nunca se repite ni deja huecos. |
| **Calidad** | Más de 400 tests automáticos contra PostgreSQL real, integración continua en cada push y tipado estricto en todo el frontend. |
| **Forma de trabajo** | Desarrollo guiado por especificación: requisitos → plan técnico → tareas. Cada tarea es un commit. |

---

## Qué puede hacer ya

**Facturas**
- Crear la factura sobre una vista que imita el papel: lo que ves es lo que sale en el PDF.
- Cálculo de **IVA, recargo de equivalencia y retención de IRPF** en el servidor, con el redondeo por tipo impositivo que aplica la Agencia Tributaria.
- Factura completa o **simplificada** según tenga o no NIF el cliente, con aviso si la simplificada supera el límite legal.
- Causas de exención (E1–E6) y operaciones no sujetas.
- **Emisión con número correlativo**: el número se asigna al emitir, nunca antes, y desde ese momento la factura queda bloqueada.
- Vista previa del documento y listado con filtros, totales de lo filtrado y avisos de facturas vencidas y borradores sin emitir.

**Clientes y catálogo**
- Clientes particulares o empresas, con contactos, condiciones de pago y notas.
- Validación del **NIF, NIE y CIF** con su dígito de control, y de los números de IVA europeos.
- Comprobación automática en **VIES** (el registro europeo de operadores) en segundo plano. Si VIES no responde, se reintenta más tarde y nunca bloquea la facturación.
- Aviso si das de alta un NIF que ya existe, con opción de abrir el existente o continuar.
- Catálogo de productos y servicios con precios de hasta tres decimales (33,333 €/h). Al añadirlos a una factura se copian sus datos, así que cambiar el catálogo no altera nada de lo ya facturado.
- Buscadores con teclado para elegir cliente y producto mientras escribes.

**Tu empresa**
- Nombre, logotipo, NIF, dirección, régimen de IVA y retención por defecto.
- **Color de marca** para el PDF. Cada factura guarda el color con el que se emitió.
- La aplicación no deja emitir hasta que los datos obligatorios están completos y dice exactamente qué falta.

**Experiencia de uso**
- Panel de inicio con lo facturado en el mes, lo pendiente de cobro y lo vencido.
- Diseño adaptado a móvil, con barra de navegación inferior, y **modo oscuro**.
- Atajo `Ctrl + S` para guardar el borrador.

## Lo que viene

| Fase | Contenido | Estado |
|---|---|:---:|
| 1–3 | Base del proyecto, motor de impuestos, emisión de facturas | ✅ |
| 4 | Datos del emisor, logotipo y color de marca | ✅ |
| 5 | Clientes, catálogo y validación VIES | ✅ (falta el rediseño de la ficha de cliente) |
| 6 | **Presupuestos** y conversión a factura con un clic | ⏳ |
| 7 | **PDF** definitivo y **envío por email** al cliente, en segundo plano | ⏳ |
| 8 | **Facturas rectificativas** (por sustitución y por diferencias) | ⏳ |
| 9 | Series de numeración configurables (por ejemplo, una serie por actividad) | ⏳ |
| 10 | Marcar facturas como cobradas y seguimiento de lo pendiente | ⏳ |
| 11 | Gráficos en el panel, búsqueda global con `Ctrl + K` y pulido general | ⏳ |
| Después | **VeriFactu**: registro encadenado de facturas y envío a la AEAT, ya previsto en el modelo de datos | 🗺️ |
| Después | Crear facturas **por voz** a partir de una nota hablada | 🗺️ |

---

## Decisiones técnicas que importan

Una aplicación de facturación tiene poco margen para el error: un céntimo de más o un
número repetido es un problema con Hacienda. Estas son las decisiones que lo evitan.

**El dinero nunca es un número decimal.** Los importes se guardan como enteros (céntimos
para totales, milésimas para precios unitarios) y se operan con aritmética de precisión
arbitraria (`bcmath`). Nunca se usa `float` ni se redondea un decimal binario. Todo pasa
por *value objects* (`Money`, `UnitPrice`, `Quantity`, `Percentage`).

**Redondeo fiscal por tipo, no por línea.** Las líneas se suman sin redondear, se agrupan
por tipo de IVA, recargo y exención, y solo entonces se redondea la base y se calcula la
cuota. El algoritmo tiene vectores de prueba calculados a mano y un test de regresión
con once facturas típicas.

**Una factura emitida es inmutable, y lo garantizan dos capas.** La aplicación lo impide
con *observers* de Eloquent; además, unos **triggers de PostgreSQL** rechazan cualquier
`UPDATE` o `DELETE` sobre una factura emitida, sus líneas o sus impuestos, aunque alguien
salte la aplicación y escriba SQL a mano. Corregir una factura obliga a emitir una
rectificativa, como exige la normativa.

**Numeración sin huecos bajo concurrencia.** El número se asigna dentro de una
transacción con bloqueo pesimista sobre la serie (`SELECT … FOR UPDATE`). Un test lanza
**50 emisiones en procesos paralelos** y comprueba que salen los números del 1 al 50, sin
repetidos ni huecos.

**Lo emitido queda congelado.** Al emitir se guarda una copia versionada de los datos del
emisor y del cliente. Si el cliente se muda o cambias tu logotipo o tu color de marca,
las facturas antiguas siguen mostrando los datos de entonces.

**Arquitectura por capas, con puertos y adaptadores.** El dominio (`app/Domain`) no
depende de la infraestructura. Los servicios externos se usan a través de interfaces,
con un adaptador real y otro falso para los tests: VIES, el reloj del sistema y, más
adelante, el generador de PDF. Los casos de uso viven en `app/Application`, y la capa
HTTP solo valida y presenta.

**Trabajo en segundo plano idempotente.** Las tareas lentas o que dependen de terceros,
como VIES y más adelante el PDF y el email, van en cola. La validación VIES es única por
cliente, reintenta con espera exponencial, no repite una comprobación de hace menos de
30 días y se encola después de confirmar la transacción, para no interferir con el
guardado.

**La interfaz nunca calcula impuestos.** El frontend muestra y envía texto (`"33,333"`)
y el servidor devuelve el desglose ya calculado. No puede haber diferencias entre lo que
se ve y lo que se guarda.

## Calidad

- **Más de 400 tests** con [Pest](https://pestphp.com), que se ejecutan contra
  **PostgreSQL real**, nunca contra SQLite, para probar también los triggers y los
  bloqueos.
  - Unitarios: dinero, porcentajes, NIF, motor de impuestos.
  - De integración: emisión, inmutabilidad, borradores, clientes, catálogo, VIES y
    ajustes.
  - De concurrencia: numeración con 50 procesos paralelos.
- **Integración continua** en GitHub Actions en cada push y pull request: lint y tipos
  del frontend, compilación, migraciones, tests y test de concurrencia.
- **Estilo y tipos**: Laravel Pint en PHP, ESLint en el frontend y TypeScript en modo
  estricto.
- **Sin "números mágicos"**: los límites, tipos impositivos y plazos viven en constantes
  o en `config/invoice.php`.
- **Accesibilidad**: formularios con etiquetas y errores asociados, buscadores manejables
  con teclado y roles ARIA.

## Cómo se ha desarrollado

El proyecto sigue un flujo de **desarrollo guiado por especificación**. Primero se
escribe qué debe hacer la aplicación (historias de usuario y criterios de aceptación),
después el plan técnico y los contratos (rutas, puertos, motor de impuestos) y por
último una lista de tareas ordenadas por dependencias.

- Cada tarea corresponde a **un commit** (`T0xx: descripción`), de modo que el
  historial de git cuenta cómo se ha construido la aplicación paso a paso.
- Las funcionalidades se prueban también en un navegador real antes de darlas por
  buenas, en escritorio y en móvil.
- Ramas: `develop` para el trabajo diario y `main` solo para versiones aprobadas.

## Tecnología

| Capa | Herramientas |
|---|---|
| Backend | PHP 8.4, Laravel 13, colas de Laravel |
| Base de datos | PostgreSQL 17 (restricciones `CHECK`, triggers PL/pgSQL, `jsonb`) |
| Frontend | React 19, TypeScript, Inertia.js 3, Tailwind CSS 4, componentes Radix/shadcn |
| Tests | Pest, PostgreSQL real, tests de concurrencia con procesos paralelos |
| Calidad | GitHub Actions, Laravel Pint, ESLint |

---

## Instalación

### Requisitos

- PHP 8.4 con las extensiones `bcmath`, `intl`, `pdo_pgsql`, `fileinfo`, `gd` y `zip`
- Composer 2
- Node 22 y npm
- PostgreSQL 17 (en local o con Docker)

### Puesta en marcha

```bash
git clone https://github.com/AndyGEINF/Invoice-app.git
cd Invoice-app

# Base de datos con Docker (si no tienes PostgreSQL instalado)
docker run -d --name invoice-pg -p 5432:5432 \
  -e POSTGRES_USER=invoice -e POSTGRES_PASSWORD=invoice -e POSTGRES_DB=invoice \
  postgres:17

# Dependencias, .env, clave, migraciones y assets en un solo paso
composer setup

# Datos iniciales (series de numeración)
php artisan db:seed
php artisan storage:link
```

Revisa en `.env` los datos de conexión a PostgreSQL (`DB_*`). Después arranca todo
(servidor, cola, logs y Vite) con un solo comando:

```bash
composer dev
```

Abre <http://127.0.0.1:8000>. Lo primero que pide la aplicación es configurar tu
empresa: nombre, logotipo y datos fiscales.

### Datos de demostración

Para probarla con datos ficticios (empresa configurada, clientes, productos, facturas
emitidas, una vencida y otra cobrada, y presupuestos):

```bash
php artisan db:seed --class=DemoSeeder
```

### Tests

Los tests necesitan una segunda base de datos llamada `invoice_test`:

```bash
docker exec invoice-pg createdb -U invoice invoice_test

php artisan test                        # unitarios e integración
php artisan test --group=concurrency    # 50 emisiones en paralelo
npm run lint && npm run types           # frontend
```

### Despliegue

Para instalarla en un servidor (Docker o no), con proxy y contraseña, cola de trabajos,
Chromium para los PDF, correo y copias de seguridad, consulta
[docs/deploy.md](docs/deploy.md).

---

## Estructura del proyecto

```
app/
├── Domain/          Reglas de negocio: documentos, impuestos, clientes, catálogo, emisor
│   └── Shared/      Value objects: Money, UnitPrice, Quantity, Percentage, TaxId, Address
├── Application/     Casos de uso: CreateDraft, IssueInvoice, UpsertCustomer…
├── Infrastructure/  Adaptadores de servicios externos (VIES, reloj)
├── Http/            Controladores, validación y presentación para el frontend
├── Jobs/            Trabajos en cola (validación VIES)
└── Observers/       Inmutabilidad de documentos emitidos
database/migrations/ Esquema, restricciones y triggers de PostgreSQL
resources/js/        Frontend en React + TypeScript (páginas, componentes, tipos)
resources/views/pdf/ Plantilla del documento (vista previa y PDF)
tests/               Unit, Feature (PostgreSQL real) y Concurrency
```

## Alcance

Lo que la aplicación **no** hace, a propósito:

- **No tiene usuarios ni login.** Hay un único emisor y el acceso se protege en el
  despliegue.
- **No gestiona cobros.** No guarda importes cobrados ni formas de pago: solo permitirá
  marcar una factura como cobrada. El estado de cobro (pendiente, cobrada, vencida) se
  calcula, no se guarda.
- **Solo territorio común.** País Vasco, Navarra y Canarias tienen sus propios impuestos
  y quedan fuera.

## Autor

Desarrollado por **Andy** ([@AndyGEINF](https://github.com/AndyGEINF)).

## Licencia

Pendiente de definir. Mientras tanto, el código se puede consultar pero no reutilizar
sin permiso del autor.

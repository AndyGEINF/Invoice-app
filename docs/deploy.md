# Despliegue de INVOICE

## 1. Antes de nada: la aplicación no tiene login

INVOICE no tiene usuarios ni contraseñas. Quien alcance su dirección puede ver clientes,
emitir facturas y enviarlas por email en nombre del emisor. La protección del acceso es
responsabilidad del despliegue.

Elige una de estas opciones:

| Escenario | Qué hacer |
|---|---|
| Uso en tu propio equipo | Ejecuta en `127.0.0.1`. Es la opción por defecto. |
| Varias personas en la misma oficina | Red privada y cortafuegos que solo deje entrar a esa red. |
| Acceso desde fuera | VPN (WireGuard, Tailscale) o proxy inverso con contraseña y HTTPS. |

Nunca publiques el puerto de la aplicación directamente en Internet.

### Proxy con contraseña: Caddy

```caddyfile
facturas.tu-dominio.es {
    basic_auth {
        # Genera el hash con: caddy hash-password
        andy $2a$14$REEMPLAZA_POR_EL_HASH
    }
    reverse_proxy 127.0.0.1:8080
}
```

Caddy obtiene el certificado HTTPS automáticamente. Sin HTTPS, la contraseña viaja en
claro: no uses `basic_auth` sobre HTTP.

### Proxy con contraseña: Nginx

```nginx
server {
    listen 443 ssl;
    server_name facturas.tu-dominio.es;

    ssl_certificate     /etc/letsencrypt/live/facturas.tu-dominio.es/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/facturas.tu-dominio.es/privkey.pem;

    # Crea el fichero con: htpasswd -c /etc/nginx/.htpasswd andy
    auth_basic           "INVOICE";
    auth_basic_user_file /etc/nginx/.htpasswd;

    client_max_body_size 5m;

    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_set_header   Host $host;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
    }
}
```

## 2. Despliegue con Docker

El fichero `docker/compose.yml` levanta tres servicios:

- `postgres`: PostgreSQL 17 con un volumen persistente.
- `app`: PHP 8.4-FPM y Nginx. Solo escucha en `127.0.0.1:8080`.
- `worker`: cola de trabajos con Chromium. Genera los PDF, envía los emails y valida
  los NIF intracomunitarios.

Pasos:

```bash
cp .env.example .env
# Genera la clave y cópiala en APP_KEY:
php artisan key:generate --show
# Rellena en .env como mínimo: APP_KEY, APP_URL, DB_PASSWORD y la cuenta de correo.

docker compose -f docker/compose.yml --env-file .env up -d --build
```

Al arrancar, el contenedor `app` ejecuta las migraciones, crea el emisor vacío y las
series por defecto, y enlaza el almacenamiento público. Abre la URL y configura el
emisor.

Para cambiar el puerto local, define `INVOICE_HTTP_PORT` en `.env`.

## 3. Despliegue sin Docker

1. Instala PHP 8.4 con `bcmath`, `intl`, `pdo_pgsql`, `fileinfo`, `gd` y `zip`,
   PostgreSQL 17, Node 22 y Chromium.
2. Clona el repositorio, ejecuta `composer install --no-dev` y `npm ci && npm run build`.
3. Configura `.env` con `APP_ENV=production` y `APP_DEBUG=false`.
4. Ejecuta `php artisan migrate --force --seed`, `php artisan storage:link` y
   `php artisan optimize`.
5. Sirve `public/` con Nginx y PHP-FPM, escuchando solo en `127.0.0.1`.
6. Mantén un proceso `php artisan queue:work --tries=3` siempre activo con systemd o
   Supervisor.

## 4. Generación de PDF (Chromium)

Browsershot usa Chrome o Chromium sin interfaz.

- En Docker ya está instalado en el `worker`.
- En Linux: `apt install chromium` y define `BROWSERSHOT_CHROME_PATH=/usr/bin/chromium`.
- En Windows: define la ruta con comillas simples, porque las dobles rompen las barras
  invertidas:

  ```dotenv
  BROWSERSHOT_CHROME_PATH='C:\Program Files\Google\Chrome\Application\chrome.exe'
  ```

Browsershot también necesita Node y el paquete `puppeteer`. Si Node no está en el PATH,
define `BROWSERSHOT_NODE_BINARY` y `BROWSERSHOT_NPM_BINARY`.

## 5. Cola de trabajos

La cola usa la base de datos (`QUEUE_CONNECTION=database`). Sin un worker activo, los
PDF no se generan y los emails no salen.

```bash
php artisan queue:work --tries=3 --backoff=30
```

Opcional: con mucha carga puedes usar Redis. Define `QUEUE_CONNECTION=redis` y los
datos `REDIS_*`.

## 6. Almacenamiento

- PDF emitidos: disco `documents`, en `storage/app/private/documents`. Nunca se sirven
  directamente.
- Logotipos: disco `logos`, en `storage/app/public/logos`. Cada versión del logo se
  guarda con el hash de su contenido y no se borra, porque las facturas antiguas la
  referencian.

Opcional: para guardar los PDF en S3 o MinIO, define `INVOICE_DOCUMENTS_DISK=s3` y las
variables `AWS_*`.

## 7. Email

Configura tu propia cuenta SMTP en `.env`. El nombre del emisor aparece como remitente
y su email como dirección de respuesta.

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.tu-proveedor.com
MAIL_PORT=587
MAIL_USERNAME=facturas@tu-dominio.es
MAIL_PASSWORD=********
MAIL_FROM_ADDRESS=facturas@tu-dominio.es
```

## 8. Copias de seguridad

Las facturas emitidas deben conservarse durante el plazo legal. Haz copia de:

- La base de datos: `pg_dump -Fc invoice > invoice-$(date +%F).dump`
- El directorio `storage/app`, que contiene los PDF y los logotipos.

Guarda las copias fuera del servidor y prueba a restaurarlas de vez en cuando.

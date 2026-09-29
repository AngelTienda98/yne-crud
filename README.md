# YNE CRUD

Aplicación de prueba para administrar productos e inventario con PHP, MySQL y una interfaz web. Incluye una API REST de productos protegida por sesión y permisos por rol.

## Requisitos

- XAMPP con Apache y MySQL/MariaDB.
- PHP 7.3 o posterior, con PDO MySQL y `mbstring` habilitados.
- Una base de datos llamada `ynecrud` con las tablas del proyecto.

## Instalación local

1. Clona el repositorio dentro de `C:\xampp\htdocs`:

	```powershell
	git clone https://github.com/AngelTienda98/yne-crud.git C:\xampp\htdocs\yne-crud
	```

2. Inicia Apache y MySQL desde el panel de XAMPP.
3. Crea o importa la base de datos `ynecrud` en MySQL. El archivo de respaldo `database/ERP.sql` está excluido del repositorio por `.gitignore`; si no lo tienes localmente, necesitarás obtener un respaldo de prueba por separado. El modelo está descrito en [`docs/modelo-entidad-relacion.md`](docs/modelo-entidad-relacion.md).
4. Revisa la conexión en `config/conexion.php` y ajusta host, usuario y contraseña a tu instalación (detalles abajo).
5. Abre `http://localhost/yne-crud/` en el navegador.

## Configuración de base de datos

La clase `Database` de `config/conexion.php` usa actualmente estos valores:

```php
private $host = "localhost";
private $db = "ynecrud";
private $user = "root";
private $password = "";
```

Modifica esas propiedades para que coincidan con tu servidor MySQL. En una instalación predeterminada de XAMPP, `root` suele tener contraseña vacía; si configuraste una, escríbela en `$password`. La conexión PDO usa `utf8` y lanza excepciones ante errores SQL.

Estos valores están escritos directamente en el archivo y son apropiados solo para desarrollo local. No uses credenciales de producción en este proyecto de prueba.

## Acceso de demostración

La pantalla de inicio muestra las cuentas de prueba `admin`, `moderador` y `ayudante`, todas con contraseña `123456`. Úsalas solo con datos locales de prueba y cambia o elimina esas credenciales antes de desplegar la aplicación en un entorno accesible públicamente.

## API REST de productos

URL base:

```text
http://localhost/yne-crud/api/v1/productos.php
```

La API usa la sesión PHP creada al iniciar sesión desde `modules/auth/controller/LoginController.php`. No usa tokens Bearer: conserva la cookie de sesión entre peticiones. Los cuerpos de `POST` y `PUT` deben ser objetos JSON y enviarse con `Content-Type: application/json`.

### Iniciar y cerrar sesión

En `curl.exe`, guarda la cookie de sesión durante el login:

```powershell
curl.exe -c cookies.txt -X POST "http://localhost/yne-crud/modules/auth/controller/LoginController.php?accion=login" -d "username=admin&password=123456"
```

La respuesta correcta incluye `"success":true`. Usa `-b cookies.txt` en las siguientes llamadas para enviar la sesión. Para cerrar sesión:

```powershell
curl.exe -b cookies.txt -c cookies.txt -X POST "http://localhost/yne-crud/modules/auth/controller/LoginController.php?accion=logout"
```

### Operaciones disponibles

| Método | URL | Permiso |
| --- | --- | --- |
| `GET` | `/api/v1/productos.php` | Cualquier usuario autenticado |
| `GET` | `/api/v1/productos.php?id=4` | Cualquier usuario autenticado |
| `POST` | `/api/v1/productos.php` | `ADMIN` o `MODERADOR` |
| `PUT` | `/api/v1/productos.php?id=4` | `ADMIN` o `MODERADOR` |
| `DELETE` | `/api/v1/productos.php?id=4` | Solo `ADMIN` |

Las rutas de la tabla se agregan al dominio local, por ejemplo `http://localhost/yne-crud`. Ejemplos (ejecuta primero el login anterior):

**Listar productos**

```powershell
curl.exe -b cookies.txt "http://localhost/yne-crud/api/v1/productos.php"
```

**Consultar un producto**

```powershell
curl.exe -b cookies.txt "http://localhost/yne-crud/api/v1/productos.php?id=4"
```

**Crear un producto**

```powershell
curl.exe -b cookies.txt -X POST "http://localhost/yne-crud/api/v1/productos.php" -H "Content-Type: application/json" --data-raw '{"nombre":"Paracetamol","descripcion":"Tabletas de 500 mg","presentacion":"Caja con 20 tabletas","lote":"PAR-2026-001","cantidad":100,"precio":45.50,"fecha_caducidad":"2027-12-31","categoria":"Medicamentos","estatus":"Activo"}'
```

**Actualizar un producto**

`PUT` requiere los mismos campos que `POST`:

```powershell
curl.exe -b cookies.txt -X PUT "http://localhost/yne-crud/api/v1/productos.php?id=4" -H "Content-Type: application/json" --data-raw '{"nombre":"Paracetamol","descripcion":"Tabletas de 500 mg","presentacion":"Caja con 20 tabletas","lote":"PAR-2026-001","cantidad":100,"precio":45.50,"fecha_caducidad":"2027-12-31","categoria":"Medicamentos","estatus":"Activo"}'
```

**Eliminar un producto**

```powershell
curl.exe -b cookies.txt -X DELETE "http://localhost/yne-crud/api/v1/productos.php?id=4"
```

Un producto requiere nombre, descripción, presentación, lote, cantidad, precio, fecha de caducidad y categoría. Los estatus aceptados son `Activo`, `Rechazado`, `Cancelado` y `Sin existencia`. Crear un producto también crea su ubicación inicial; si no se puede eliminar por tener movimientos o recepciones relacionados, la API responde `409 Conflict`.

### Respuestas y errores

Las respuestas son JSON. Las respuestas exitosas usan `{"success":true,...}`; los errores usan `{"success":false,"message":"..."}`. Códigos habituales:

- `400`: JSON mal formado o cuerpo que no es un objeto.
- `401`: no hay sesión válida; inicia sesión y conserva su cookie.
- `403`: el rol no tiene permiso para la operación.
- `404`: producto inexistente.
- `409`: conflicto con el inventario o historial del producto.
- `415`: falta `Content-Type: application/json` en `POST` o `PUT`.
- `422`: ID o datos de producto no válidos.
- `500`: error interno; revisa el log de errores de PHP.
# CINEA Backend - Quick Start Guide

## 🚀 Inicio Rápido (5 minutos)

### Prerrequisitos
- Docker y Docker Compose instalados
- Terminal/Bash
- Acceso a `/home/oficial/Documentos/gofriz/public_html/cinea`

### Paso 1: Iniciar Sistema
```bash
cd /home/oficial/Documentos/gofriz/public_html/cinea
chmod +x setup.sh
./setup.sh
```

O manualmente:
```bash
# Iniciar Docker
docker compose up -d

# Resetear BD
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan migrate:fresh --seed
```

### Paso 2: Acceder a la Aplicación
```
Panel Admin:  http://localhost:8000/admin
API Base:     http://localhost:8000/api
```

### Paso 3: Obtener Token de API
```bash
# Entrar a tinker
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan tinker

# En tinker, ejecutar:
$user = User::first();
$user->createToken('api-token')->plainTextToken;

# Copiar el token (sin comillas)
```

---

## 📝 Ejemplos Rápidos

### 1. Listar Cines (Sin autenticación)
```bash
curl -X GET http://localhost:8000/api/cinemas
```

### 2. Listar Películas (Sin autenticación)
```bash
curl -X GET http://localhost:8000/api/movies
```

### 3. Buscar Funciones por Fecha
```bash
curl -X GET "http://localhost:8000/api/screenings?date=2025-11-15"
```

### 4. Ver Asientos Disponibles
```bash
curl -X GET http://localhost:8000/api/screenings/1/available-seats
```

### 5. Comprar Entradas (Requiere Token)
```bash
curl -X POST http://localhost:8000/api/tickets \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "screening_id": 1,
    "seat_ids": [1, 2, 3]
  }'
```

### 6. Ver Mis Entradas (Requiere Token)
```bash
curl -X GET http://localhost:8000/api/tickets \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🛠️ Comandos Útiles

### Base de Datos
```bash
# Resetear todo
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan migrate:fresh --seed

# Ver estado migraciones
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan migrate:status

# Rollback última migración
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan migrate:rollback
```

### Open Admin
```bash
# (Re)instalar Open Admin
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan admin:install

# Generar menús admin
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan db:seed --class=AdminMenuSeeder
```

### Cache
```bash
# Limpiar cache
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan cache:clear

# Regenerar config cache
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan config:cache
```

### Tinker (PHP REPL)
```bash
# Acceder a tinker
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan tinker

# Ver usuarios
User::all();

# Crear película
Movie::create(['title' => 'Test', 'genre' => 'Drama', 'duration' => 120, 'release_date' => now()]);

# Salir
exit;
```

---

## 🔍 Estructura Principal

```
API Endpoints:
├── Públicos (sin token)
│   ├── GET  /api/cinemas
│   ├── GET  /api/movies
│   ├── GET  /api/rooms
│   ├── GET  /api/screenings
│   └── GET  /api/screenings/{id}/available-seats
│
└── Protegidos (requieren token Bearer)
    ├── CRUD /api/cinemas
    ├── CRUD /api/movies
    ├── CRUD /api/rooms
    ├── CRUD /api/screenings
    └── POST/GET/DELETE /api/tickets

Panel Admin:
├── /admin/cinemas
├── /admin/movies
├── /admin/rooms
├── /admin/screenings
└── /admin/tickets
```

---

## 📊 Datos Disponibles

**Después de ejecutar `migrate:fresh --seed`:**

- 2 usuarios de prueba
- 3 cines con datos reales
- 7 salas (2-3 por cine)
- 5 películas diferentes
- 420+ funciones generadas
- Miles de asientos
- Datos listos para pruebas

---

## 🔐 Seguridad

### Autenticación API
```
Token: Authorization: Bearer {token}
Tipo: Laravel Sanctum
Expira: Nunca (configurable)
```

### Autorización
- **Cines/Películas/Salas/Funciones**: Solo admin puede editar
- **Tickets**: Solo el propietario ve sus entradas
- **Lectura**: Abierta al público

---

## 📚 Documentación Completa

- `API_DOCUMENTATION.md` - Guía detallada
- `TECHNICAL_SUMMARY.md` - Resumen técnico
- `ARCHITECTURE.md` - Diagrama de arquitectura
- `config/cinema.php` - Configuración

---

## ✅ Verificar Instalación

### 1. Verificar API
```bash
# Debe devolver JSON con cines
curl http://localhost:8000/api/cinemas
```

### 2. Verificar Panel Admin
```
Abrir: http://localhost:8000/admin
Usuario: admin@example.com
Password: password
```

### 3. Verificar Base de Datos
```bash
# Ver estado
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan tinker

# En tinker:
Cinema::count();  # Debe devolver 3
Movie::count();   # Debe devolver 5
Screening::count(); # Debe devolver 400+
```

---

## 🐛 Solución de Problemas

### Error: "Unable to start container"
```bash
# Reiniciar Docker
docker compose down
docker compose up -d
```

### Error: "Integrity constraint violation"
```bash
# Resetear BD
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan migrate:fresh --seed
```

### Open Admin no muestra menús
```bash
# Regenerar menús
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan db:seed --class=AdminMenuSeeder
```

### Token inválido
```bash
# Generar nuevo token en tinker
docker compose exec -w /var/www/public_html/cinea phpv83 php artisan tinker
# User::first()->createToken('api-token')->plainTextToken;
```

---

## 📞 URLs Importantes

| Recurso | URL | Auth |
|---------|-----|------|
| Admin | http://localhost:8000/admin | Sí |
| API Base | http://localhost:8000/api | No |
| Cines | http://localhost:8000/api/cinemas | No |
| Películas | http://localhost:8000/api/movies | No |
| Funciones | http://localhost:8000/api/screenings | No |
| Entradas | http://localhost:8000/api/tickets | Sí |

---

## 🚀 Próximo Paso

Importar `CINEA_API.postman_collection.json` en Postman para pruebas completas.

---

**Listo para desarrollar** ✅

# Learning Platform API - JWT Authentication Migration

##  Migráció Sanctumról JWT-re - SIKERES!

A Learning Platform API sikeresen átállt **Laravel Sanctum**-ról **JWT (JSON Web Token)** alapú autentikációra.

---

##  Mi változott?

### 1. **JWT Package Telepítése**
```bash
composer require tymon/jwt-auth
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret
```

### 2. **User Model Frissítése**
- Eltávolítva: `HasApiTokens` trait (Sanctum)
- Hozzáadva: `JWTSubject` interface implementálása
- Új metódusok:
  - `getJWTIdentifier()` - JWT azonosító
  - `getJWTCustomClaims()` - Custom claim-ek (jelenleg üres)

### 3. **Auth Config Módosítása**
`config/auth.php`:
```php
'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

### 4. **AuthController Frissítése**
**Új endpointok:**
- `POST /api/login` - JWT token generálás
- `POST /api/logout` - JWT token invalidálás  
- `POST /api/refresh` - JWT token frissítése
- `GET /api/me` - Bejelentkezett felhasználó adatai

**Login válasz most tartalmazza:**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Test User",
    "email": "test@example.com",
    "role": "student"
  },
  "access": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

### 5. **Routes Frissítése**
`routes/api.php`:
- `auth:sanctum` → `auth:api`
- Új route: `POST /api/refresh`
- Új route: `GET /api/me`

### 6. **Tesztek Frissítése**
- `Sanctum::actingAs($user)` → `$this->actingAsUser($user)->getJson(...)`
- Új `actingAsUser()` helper metódus minden test osztályban
- JWT token generálás tesztekhez: `JWTAuth::fromUser($user)`

---

##  Használat

### Postman Collection
Importáld a **`Learning_Platform_JWT.postman_collection.json`** fájlt Postmanbe.

**Fontos lépések:**
1. **Register** - Új felhasználó létrehozása
2. **Login** - JWT token megszerzése (automatikusan elmentésre kerül)
3. **Bármely védett endpoint** - A token automatikusan hozzáadódik

### API Endpointok

#### Publikus
- `GET /api/ping` - Health check
- `POST /api/register` - Regisztráció
- `POST /api/login` - Bejelentkezés (JWT token)

#### Védett (JWT token szükséges)
- `POST /api/logout` - Kijelentkezés
- `POST /api/refresh` - Token frissítése
- `GET /api/me` - Bejelentkezett felhasználó
- `GET /api/users/me` - Profil részletek + statisztikák
- `PUT /api/users/me` - Profil frissítése
- `GET /api/users` - Összes felhasználó (Admin)
- `GET /api/users/{id}` - Felhasználó részletei (Admin)
- `DELETE /api/users/{id}` - Felhasználó törlése (Admin)
- `GET /api/courses` - Kurzusok listája
- `GET /api/courses/{id}` - Kurzus részletei
- `POST /api/courses/{id}/enroll` - Beiratkozás
- `PATCH /api/courses/{id}/completed` - Teljesítés

### JWT Token használata kézi teszteléshez
```bash
# Authorization header formátuma
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

---

## 🧪 Tesztek

Minden teszt sikeres! 

```bash
php artisan test
```

**Eredmény:**
-  24 teszt átment
-  129 assertion sikeres
-  AuthTest - JWT login/register tesztek
-  UserTest - JWT auth user management
-  CourseTest - JWT auth course operations

---

##  JWT vs Sanctum - Főbb Különbségek

| Tulajdonság | Sanctum (Előző) | JWT (Jelenlegi) |
|-------------|-----------------|-----------------|
| Token tárolás | Adatbázis (`personal_access_tokens` tábla) | Nincs adatbázis tárolás |
| Token formátum | Random string | Kódolt JSON (3 részből: header.payload.signature) |
| Token érvényesség | Nincs lejárat (manuális törlésig) | Automatikus lejárat (default: 60 perc) |
| Token frissítés | Nem támogatott | `POST /api/refresh` endpoint |
| Teljesítmény | Lassabb (DB query) | Gyorsabb (nincs DB query) |
| Skálázhatóság | Központi DB szükséges | Stateless, könnyebben skálázható |
| Token visszavonás | Egyszerű (DB delete) | Nehezebb (blacklist vagy short expiry) |

---

##  Fájlok

### Létrehozott/Módosított:
-  `app/Models/User.php` - JWT interface
-  `app/Http/Controllers/AuthController.php` - JWT login/logout/refresh
-  `routes/api.php` - auth:api middleware
-  `config/auth.php` - JWT guard
-  `config/jwt.php` - JWT konfiguráció
-  `tests/Feature/AuthTest.php` - JWT tesztek
-  `tests/Feature/UserTest.php` - JWT tesztek
-  `tests/Feature/CourseTest.php` - JWT tesztek
-  **`Learning_Platform_JWT.postman_collection.json`** - Teljes Postman collection

---

##  Konfiguráció

### JWT beállítások
`.env`:
```env
JWT_SECRET=<generált_secret_key>
JWT_TTL=60  # Token élettartam percben (default: 60)
JWT_REFRESH_TTL=20160  # Refresh token élettartam percben (default: 2 hét)
```

### JWT TTL módosítása
`config/jwt.php`:
```php
'ttl' => env('JWT_TTL', 60),  // Token lejárat (perc)
'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),  // Refresh lejárat
```

---

##  Előnyök

1. **Stateless autentikáció** - Nincs szükség adatbázis lekérdezésre minden kérésnél
2. **Jobb teljesítmény** - Gyorsabb válaszidő
3. **Könnyebb skálázhatóság** - Több szerver között könnyen megosztható
4. **Automatikus lejárat** - Biztonságosabb, tokenek automatikusan lejárnak
5. **Token refresh** - Folyamatos munka megszakítás nélkül

---

##  Megjegyzések

- A JWT tokenek a válaszban érkeznek és a kliens feladata tárolni (localStorage, sessionStorage, stb.)
- A token lejárati idő után a `/api/refresh` endpoint használható új token megszerzésére
- Logout esetén a token invalidálódik (blacklist-re kerül)
- Minden védett endpoint `Authorization: Bearer <token>` headert vár

---


**Következő lépések:**
1. Importáld a `Learning_Platform_JWT.postman_collection.json` fájlt
2. Állítsd be a `base_url` változót (`http://localhost` vagy a szerver címed)
3. Futtasd a `Register` → `Login` → bármely védett endpoint tesztet
4. Élvezd a gyors JWT alapú autentikációt! 🚀

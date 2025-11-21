# nPanel Database Management Feature

## 📋 Übersicht

Domain-isoliertes MySQL Database Management System für nPanel. Jede Domain kann eigene MySQL-Datenbanken erstellen und verwalten, die nur für diese Domain zugreifbar sind.

## 🎯 Ziele

- **Domain-Isolierung**: Datenbanken sind nur für die zugehörige Domain zugänglich
- **Sicherheit**: Separate MySQL-User pro Domain mit eingeschränkten Rechten
- **Einfache Verwaltung**: CRUD-Operationen für Datenbanken über UI
- **phpMyAdmin Integration**: Passwordless Login per SSO-Token
- **Isolation vom Panel**: Client-Datenbanken getrennt von nPanel-Laravel-DB

## 🏗️ Architektur

### Database Schema

```
databases
  - id (BIGINT UNSIGNED, PK)
  - domain_id (BIGINT UNSIGNED, FK -> domains.id)
  - database_name (VARCHAR 64, UNIQUE) Format: {domain}_{name}
  - mysql_user (VARCHAR 32) Format: {domain}_{random}
  - mysql_password (VARCHAR 255, encrypted)
  - mysql_host (VARCHAR 255, default: 127.0.0.1)
  - charset (VARCHAR 32, default: utf8mb4)
  - collation (VARCHAR 32, default: utf8mb4_unicode_ci)
  - size_mb (DECIMAL 10,2, nullable) Calculated size
  - created_at (TIMESTAMP)
  - updated_at (TIMESTAMP)

Indexes:
  - domain_id (für schnelle Domain-Abfragen)
  - database_name (UNIQUE für Namenskollisionen)

Relationships:
  - belongsTo(Domain)
```

### Naming Conventions

**Datenbank-Namen:**
```
Format: {domain_clean}_{db_name}
Beispiel: example_com_wordpress
         npanelat_shop_db

Regeln:
- Domain-Name: Punkte durch Unterstriche ersetzen, nur a-z0-9_
- DB-Name: Nur Kleinbuchstaben, Zahlen, Unterstriche
- Max. Länge: 64 Zeichen (MySQL Limit)
- Prefix verhindert Kollisionen zwischen Domains
```

**MySQL User:**
```
Format: {domain_clean}_{random5}
Beispiel: example_com_x7k2p
         npanelat_m9w4a

Regeln:
- 5 zufällige Zeichen (a-z0-9)
- Max. 32 Zeichen (MySQL Limit)
- Pro Datenbank ein eigener User
```

**Passwörter:**
```
- Generiert: 32 Zeichen, alphanumerisch + Sonderzeichen
- Verschlüsselt in nPanel-DB gespeichert
- Nur bei Erstellung angezeigt, danach verborgen
```

## 🔐 Sicherheitskonzept

### 1. MySQL User Isolation

```sql
-- Bei Datenbank-Erstellung:
CREATE DATABASE `example_com_wordpress` 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'example_com_x7k2p'@'127.0.0.1' 
    IDENTIFIED BY 'generated_secure_password';

GRANT ALL PRIVILEGES ON `example_com_wordpress`.* 
    TO 'example_com_x7k2p'@'127.0.0.1';

FLUSH PRIVILEGES;
```

**Wichtig:**
- User hat NUR Zugriff auf eigene Datenbank
- Kein Zugriff auf: `information_schema`, `mysql`, `performance_schema`, `npanel`
- Keine globalen Rechte (kein `CREATE USER`, `GRANT`, etc.)
- Verbindung nur von `127.0.0.1` (localhost TCP)

### 2. Panel-Datenbank Isolation

- nPanel Laravel-DB: `npanel` (separate Verbindung)
- Client-DBs: Dynamisch erstellt, völlig getrennt
- Kein Schema-Sharing zwischen Panel und Clients

### 3. phpMyAdmin Single Sign-On

**Ablauf:**

```
1. User klickt "phpMyAdmin" in nPanel
2. Backend generiert temporären Token:
   - Token = hash(database_id + timestamp + secret)
   - Speichert in Redis: token => {db_id, user, password, expires_at}
   - TTL: 5 Minuten
3. Redirect zu: /phpmyadmin/index.php?token={token}
4. phpMyAdmin Custom Auth Plugin:
   - Liest Token aus URL
   - Holt Credentials aus Redis
   - Authentifiziert automatisch
   - Löscht Token nach Login
5. User ist eingeloggt ohne Passwort-Eingabe
```

**phpMyAdmin Konfiguration:**
```php
// /var/www/phpmyadmin/config.inc.php
$cfg['Servers'][1]['auth_type'] = 'signon';
$cfg['Servers'][1]['SignonSession'] = 'npanel_phpmyadmin';
$cfg['Servers'][1]['SignonURL'] = 'https://panel.domain/phpmyadmin/signon';
$cfg['Servers'][1]['LogoutURL'] = 'https://panel.domain/domains/{domain}/databases';
```

**Alternative: Adminer**
- Leichtgewichtige Alternative (single file)
- Einfachere Integration
- Gleicher Token-basierter SSO-Mechanismus

## 🎨 UI/UX Design

### Domain Detail Page - Button Platzierung

```
┌─────────────────────────────────────────────────┐
│  example.com                     [Active] [SSL] │
│                                                  │
│  [📄 Files] [💾 Databases] [🔒 SSL] [✏️ Edit] [🗑️]│
└─────────────────────────────────────────────────┘
```

**Neuer "Databases" Button:**
- Position: Zwischen "Files" und "Issue SSL"
- Icon: 💾 (Database/Disk Icon)
- Farbe: Indigo/Blue (#4F46E5)
- Route: `/domains/{domain}/databases`

### Database Management Page

**URL:** `/domains/{domain}/databases`

**Layout:**

```
┌────────────────────────────────────────────────────┐
│ Databases for example.com           [+ New Database]│
├────────────────────────────────────────────────────┤
│                                                    │
│  ┌──────────────────────────────────────────┐    │
│  │ 📊 example_com_wordpress   [Active]      │    │
│  │                                           │    │
│  │ Size: 15.3 MB                            │    │
│  │ Created: 2025-11-20 14:30                │    │
│  │                                           │    │
│  │ [ℹ️ Info] [🌐 phpMyAdmin] [🗑️ Delete]      │    │
│  └──────────────────────────────────────────┘    │
│                                                    │
│  ┌──────────────────────────────────────────┐    │
│  │ 📊 example_com_shop       [Active]       │    │
│  │                                           │    │
│  │ Size: 8.7 MB                             │    │
│  │ Created: 2025-11-19 10:15                │    │
│  │                                           │    │
│  │ [ℹ️ Info] [🌐 phpMyAdmin] [🗑️ Delete]      │    │
│  └──────────────────────────────────────────┘    │
│                                                    │
│  [📥 Import SQL] [📤 Export All]                  │
└────────────────────────────────────────────────────┘
```

### Create Database Modal

```
┌─────────────────────────────────────────┐
│ Create New Database                 [✕] │
├─────────────────────────────────────────┤
│                                         │
│ Database Name *                         │
│ ┌─────────────────────────────────────┐ │
│ │ wordpress                            │ │
│ └─────────────────────────────────────┘ │
│ Full name: example_com_wordpress        │
│                                         │
│ Character Set                           │
│ ┌─────────────────────────────────────┐ │
│ │ utf8mb4                        [▼]  │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ Collation                               │
│ ┌─────────────────────────────────────┐ │
│ │ utf8mb4_unicode_ci             [▼]  │ │
│ └─────────────────────────────────────┘ │
│                                         │
│           [Cancel]  [Create Database]   │
└─────────────────────────────────────────┘
```

**Validation:**
- Database Name: required, max:50, regex:/^[a-z0-9_]+$/
- Auto-Prefix mit Domain-Name
- Duplikat-Check

### Database Info Modal

```
┌─────────────────────────────────────────┐
│ Database Information                [✕] │
├─────────────────────────────────────────┤
│                                         │
│ Database Name                           │
│ example_com_wordpress                   │
│                                         │
│ MySQL User                              │
│ example_com_x7k2p                       │
│                                         │
│ MySQL Password          [👁️ Show]  [📋] │
│ ••••••••••••••••••••••                 │
│                                         │
│ Host                                    │
│ 127.0.0.1                              │
│                                         │
│ Port                                    │
│ 3306                                    │
│                                         │
│ Character Set                           │
│ utf8mb4                                │
│                                         │
│ Collation                               │
│ utf8mb4_unicode_ci                     │
│                                         │
│ Size                                    │
│ 15.3 MB                                │
│                                         │
│ Created                                 │
│ 2025-11-20 14:30:15                    │
│                                         │
│ Connection String (PHP)       [📋 Copy]│
│ ┌─────────────────────────────────────┐ │
│ │ mysqli_connect('127.0.0.1',         │ │
│ │   'example_com_x7k2p',              │ │
│ │   'password',                        │ │
│ │   'example_com_wordpress');         │ │
│ └─────────────────────────────────────┘ │
│                                         │
│                          [Close]        │
└─────────────────────────────────────────┘
```

**Features:**
- Password initial versteckt, zeigbar per Button
- Copy-to-Clipboard für alle Felder
- Connection Strings für PHP, Node.js, Python

### Success Notification nach Erstellung

```
┌─────────────────────────────────────────────┐
│ ✅ Database Created Successfully           │
├─────────────────────────────────────────────┤
│ Database: example_com_wordpress             │
│ User: example_com_x7k2p                     │
│ Password: Xk9@mP2$vL8#nQ4&wR7              │
│                                             │
│ ⚠️ Save password now! It won't be shown     │
│    again for security reasons.              │
│                                             │
│ [📋 Copy All Info]  [Open phpMyAdmin]       │
└─────────────────────────────────────────────┘
```

## 🔧 Backend Implementation

### 1. Migration

**File:** `database/migrations/xxxx_create_databases_table.php`

```php
Schema::create('databases', function (Blueprint $table) {
    $table->id();
    $table->foreignId('domain_id')
          ->constrained()
          ->onDelete('cascade');
    $table->string('database_name', 64)->unique();
    $table->string('mysql_user', 32);
    $table->text('mysql_password'); // Encrypted
    $table->string('mysql_host')->default('127.0.0.1');
    $table->string('charset', 32)->default('utf8mb4');
    $table->string('collation', 32)->default('utf8mb4_unicode_ci');
    $table->decimal('size_mb', 10, 2)->nullable();
    $table->timestamps();
    
    $table->index('domain_id');
});
```

### 2. Model

**File:** `app/Models/Database.php`

```php
class Database extends Model
{
    protected $fillable = [
        'domain_id',
        'database_name',
        'mysql_user',
        'mysql_password',
        'mysql_host',
        'charset',
        'collation',
        'size_mb',
    ];

    protected $hidden = [
        'mysql_password', // Hide from JSON by default
    ];

    protected $casts = [
        'size_mb' => 'decimal:2',
    ];

    // Relationships
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    // Accessors
    public function getDecryptedPasswordAttribute()
    {
        return decrypt($this->mysql_password);
    }

    // Helpers
    public function calculateSize(): float
    {
        // Query MySQL for database size
        $result = DB::connection('mysql_root')->select(
            "SELECT SUM(data_length + index_length) / 1024 / 1024 AS size_mb
             FROM information_schema.TABLES
             WHERE table_schema = ?",
            [$this->database_name]
        );
        
        return round($result[0]->size_mb ?? 0, 2);
    }

    public function getConnectionString(string $language = 'php'): string
    {
        $templates = [
            'php' => "mysqli_connect('{host}', '{user}', '{password}', '{database}');",
            'laravel' => "DB::connection('mysql')->...",
            'node' => "mysql.createConnection({host: '{host}', user: '{user}', ...})",
        ];

        return str_replace(
            ['{host}', '{user}', '{password}', '{database}'],
            [$this->mysql_host, $this->mysql_user, '[HIDDEN]', $this->database_name],
            $templates[$language] ?? $templates['php']
        );
    }
}
```

**Update Domain Model:**

```php
// app/Models/Domain.php
public function databases()
{
    return $this->hasMany(Database::class);
}
```

### 3. Service Layer

**File:** `app/Services/DatabaseService.php`

```php
class DatabaseService
{
    protected MySQLRootConnection $mysqlRoot;

    public function __construct()
    {
        $this->mysqlRoot = new MySQLRootConnection();
    }

    /**
     * Create new database for domain
     */
    public function createDatabase(Domain $domain, array $data): Database
    {
        DB::transaction(function () use ($domain, $data) {
            // Generate names
            $databaseName = $this->generateDatabaseName($domain, $data['name']);
            $mysqlUser = $this->generateUserName($domain);
            $mysqlPassword = $this->generateSecurePassword();

            // Create database record
            $database = Database::create([
                'domain_id' => $domain->id,
                'database_name' => $databaseName,
                'mysql_user' => $mysqlUser,
                'mysql_password' => encrypt($mysqlPassword),
                'mysql_host' => '127.0.0.1',
                'charset' => $data['charset'] ?? 'utf8mb4',
                'collation' => $data['collation'] ?? 'utf8mb4_unicode_ci',
            ]);

            // Execute MySQL commands
            $this->mysqlRoot->createDatabase($database);
            $this->mysqlRoot->createUser($database, $mysqlPassword);
            $this->mysqlRoot->grantPermissions($database);

            return $database;
        });
    }

    /**
     * Delete database and user
     */
    public function deleteDatabase(Database $database): bool
    {
        DB::transaction(function () use ($database) {
            // Drop database
            $this->mysqlRoot->dropDatabase($database->database_name);
            
            // Drop user
            $this->mysqlRoot->dropUser($database->mysql_user, $database->mysql_host);
            
            // Delete record
            $database->delete();
        });

        return true;
    }

    /**
     * Generate database name with domain prefix
     */
    protected function generateDatabaseName(Domain $domain, string $name): string
    {
        // Clean domain name: example.com -> example_com
        $domainClean = str_replace(['.', '-'], '_', $domain->domain_name);
        $domainClean = preg_replace('/[^a-z0-9_]/', '', strtolower($domainClean));
        
        // Clean database name
        $nameClean = preg_replace('/[^a-z0-9_]/', '', strtolower($name));
        
        // Combine and limit to 64 chars
        $fullName = "{$domainClean}_{$nameClean}";
        return substr($fullName, 0, 64);
    }

    /**
     * Generate unique MySQL username
     */
    protected function generateUserName(Domain $domain): string
    {
        $domainClean = str_replace(['.', '-'], '_', $domain->domain_name);
        $domainClean = preg_replace('/[^a-z0-9_]/', '', strtolower($domainClean));
        $random = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 5);
        
        return substr("{$domainClean}_{$random}", 0, 32);
    }

    /**
     * Generate secure random password
     */
    protected function generateSecurePassword(int $length = 32): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
    }

    /**
     * Generate phpMyAdmin SSO token
     */
    public function generatePhpMyAdminToken(Database $database): string
    {
        $token = Str::random(64);
        
        // Store in Redis with 5 minute expiration
        Redis::setex(
            "phpmyadmin_token:{$token}",
            300, // 5 minutes
            json_encode([
                'database_id' => $database->id,
                'database_name' => $database->database_name,
                'mysql_user' => $database->mysql_user,
                'mysql_password' => $database->getDecryptedPasswordAttribute(),
                'mysql_host' => $database->mysql_host,
                'created_at' => now()->toIso8601String(),
            ])
        );

        return $token;
    }

    /**
     * Update database size
     */
    public function updateSize(Database $database): void
    {
        $size = $database->calculateSize();
        $database->update(['size_mb' => $size]);
    }
}
```

**File:** `app/Services/MySQLRootConnection.php`

```php
class MySQLRootConnection
{
    protected PDO $pdo;

    public function __construct()
    {
        // Connect as root
        $this->pdo = new PDO(
            'mysql:host=' . config('npanel.mysql_root_host'),
            config('npanel.mysql_root_user'),
            config('npanel.mysql_root_password')
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function createDatabase(Database $database): void
    {
        $stmt = $this->pdo->prepare(
            "CREATE DATABASE `{$database->database_name}` 
             CHARACTER SET {$database->charset} 
             COLLATE {$database->collation}"
        );
        $stmt->execute();
    }

    public function createUser(Database $database, string $password): void
    {
        $stmt = $this->pdo->prepare(
            "CREATE USER '{$database->mysql_user}'@'{$database->mysql_host}' 
             IDENTIFIED BY :password"
        );
        $stmt->execute(['password' => $password]);
    }

    public function grantPermissions(Database $database): void
    {
        $stmt = $this->pdo->prepare(
            "GRANT ALL PRIVILEGES ON `{$database->database_name}`.* 
             TO '{$database->mysql_user}'@'{$database->mysql_host}'"
        );
        $stmt->execute();
        
        $this->pdo->exec('FLUSH PRIVILEGES');
    }

    public function dropDatabase(string $databaseName): void
    {
        $stmt = $this->pdo->prepare("DROP DATABASE IF EXISTS `{$databaseName}`");
        $stmt->execute();
    }

    public function dropUser(string $user, string $host): void
    {
        $stmt = $this->pdo->prepare("DROP USER IF EXISTS '{$user}'@'{$host}'");
        $stmt->execute();
        
        $this->pdo->exec('FLUSH PRIVILEGES');
    }
}
```

### 4. Controller

**File:** `app/Http/Controllers/DatabaseController.php`

```php
class DatabaseController extends Controller
{
    public function __construct(
        protected DatabaseService $databaseService
    ) {}

    /**
     * List all databases for a domain
     */
    public function index(Domain $domain)
    {
        $databases = $domain->databases()
            ->latest()
            ->get()
            ->map(function ($db) {
                // Update size
                $this->databaseService->updateSize($db);
                return $db;
            });

        return inertia('Domains/Databases/Index', [
            'domain' => $domain,
            'databases' => $databases,
        ]);
    }

    /**
     * Store new database
     */
    public function store(Request $request, Domain $domain)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'charset' => ['nullable', 'string', 'in:utf8mb4,utf8,latin1'],
            'collation' => ['nullable', 'string'],
        ]);

        try {
            $database = $this->databaseService->createDatabase($domain, $validated);

            return Redirect::route('domains.databases.index', $domain)
                ->with('success', 'Database created successfully')
                ->with('database_password', $database->getDecryptedPasswordAttribute());
        } catch (\Exception $e) {
            Log::error('Database creation failed', [
                'domain' => $domain->domain_name,
                'error' => $e->getMessage()
            ]);
            return Redirect::back()->with('error', 'Failed to create database');
        }
    }

    /**
     * Show database details
     */
    public function show(Domain $domain, Database $database)
    {
        // Update size
        $this->databaseService->updateSize($database);

        return response()->json([
            'database' => $database,
            'password' => $database->getDecryptedPasswordAttribute(),
            'connection_strings' => [
                'php' => $database->getConnectionString('php'),
                'laravel' => $database->getConnectionString('laravel'),
                'node' => $database->getConnectionString('node'),
            ],
        ]);
    }

    /**
     * Delete database
     */
    public function destroy(Domain $domain, Database $database)
    {
        try {
            $this->databaseService->deleteDatabase($database);

            return Redirect::route('domains.databases.index', $domain)
                ->with('success', 'Database deleted successfully');
        } catch (\Exception $e) {
            Log::error('Database deletion failed', [
                'database' => $database->database_name,
                'error' => $e->getMessage()
            ]);
            return Redirect::back()->with('error', 'Failed to delete database');
        }
    }

    /**
     * Generate phpMyAdmin SSO token and redirect
     */
    public function phpmyadmin(Domain $domain, Database $database)
    {
        $token = $this->databaseService->generatePhpMyAdminToken($database);

        return redirect(config('npanel.phpmyadmin_url') . '?npanel_token=' . $token);
    }
}
```

### 5. Routes

**File:** `routes/web.php`

```php
// Database management
Route::prefix('domains/{domain}/databases')->middleware(['auth'])->group(function () {
    Route::get('/', [DatabaseController::class, 'index'])->name('domains.databases.index');
    Route::post('/', [DatabaseController::class, 'store'])->name('domains.databases.store');
    Route::get('/{database}', [DatabaseController::class, 'show'])->name('domains.databases.show');
    Route::delete('/{database}', [DatabaseController::class, 'destroy'])->name('domains.databases.destroy');
    Route::get('/{database}/phpmyadmin', [DatabaseController::class, 'phpmyadmin'])->name('domains.databases.phpmyadmin');
});
```

### 6. Configuration

**File:** `config/npanel.php`

```php
return [
    // ... existing config ...

    /*
    |--------------------------------------------------------------------------
    | MySQL Root Credentials
    |--------------------------------------------------------------------------
    | Root credentials for creating/managing client databases
    */
    'mysql_root_host' => env('NPANEL_MYSQL_ROOT_HOST', 'localhost'),
    'mysql_root_user' => env('NPANEL_MYSQL_ROOT_USER', 'root'),
    'mysql_root_password' => env('NPANEL_MYSQL_ROOT_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | phpMyAdmin Configuration
    |--------------------------------------------------------------------------
    */
    'phpmyadmin_url' => env('NPANEL_PHPMYADMIN_URL', 'https://panel.domain/phpmyadmin'),
    'phpmyadmin_path' => env('NPANEL_PHPMYADMIN_PATH', '/var/www/phpmyadmin'),
    'phpmyadmin_sso_enabled' => env('NPANEL_PHPMYADMIN_SSO', true),

    /*
    |--------------------------------------------------------------------------
    | Database Defaults
    |--------------------------------------------------------------------------
    */
    'database_defaults' => [
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'max_databases_per_domain' => 10,
    ],
];
```

**File:** `.env.example`

```env
# MySQL Root Access (for client database management)
NPANEL_MYSQL_ROOT_HOST=localhost
NPANEL_MYSQL_ROOT_USER=root
NPANEL_MYSQL_ROOT_PASSWORD=your_root_password

# phpMyAdmin
NPANEL_PHPMYADMIN_URL=https://panel.yourdomain.com/phpmyadmin
NPANEL_PHPMYADMIN_PATH=/var/www/phpmyadmin
NPANEL_PHPMYADMIN_SSO=true
```

## 🎨 Frontend Implementation

### 1. Add Database Button to Domain Show Page

**File:** `resources/js/Pages/Domains/Show.vue`

```vue
<!-- Add after Files button, before Issue SSL button -->
<Link 
    :href="`/domains/${domain.id}/databases`" 
    class="px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 flex items-center"
>
    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
        <path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3z"/>
        <path d="M3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7z"/>
        <path d="M17 5c0 1.657-3.134 3-7 3S3 6.657 3 5s3.134-3 7-3 7 1.343 7 3z"/>
    </svg>
    Databases
</Link>
```

### 2. Database Index Page

**File:** `resources/js/Pages/Domains/Databases/Index.vue`

```vue
<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    domain: Object,
    databases: Array,
});

const showCreateModal = ref(false);
const showInfoModal = ref(false);
const selectedDatabase = ref(null);

const form = useForm({
    name: '',
    charset: 'utf8mb4',
    collation: 'utf8mb4_unicode_ci',
});

const createDatabase = () => {
    form.post(`/domains/${props.domain.id}/databases`, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const deleteDatabase = (database) => {
    if (confirm(`Delete database ${database.database_name}? This cannot be undone!`)) {
        router.delete(`/domains/${props.domain.id}/databases/${database.id}`);
    }
};

const openPhpMyAdmin = (database) => {
    window.open(`/domains/${props.domain.id}/databases/${database.id}/phpmyadmin`, '_blank');
};

const showInfo = async (database) => {
    // Fetch full details including password
    const response = await axios.get(`/domains/${props.domain.id}/databases/${database.id}`);
    selectedDatabase.value = response.data;
    showInfoModal.value = true;
};
</script>

<template>
    <AppLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900">
                                Databases for {{ domain.domain_name }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Manage MySQL databases for this domain
                            </p>
                        </div>
                        <button
                            @click="showCreateModal = true"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                        >
                            + New Database
                        </button>
                    </div>
                </div>

                <!-- Database List -->
                <div class="space-y-4">
                    <div
                        v-for="database in databases"
                        :key="database.id"
                        class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3z"/>
                                        <path d="M3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7z"/>
                                        <path d="M17 5c0 1.657-3.134 3-7 3S3 6.657 3 5s3.134-3 7-3 7 1.343 7 3z"/>
                                    </svg>
                                    {{ database.database_name }}
                                </h3>
                                <div class="mt-2 space-y-1 text-sm text-gray-600">
                                    <p>Size: {{ database.size_mb }} MB</p>
                                    <p>Created: {{ new Date(database.created_at).toLocaleString() }}</p>
                                </div>
                            </div>

                            <div class="flex space-x-2">
                                <button
                                    @click="showInfo(database)"
                                    class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm"
                                    title="Database Info"
                                >
                                    ℹ️ Info
                                </button>
                                <button
                                    @click="openPhpMyAdmin(database)"
                                    class="px-3 py-1.5 bg-green-500 text-white rounded-md hover:bg-green-600 text-sm"
                                    title="Open phpMyAdmin"
                                >
                                    🌐 phpMyAdmin
                                </button>
                                <button
                                    @click="deleteDatabase(database)"
                                    class="px-3 py-1.5 bg-red-500 text-white rounded-md hover:bg-red-600 text-sm"
                                    title="Delete Database"
                                >
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="databases.length === 0" class="bg-white shadow-xl sm:rounded-lg p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No databases</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by creating a new database.</p>
                        <div class="mt-6">
                            <button
                                @click="showCreateModal = true"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                            >
                                + New Database
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Database Modal -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Modal implementation -->
            </div>
        </Teleport>

        <!-- Info Modal -->
        <Teleport to="body">
            <div v-if="showInfoModal" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Info modal implementation with password, connection strings, etc. -->
            </div>
        </Teleport>
    </AppLayout>
</template>
```

## 🔧 phpMyAdmin Integration

### Installation

**Option 1: Adminer (Empfohlen - einfacher)**

```bash
# Download Adminer
cd /var/www
sudo mkdir adminer
cd adminer
sudo wget https://www.adminer.org/latest.php -O index.php

# Create SSO auth script
sudo nano sso.php
```

```php
<?php
// /var/www/adminer/sso.php
if (isset($_GET['npanel_token'])) {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    $data = $redis->get("phpmyadmin_token:{$_GET['npanel_token']}");
    if ($data) {
        $auth = json_decode($data, true);
        
        $_SESSION['adminer_login'] = [
            'server' => $auth['mysql_host'],
            'username' => $auth['mysql_user'],
            'password' => $auth['mysql_password'],
            'database' => $auth['database_name'],
        ];
        
        $redis->del("phpmyadmin_token:{$_GET['npanel_token']}");
        
        header('Location: index.php');
        exit;
    }
}
http_response_code(403);
echo 'Invalid or expired token';
```

**Nginx Config:**

```nginx
# /etc/nginx/sites-available/adminer.conf
server {
    listen 80;
    server_name adminer.panel.domain;
    
    root /var/www/adminer;
    index index.php;
    
    # Allow only from localhost (proxy through panel)
    allow 127.0.0.1;
    deny all;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

**Option 2: phpMyAdmin (Volle Features)**

```bash
# Install phpMyAdmin
sudo apt install phpmyadmin

# Configure SSO
sudo nano /etc/phpmyadmin/config.inc.php
```

```php
<?php
// Signon authentication
$cfg['Servers'][$i]['auth_type'] = 'signon';
$cfg['Servers'][$i]['SignonSession'] = 'npanel_phpmyadmin';
$cfg['Servers'][$i]['SignonURL'] = 'sso.php';

// Create sso.php in /usr/share/phpmyadmin/
```

## 📊 Testing Strategy

### Unit Tests

```php
// tests/Unit/DatabaseServiceTest.php
class DatabaseServiceTest extends TestCase
{
    public function test_database_name_generation()
    {
        $domain = Domain::factory()->create(['domain_name' => 'example.com']);
        $service = new DatabaseService();
        
        $name = $service->generateDatabaseName($domain, 'wordpress');
        
        $this->assertEquals('example_com_wordpress', $name);
    }

    public function test_create_database()
    {
        $domain = Domain::factory()->create();
        $service = new DatabaseService();
        
        $database = $service->createDatabase($domain, ['name' => 'test']);
        
        $this->assertDatabaseHas('databases', [
            'domain_id' => $domain->id,
            'database_name' => 'example_com_test',
        ]);
    }
}
```

### Feature Tests

```php
// tests/Feature/DatabaseManagementTest.php
class DatabaseManagementTest extends TestCase
{
    public function test_user_can_create_database()
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['user_id' => $user->id]);
        
        $response = $this->actingAs($user)->post("/domains/{$domain->id}/databases", [
            'name' => 'wordpress',
            'charset' => 'utf8mb4',
        ]);
        
        $response->assertRedirect();
        $this->assertDatabaseHas('databases', [
            'domain_id' => $domain->id,
        ]);
    }

    public function test_database_is_isolated_per_domain()
    {
        $domain1 = Domain::factory()->create();
        $domain2 = Domain::factory()->create();
        $db1 = Database::factory()->create(['domain_id' => $domain1->id]);
        
        $this->actingAs($domain2->user)
            ->get("/domains/{$domain2->id}/databases")
            ->assertDontSee($db1->database_name);
    }
}
```

## 🚀 Deployment Checklist

### Phase 1: Backend Setup
- [ ] Run migration: `php artisan migrate`
- [ ] Add MySQL root credentials to `.env`
- [ ] Test MySQL root connection
- [ ] Create `DatabaseService` and `MySQLRootConnection`
- [ ] Create `Database` model with relationships
- [ ] Create `DatabaseController`
- [ ] Add routes to `web.php`

### Phase 2: Frontend
- [ ] Add "Databases" button to `Domains/Show.vue`
- [ ] Create `Domains/Databases/Index.vue`
- [ ] Create modals (Create, Info, Delete confirmation)
- [ ] Test UI flow: Create → View Info → Delete

### Phase 3: phpMyAdmin/Adminer
- [ ] Install Adminer or phpMyAdmin
- [ ] Configure SSO authentication
- [ ] Create Nginx proxy configuration
- [ ] Test SSO token generation and login
- [ ] Verify database isolation (user can only access own DB)

### Phase 4: Security Audit
- [ ] Verify MySQL user isolation (no cross-database access)
- [ ] Test password encryption/decryption
- [ ] Verify Redis token expiration
- [ ] Test phpMyAdmin access restrictions
- [ ] Audit SQL injection vectors

### Phase 5: Documentation
- [ ] Update README.md with database features
- [ ] Create user guide for database management
- [ ] Document phpMyAdmin SSO setup
- [ ] Add troubleshooting section

## 🔒 Security Considerations

1. **MySQL User Permissions**
   - ✅ User hat KEINE globalen Rechte
   - ✅ User kann KEINE anderen Datenbanken sehen
   - ✅ User kann KEINE neuen User anlegen
   - ✅ User gebunden an 127.0.0.1 (kein remote access)

2. **Password Storage**
   - ✅ Verschlüsselt in nPanel-DB gespeichert
   - ✅ Nur bei Erstellung im Klartext gezeigt
   - ✅ Nicht in Logs ausgegeben

3. **phpMyAdmin Access**
   - ✅ Token-basierter SSO (5 Min TTL)
   - ✅ Token nach Login ungültig
   - ✅ Kein direkter phpMyAdmin-Zugang
   - ✅ Proxy durch nPanel mit Auth

4. **SQL Injection Prevention**
   - ✅ PDO Prepared Statements für alle Queries
   - ✅ Database/User Namen validated (alphanumeric + underscore only)
   - ✅ Laravel Query Builder für nPanel-DB

5. **Rate Limiting**
   - ⚠️ TODO: Limit database creation (max 10 per domain)
   - ⚠️ TODO: Limit phpMyAdmin access (max 10 requests/min)

## 📈 Future Enhancements

### Phase 2 Features
- [ ] **Database Backups**
  - Automatic daily backups
  - Manual backup/restore
  - Export to .sql file

- [ ] **Database Import**
  - Upload .sql file
  - Import from URL
  - Progress tracking for large imports

- [ ] **Database Users**
  - Multiple users per database
  - Read-only users
  - Custom permissions

- [ ] **Monitoring**
  - Database size tracking
  - Query statistics
  - Slow query log

- [ ] **PostgreSQL Support**
  - Parallel implementation für PostgreSQL
  - Same UI/UX
  - Toggle zwischen MySQL/PostgreSQL

### Advanced Features
- [ ] **Database Cloning** - Duplicate database with data
- [ ] **Point-in-Time Recovery** - Restore to specific timestamp
- [ ] **Replication Setup** - Master-slave for scaling
- [ ] **Query Builder UI** - Visual query builder
- [ ] **Database Migrations** - Version control for schemas

## 📝 Implementation Timeline

**Sprint 1 (Week 1):** Backend Foundation
- Day 1-2: Database schema, migrations, models
- Day 3-4: DatabaseService, MySQLRootConnection
- Day 5: Controller, routes, basic testing

**Sprint 2 (Week 2):** Frontend & Integration
- Day 1-2: Database Index Vue component
- Day 3: Create/Info/Delete modals
- Day 4-5: phpMyAdmin SSO integration

**Sprint 3 (Week 3):** Polish & Security
- Day 1-2: Security audit, permission tests
- Day 3: Error handling, user feedback
- Day 4-5: Documentation, deployment guide

**Total Estimate:** 3 weeks (15 working days)

---

## ✅ Success Criteria

Feature ist erfolgreich implementiert wenn:

1. ✅ User kann Datenbank über UI anlegen
2. ✅ Datenbank ist nur von eigener Domain erreichbar
3. ✅ MySQL User hat isolierte Rechte (keine Cross-DB Access)
4. ✅ phpMyAdmin SSO funktioniert passwordless
5. ✅ Connection Strings werden korrekt angezeigt
6. ✅ Datenbank kann gelöscht werden (inkl. User)
7. ✅ Datenbankgröße wird korrekt berechnet
8. ✅ Keine SQL Injection Vulnerabilities
9. ✅ Tests bestehen (Unit + Feature)
10. ✅ Dokumentation ist vollständig

---

**Erstellt:** 2025-11-20  
**Version:** 1.0  
**Status:** Planning - Ready for Implementation

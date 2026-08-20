# Number Info API & Dynamic Key Manager

A high-performance PHP API service for phone number intelligence, equipped with a comprehensive multi-key management system, request quotas, validity/expiration tracking, and a modern Admin Control Panel.

**Developer / Credit**: Satyam Gupta

---

## 🚀 Features

- **Multi-Key System**: Issue unlimited dynamic API keys for clients, bots, and users.
- **Request Quota / Limits**: Configure limits (e.g., 100, 500, 1000 requests, or unlimited `-1`).
- **Validity & Expiration**: Set key expiration periods (e.g., 7 days, 30 days, 1 year, or lifetime).
- **Admin Control Panel (`admin.php`)**: Modern, responsive dark-mode dashboard to create, monitor, suspend, reset, or delete keys.
- **Admin REST API**: Automate key creation and management from external scripts/bots.
- **Zero-Setup Database**: Uses lightweight, atomic-locked JSON storage (`data/keys.json`) — works out of the box with zero MySQL/database setup required.
- **Backward Compatible**: Comes pre-configured with the default legacy key `satyamm`.

---

## 📁 File Structure

```
├── config.php         # Master settings (Admin secret, data paths, developer info)
├── KeyManager.php     # Core engine for key validation, quotas, atomic locking & CRUD
├── admin.php          # Interactive Web Admin Dashboard & REST API
├── index.php          # Main Public API Endpoint (Number Lookup)
├── data/
│   ├── keys.json      # Stored keys database (auto-generated on first run)
│   └── .htaccess      # Security rule preventing direct web access to keys
└── README.md          # Documentation & usage guide
```

---

## ⚙️ Configuration (`config.php`)

Open `config.php` to customize your settings:

```php
// Master Admin Secret Key for accessing admin.php
define('ADMIN_SECRET_KEY', 'satyam_admin_2026');

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Branding
define('API_DEVELOPER', 'Satyam Gupta');
define('API_CREDIT', 'Satyam Gupta');
```

> [!IMPORTANT]
> **Change `ADMIN_SECRET_KEY`** to your own private password before deploying to production.

---

## 🖥️ Using the Admin Panel

1. Open your browser and navigate to `http://your-domain.com/admin.php`.
2. Enter your **Admin Secret Key** (`satyam_admin_2026` by default).
3. From the dashboard you can:
   - **Create New Key**: Set Owner name, Request Limit, Validity period (e.g., 30 days), and optional custom key name.
   - **Monitor Usage**: View real-time request counts, progress bars, and expiration dates.
   - **Actions**:
     - 📋 Copy 1-Click Test API URL
     - 🔄 Reset Usage Counter
     - ⏸️ Suspend / ▶️ Activate Key
     - 🗑️ Delete Key

---

## 🔌 API Usage (`index.php`)

### Request

```http
GET /index.php?apikey=YOUR_API_KEY&number=9570187989
```

You can pass the API key via:
- **URL Parameter**: `?apikey=YOUR_KEY`
- **POST Body**: `apikey=YOUR_KEY`
- **HTTP Header**: `X-API-Key: YOUR_KEY`
- **Authorization Header**: `Authorization: Bearer YOUR_KEY`

### Success Response (`200 OK`)

```json
{
  "success": true,
  "developer": "Satyam Gupta",
  "credit": "Satyam Gupta",
  "key_info": {
    "owner": "Client A",
    "requests_used": 15,
    "request_limit": 500,
    "requests_remaining": 485,
    "expires_at": "2026-09-19 23:59:59"
  },
  "result": [
    "NAME: John Doe",
    "MOBILE: 9570187989",
    "OPERATOR: Airtel"
  ]
}
```

### Error Responses

#### Limit Exceeded (`401 Unauthorized`)
```json
{
  "success": false,
  "error_code": "LIMIT_EXCEEDED",
  "message": "API request limit reached (500/500). Please contact admin for more quota.",
  "developer": "Satyam Gupta"
}
```

#### Key Expired (`401 Unauthorized`)
```json
{
  "success": false,
  "error_code": "KEY_EXPIRED",
  "message": "This API key expired on 2026-08-01 00:00:00. Please renew your plan.",
  "developer": "Satyam Gupta"
}
```

---

## 🤖 Admin REST API (For Automation / Bots)

You can programmatically manage keys using the Admin REST API by passing `admin_key`:

### 1. Create a Key
```http
POST /admin.php?action=create&admin_key=YOUR_ADMIN_SECRET
Content-Type: application/x-www-form-urlencoded

owner=TelegramBot&limit=1000&validity_days=30
```

### 2. List All Keys
```http
GET /admin.php?action=list&admin_key=YOUR_ADMIN_SECRET
```

### 3. Reset Key Usage
```http
GET /admin.php?action=reset_usage&admin_key=YOUR_ADMIN_SECRET&key=satyam_xxxxxx
```

### 4. Suspend / Activate Key
```http
GET /admin.php?action=toggle_status&admin_key=YOUR_ADMIN_SECRET&key=satyam_xxxxxx
```

### 5. Delete Key
```http
GET /admin.php?action=delete&admin_key=YOUR_ADMIN_SECRET&key=satyam_xxxxxx
```

# Arellano University Digital Clearance & Document Request Management System
## Version 1.0.0 | Production-Ready PHP/MySQL System

---

## 📁 Folder Structure

```
au-clearance/
├── config/
│   └── db.php                    # Database configuration & PDO connection
├── includes/
│   ├── auth.php                  # Authentication, RBAC, session, logging helpers
│   ├── header.php                # Shared sidebar + topbar layout
│   └── footer.php                # Shared footer
├── student/
│   ├── dashboard.php             # Student dashboard
│   ├── clearance.php             # Clearance status & progress
│   └── document_requests.php    # Document requests & payment submission
├── department/
│   └── dashboard.php             # Department clearance management
├── registrar/
│   └── dashboard.php             # Document requests & payment verification
├── admin/
│   ├── dashboard.php             # System overview & statistics
│   ├── users.php                 # User management (CRUD)
│   └── logs.php                  # Activity log viewer
├── ajax/
│   ├── auth.php                  # Login / register / logout
│   ├── student.php               # Student AJAX actions
│   ├── department.php            # Department AJAX actions
│   ├── registrar.php             # Registrar AJAX actions
│   └── admin.php                 # Admin AJAX actions
├── database/
│   └── au_clearance.sql          # Full MySQL schema with seed data
├── assets/
│   └── au-logo.png               # University logo (place here)
├── index.php                     # Login / Registration landing page
└── setup.php                     # One-time installation script
```

---

## ⚙️ Requirements

| Requirement | Minimum |
|---|---|
| PHP | 8.0+ |
| MySQL | 5.7+ / MariaDB 10.4+ |
| Web Server | Apache 2.4+ / Nginx |
| Extensions | PDO, PDO_MySQL, mbstring, openssl |

---

## 🚀 Installation Steps

### 1. Place files on server
Copy the `au-clearance/` folder to your web server root:
- **XAMPP**: `C:/xampp/htdocs/au-clearance/`
- **LAMP**: `/var/www/html/au-clearance/`

### 2. Configure database
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'au_clearance_db');
define('APP_URL', 'http://yourdomain.com/au-clearance');
```

### 3. Add university logo
Place `au-logo.png` in the `assets/` folder.

### 4. Run setup
Navigate to: `http://localhost/au-clearance/setup.php`

This will:
- Create the database and all tables
- Insert default department records
- Create demo accounts

### 5. Delete setup.php
**Critical security step** — delete `setup.php` and `setup.lock` after successful setup.

---

## 🔐 Default Credentials (Change immediately!)

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `Admin@AU2024` |
| Registrar | `registrar` | `Registrar@AU2024` |
| Library Dept | `lib` | `Dept@AU2024` |
| Finance Dept | `fin` | `Dept@AU2024` |
| Registrar Dept | `reg` | `Dept@AU2024` |

---

## 🗄️ Database Tables

| Table | Purpose |
|---|---|
| `users` | All user accounts with role-based access |
| `students` | Student profile information |
| `departments` | University department records |
| `clearances` | Clearance applications per student |
| `clearance_status` | Per-department clearance status |
| `document_requests` | Official document requests |
| `payments` | Payment records linked to requests |
| `logs` | Full system activity audit trail |

---

## 🔑 Role Permissions

### Student
- Register and login
- Apply for regular/graduation clearance
- View per-department clearance status in real-time
- Request official documents (TOR, Diploma, etc.)
- Submit payment records
- Track document request status

### Department Admin
- View all clearance requests assigned to their department
- Update clearance status: Pending → Cleared / Deficiency
- Add remarks per student
- System auto-completes clearance when all departments clear

### Registrar Personnel
- View all document requests from all students
- Verify or reject student payment submissions
- Approve, reject, or update document request status
- Mark documents as ready for pickup / released

### System Administrator
- Full system access
- Create/edit/deactivate user accounts
- Assign departments to department accounts
- View all activity logs with pagination
- Monitor system statistics

---

## 🔒 Security Features

- **Password Hashing**: bcrypt (cost=12) via `password_hash()`
- **SQL Injection Prevention**: PDO prepared statements throughout
- **CSRF Protection**: Token per session on all POST forms
- **Session Security**: HTTPOnly cookies, SameSite=Strict, session regeneration on login
- **Input Sanitization**: `sanitize()` helper with `strip_tags` + `htmlspecialchars`
- **Role-Based Access**: `requireRole()` enforced on every protected page
- **Activity Logging**: Every significant action logged with user_id, IP, timestamp
- **Session Timeout**: Configurable expiry (default 1 hour)

---

## 📧 Support

For system issues, contact the IT Department at Arellano University.  
**System developed for:** Arellano University, Manila, Philippines

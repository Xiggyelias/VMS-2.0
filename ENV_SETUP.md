# Environment Setup Guide

This guide will help you set up the environment configuration for the Vehicle Registration System.

## Prerequisites

- PHP 7.4 or higher
- Composer installed
- MySQL/MariaDB database
- XAMPP or similar web server environment

## Installation Steps

### 1. Install Dependencies

Run the following command in the `frontend` directory:

```bash
composer install
```

This will install:
- PHPMailer for email functionality
- vlucas/phpdotenv for environment variable management

### 2. Create Environment File

Copy the `.env.example` file to create your `.env` file:

```bash
# On Windows (PowerShell)
Copy-Item .env.example .env

# On Linux/Mac
cp .env.example .env
```

### 3. Configure Environment Variables

Edit the `.env` file with your actual configuration values:

#### **Database Configuration**
```env
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=your_database_password
DB_NAME=vehicleregistrationsystem
```

#### **Email Configuration**
For Gmail, you need to create an App Password:
1. Go to Google Account Settings
2. Enable 2-Factor Authentication
3. Generate an App Password for "Mail"
4. Use that password in your `.env` file

```env
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-specific-password
SMTP_FROM_EMAIL=noreply@au.ac.zw
```

#### **Google OAuth Configuration**
If you need to update Google OAuth credentials:
```env
GOOGLE_CLIENT_ID=your-google-client-id
ALLOWED_GOOGLE_DOMAIN=africau.edu
```

#### **Application Configuration**
```env
APP_ENV=development
BASE_URL=http://localhost/system/frontend
DISPLAY_ERRORS=true
```

**Important for Production:**
- Set `APP_ENV=production`
- Set `DISPLAY_ERRORS=false`
- Set `SESSION_SECURE=true` (if using HTTPS)
- Use strong passwords and change default values

### 4. Database Setup

Ensure your database is created:

```sql
CREATE DATABASE vehicleregistrationsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. File Permissions

Ensure the following directories are writable:

```bash
# On Windows
# Right-click folder > Properties > Security > Edit permissions

# On Linux/Mac
chmod -R 755 logs/
chmod -R 755 uploads/
chmod -R 755 backups/
```

### 6. Verify Setup

1. Navigate to your application URL
2. Check that the application loads without errors
3. Verify database connection works
4. Test email functionality (if configured)

## Environment Variables Reference

### Application Settings
- `APP_NAME` - Application name
- `APP_VERSION` - Application version
- `APP_ENV` - Environment (development/production)
- `BASE_URL` - Base URL of the application

### Database Settings
- `DB_HOST` - Database host
- `DB_USERNAME` - Database username
- `DB_PASSWORD` - Database password
- `DB_NAME` - Database name
- `DB_CHARSET` - Character set (default: utf8mb4)

### Session Settings
- `SESSION_NAME` - Session cookie name
- `SESSION_LIFETIME` - Session lifetime in seconds
- `SESSION_SECURE` - Use secure cookies (true/false)
- `SESSION_HTTP_ONLY` - HTTP only cookies (true/false)

### Security Settings
- `PASSWORD_MIN_LENGTH` - Minimum password length
- `LOGIN_MAX_ATTEMPTS` - Maximum login attempts
- `LOGIN_LOCKOUT_TIME` - Lockout time in seconds

### Email Settings (SMTP)
- `SMTP_HOST` - SMTP server host
- `SMTP_PORT` - SMTP server port
- `SMTP_USERNAME` - SMTP username
- `SMTP_PASSWORD` - SMTP password
- `SMTP_FROM_EMAIL` - From email address
- `SMTP_FROM_NAME` - From name

### Google OAuth Settings
- `GOOGLE_CLIENT_ID` - Google OAuth client ID
- `ALLOWED_GOOGLE_DOMAIN` - Allowed email domain

### Vehicle Registration Settings
- `MAX_VEHICLES_PER_STUDENT` - Max vehicles for students
- `MAX_VEHICLES_PER_STAFF` - Max vehicles for staff
- `MAX_VEHICLES_PER_GUEST` - Max vehicles for guests
- `VEHICLE_REGISTRATION_EXPIRY_DAYS` - Registration expiry in days

### File Upload Settings
- `MAX_FILE_SIZE` - Maximum file size in bytes
- `ALLOWED_IMAGE_TYPES` - Comma-separated list of allowed file extensions

### Pagination Settings
- `ITEMS_PER_PAGE` - Items per page
- `MAX_PAGES_DISPLAY` - Maximum page links to display

### Error Handling
- `DISPLAY_ERRORS` - Display errors (true/false)
- `LOG_ERRORS` - Log errors (true/false)

## Security Best Practices

1. **Never commit `.env` file** - It's already in `.gitignore`
2. **Use strong passwords** - Especially for production environments
3. **Change default values** - Update all default credentials
4. **Enable HTTPS** - Set `SESSION_SECURE=true` in production
5. **Limit file permissions** - Ensure proper file/folder permissions
6. **Regular backups** - Backup your `.env` file securely
7. **Environment-specific configs** - Use different values for dev/prod

## Troubleshooting

### Error: "Composer dependencies not installed"
**Solution:** Run `composer install` in the frontend directory

### Error: ".env file not found"
**Solution:** Copy `.env.example` to `.env` and configure it

### Error: "Database connection failed"
**Solution:** Verify your database credentials in `.env` file

### Error: "Email sending failed"
**Solution:** Verify SMTP settings and ensure App Password is correct

### Error: "Permission denied" for logs/uploads
**Solution:** Ensure directories have write permissions

## Development vs Production

### Development Settings
```env
APP_ENV=development
DISPLAY_ERRORS=true
SESSION_SECURE=false
```

### Production Settings
```env
APP_ENV=production
DISPLAY_ERRORS=false
SESSION_SECURE=true
```

## Getting Help

If you encounter issues:
1. Check error logs in `logs/error.log`
2. Verify all environment variables are set correctly
3. Ensure all dependencies are installed
4. Check file/folder permissions
5. Review the security log in `logs/security.log`

## Additional Notes

- The `.env` file is automatically loaded by `config/env.php`
- Helper functions are available: `env()`, `env_bool()`, `env_int()`, `env_array()`
- Configuration files are in the `config/` directory
- All sensitive data should be stored in `.env`, not in code

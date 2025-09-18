# BCS Member Import Bundle - Installation & Setup Guide

## Prerequisites
- Contao 5.3 or higher
- PHP 8.1 or higher
- Composer installed

## Installation Steps

### 1. Install via Composer
```bash
composer require bright-cloud-studio/contao-osi-member-import
```

### 2. Clear Contao Cache
```bash
php bin/console cache:clear
```
Or via Contao Manager: System → System Maintenance → Purge Data → Internal Cache

### 3. Create Password Reset Frontend Page
1. Go to **Site Structure** in your Contao backend
2. Create a new page with these settings:
   - **Page name:** Reset Password
   - **Page alias:** `reset-password`
   - **Page type:** Regular page
   - **Published:** Yes
3. Edit the page and add a **Content Element**:
   - **Element type:** Module
   - **Module:** Lost password (mod_lostPassword)

### 4. Configure Email Settings (Optional)
The module will use Contao's default email settings. To customize:
1. Go to **System → Settings**
2. Configure SMTP settings under **Email settings**

## Usage Instructions

### 1. Access the Module
- Go to **System → Member CSV Import** in your Contao backend

### 2. Prepare Your CSV File
Use the provided example CSV file as a template. Required columns:
- `firstname` - Member's first name
- `lastname` - Member's last name  
- `email` - Valid email address (must be unique)
- `username` - Username (must be unique)
- `member_group` - Group name (will be created if doesn't exist)

Optional columns:
- `password` - If empty, random password will be generated
- `company`, `street`, `postal`, `city`, `state`, `country`
- `phone`, `mobile`, `fax`, `website`
- `language`, `gender`
- `dateOfBirth` - Format: YYYY-MM-DD

### 3. Import Process
1. Select your CSV file
2. Click **Import Members**
3. Review the import report
4. If there are failures, fix the CSV and re-import failed rows

### 4. Send Password Reset Emails
1. After successful import, select members to email
2. Customize email subject and body if needed
3. Use placeholders: `{{firstname}}`, `{{lastname}}`, `{{email}}`, `{{username}}`, `{{reset_link}}`
4. Click **Send Password Reset Emails**

## Troubleshooting

### Common Issues

**Module not appearing in backend:**
- Clear cache: `php bin/console cache:clear`
- Check that composer install completed successfully

**Import failures:**
- Verify CSV format matches requirements
- Check for duplicate emails/usernames
- Ensure all required columns are present

**Password reset emails not sending:**
- Verify SMTP settings in System → Settings
- Check that `/reset-password.html` page exists and is published
- Ensure Lost Password module is configured on the page

**Permission errors:**
- Make sure your admin user has access to System modules
- Check file upload permissions

### File Structure
After installation, your bundle should be located at:
```
vendor/bright-cloud-studio/contao-osi-member-import/
├── composer.json
├── src/
│   ├── BcsMemberImportBundle.php
│   ├── ContaoManager/
│   │   └── Plugin.php
│   ├── MemberImport.php
│   └── Resources/
│       └── contao/
│           ├── config/
│           │   └── config.php
│           ├── languages/
│           │   └── en/
│           │       └── default.php
│           └── templates/
│               └── be_member_import.html5
```

## Support
For issues or questions, please contact [john@brightcloudstudio.com](mailto:john@brightcloudstudio.com)
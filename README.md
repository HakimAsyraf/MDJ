# MDJ Tracking System (XAMPP + PHP)

This project is a responsive “government-style” File Tracking System with:
- Register / Login / Logout
- Theme settings (light/dark) stored in DB
- Search & filter by: Tahun, No Kotak Fail, No Fail Permohonan, Tarikh Masuk, Lot/PT, Mukim, Aras, Kabinet
- Upload documents (Word/PDF) linked to a file record
- Open documents directly from the system (secure download controller)
- Admin: delete files, delete documents, manage users (role/jabatan), import CSV (initial data load)

---

## 1) XAMPP Setup

1. Install **XAMPP** and start:
   - **Apache**
   - **MySQL**

2. Put this folder into:
   - `C:\xampp\htdocs\mdj_tracking\`

3. Open phpMyAdmin:
   - `http://localhost/phpmyadmin`

4. Create database and tables:
   - Import `schema.sql` (from this project folder)

5. Create the default admin password hash:
   - Open `http://localhost/mdj_tracking/dev_hash.php` after creating it:
     ```php
     <?php echo password_hash("Admin@123", PASSWORD_DEFAULT);
     ```
   - Copy the output hash
   - Paste into `schema.sql` in place of `REPLACE_WITH_REAL_HASH`
   - Re-run the INSERT statement (or update the admin password in phpMyAdmin)

6. Confirm you can open:
   - `http://localhost/mdj_tracking/`

---

## 2) VS Code Setup

1. Open the folder `mdj_tracking` in VS Code.
2. Recommended extensions:
   - PHP Intelephense
   - Prettier (optional)
3. All configuration is in:
   - `includes/config.php` (DB, BASE_URL, registration toggle)

### Department access mode
The system enforces staff access by **files.department**:
- `SCOPE_FILES_TO_DEPARTMENT = true` (default): staff can only access files belonging to their own jabatan.
- Admin can access all.

---

## 3) Import Initial Data From Word (Client Data)

Your client’s Word table columns match the system fields:
`tahun, no_kotak_fail, no_fail_permohonan, tarikh_permohonan_masuk, lot_pt, mukim, aras, kabinet`

### Easy & accurate method (recommended)
1. Open the Word table
2. Copy the table → paste into **Excel**
3. Ensure the columns match the header exactly:
   `tahun,no_kotak_fail,no_fail_permohonan,tarikh_permohonan_masuk,lot_pt,mukim,aras,kabinet`
4. Set `no_kotak_fail` column to **Text** (so Excel keeps `01`)
5. Save As → **CSV UTF-8**
6. Login as admin → Tambah Fail → **Import CSV**

The importer supports dates in:
- `dd/mm/yyyy`
- `dd.mm.yyyy`
- `yyyy-mm-dd`

---

## 4) Security Notes

- Upload directory is protected by `.htaccess` (direct access denied)
- All documents are served via `download_document.php` which checks login
- Prepared statements (PDO) used to prevent SQL injection
- CSRF token required for POST actions
- Admin-only delete actions

---

## 5) Pages (Routes)

- `/login.php`
- `/register.php`
- `/dashboard.php`
- `/add_file.php`
- `/search.php`
- `/view_file.php?id=...`
- `/profile.php`
- `/admin.php` (admin only)
- `/admin_import.php` (admin only, linked from Tambah Fail)
- `/delete_file.php` (admin only POST)
- `/delete_user.php` (admin only POST)
- `/jabatan.php` (admin only - ringkasan jabatan)
- `/theme_toggle.php` (AJAX endpoint - quick theme switch)


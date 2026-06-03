# Gliffen WebP Converter Plugin

A powerful WordPress plugin that converts images to WebP format for improved performance and reduced server storage usage. Automatically converts existing images (JPG, JPEG, PNG, GIF) to WebP, updates database references, and sets up intelligent redirects for legacy image URLs.

---

## ⚙️ System Requirements

### Minimum Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.2 or higher
- **Disk Space**: At least 2× the size of your image library (for WebP files + optional backups)
- **Memory**: Recommend 256MB+ `memory_limit` for image processing

### Required PHP Extensions
- **GD Library** (for WebP conversion) - Most hosting includes this by default
  - Check: Enable in php.ini or contact your host if missing
- **Fileinfo** (for file type detection)

### Server Configuration
- **Apache** with `mod_rewrite` enabled (for .htaccess redirect rules)
  - Check if enabled: Use `.htaccess` write test during plugin setup
- **WordPress Uploads Folder** must be writable (`wp-content/uploads/`)
- **Plugin Directory** must be writable (`wp-content/plugins/gliffen-webp-converter/`)

### Optional
- **ImageMagick**: If available, plugin will use it as fallback for better quality conversion
- **Shell Access**: Not required, but useful for command-line batch operations

---

## 📦 Installation

### Via WordPress Admin (Easiest)

1. **Download** the plugin from [GitHub Releases](../../releases)
   - Click the latest release and download the ZIP file
2. **Upload** via WordPress:
   - Go to **Plugins → Add New → Upload Plugin**
   - Select the ZIP file and click "Install Now"
3. **Activate** the plugin
4. **Access** the plugin: Navigate to **Tools → WebP Converter**

### Manual Installation (Alternative)

If you prefer to upload via SFTP/File Manager:

1. **Download** the ZIP from [GitHub Releases](../../releases)
2. **Extract** the ZIP file locally
3. **Upload** the `gliffen-webp-converter` folder to `/wp-content/plugins/` via SFTP
4. **Activate** in WordPress Admin → Plugins
5. **Access** via **Tools → WebP Converter**

### Verification
After installation, check that:
- Plugin appears in Plugins list and is activated
- "WebP Converter" menu item appears under Tools
- No errors appear in WordPress admin

---

## 🚀 Quick Start / Usage

### Basic Conversion Workflow

#### 1. **Batch Convert All Images**
   - Go to **Tools → WebP Converter**
   - Click **"Start Batch Conversion"**
   - Optionally set "Max Images to Process" (default: 500)
   - Monitor progress in real-time progress bar
   - Conversion process:
     - Converts original image to WebP
     - Updates all database references automatically
     - Deletes original file
     - Backs up original (if enabled in settings)

#### 2. **Convert Single Images**
   - Go to **Media Library**
   - Select image(s) and choose **"Convert to WebP"** from bulk actions
   - OR click image and use conversion option in modal

#### 3. **Auto-Convert On Upload** (Optional)
   - Go to **Tools → WebP Converter → Settings**
   - Enable **"Auto-convert images on upload"**
   - New images uploaded will be automatically converted to WebP

### Settings Configuration

Access **Tools → WebP Converter → Settings** to configure:

| Setting | Default | Range | Description |
|---------|---------|-------|-------------|
| Auto-convert on upload | Off | On/Off | Automatically convert new uploads to WebP |
| WebP quality | 80 | 1-100 | Compression level (lower = smaller, lower quality) |
| Auto-backup originals | On | On/Off | Save original files before deletion |
| Batch size | 200 | 1-500 | Images processed per request (larger = faster but more memory) |

### Monitoring & Maintenance

**Batch Conversion Tab:**
- Real-time progress bar showing conversion status
- Total images converted and remaining count
- Visual indicators for success/failure

**Maintenance Tab:**
- View conversion activity log
- Monitor backup folder size
- Clear old backups (frees disk space)
- Clear activity log
- Configure .htaccess redirect rules

---

## ✨ Features

✅ **Automatic Image Conversion**
- Converts JPG, JPEG, PNG, GIF to WebP
- Preserves image quality with configurable settings
- Typically 20-30% file size reduction

✅ **Database Reference Updates**
- Automatically updates all WordPress tables:
  - Post content (featured images, image tags)
  - Post metadata (custom fields with image URLs)
  - Theme/plugin options (hardcoded image paths)
  - Link manager images
- Single conversion pattern (no complex URL matching)
- Tracks replacement counts per file

✅ **URL Redirect System (3-Layer)**
1. Database updates (converts URL references)
2. .htaccess rules (301 permanent redirects for direct requests)
3. WordPress 404 handler (fallback for missed cases)

✅ **Batch Processing with Real-Time Monitoring**
- Process thousands of images in manageable chunks
- Live progress bar updates
- Configurable batch size (1-500 images per request)
- Stop/resume capability
- Track images processed per run with configurable limit

✅ **Media Library Integration**
- Bulk action for quick conversion of selected images
- Conversion status in media library modal
- One-click conversion for individual images

✅ **Original File Backup**
- Optional automatic backup of originals before deletion
- Organized backup directory mirroring upload structure
- View backup folder size in admin
- One-click clear backup button

✅ **Activity Logging**
- File-based logging (no database bloat)
- Tracks conversions and database updates
- Real-time log watching in admin UI
- Clear log button for cleanup

✅ **Auto-Conversion on Upload**
- New images automatically converted when uploaded (if enabled)
- Maintains WordPress attachment metadata
- Configurable quality settings

---

## 🔧 How It Works

### Image Conversion Process

```
Original Image (jpg/png/gif)
    ↓
GD Library Conversion (ImageMagick fallback)
    ↓
Create WebP Version (same directory)
    ↓
Update Database References (all WordPress tables)
    ↓
Delete Original File (if enabled)
    ↓
Setup Redirect Rules (for legacy URLs)
```

### Database Reference Updates

The plugin scans and updates:
- **wp_posts**: Post content HTML with image tags (`<img>` tags)
- **wp_postmeta**: Custom field values with image URLs
- **wp_options**: Theme/plugin settings with hardcoded paths
- **wp_links**: Link manager images

**Example:**
```
Before: /wp-content/uploads/2024/07/photo.jpg
After:  /wp-content/uploads/2024/07/photo.webp
```

### URL Redirection

**1. Database Updates** (Primary)
- Updates image references directly in database
- Fastest method - immediate effect

**2. .htaccess Rules** (Secondary)
```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_URI} \.(jpg|jpeg|png|gif)$
RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI%.jpg}.webp -f
RewriteRule ^(.*)$ %{REQUEST_URI%.jpg}.webp [L,R=301]
```
- Catches requests to old image files
- Returns 301 permanent redirect to WebP
- Only triggers if WebP exists

**3. 404 Handler** (Fallback)
- WordPress intercepts 404 for missing images
- Plugin checks if .webp equivalent exists
- Redirects if found, otherwise returns 404

---

## 📋 Architecture & Implementation Details

### High-Level Architecture

### 1. Image Conversion Engine
- Uses PHP's GD library (with ImageMagick fallback if available)
- Processes original image and all WordPress intermediate sizes
- Saves WebP alongside original, then deletes original
- Stores metadata: conversion status, original filename, quality settings

### 2. Component Overview

**Core Components:**
- `WIC_Converter`: Image conversion logic and metadata management
- `WIC_Redirect_Handler`: Database updates and .htaccess management
- `WIC_Settings`: Settings wrapper for plugin configuration
- `WIC_File_Logger`: File-based activity logging
- `WIC_Admin_Page`: Admin UI and AJAX handlers

**Integration Points:**
- WordPress upload hooks (auto-conversion on upload)
- WordPress media library (bulk actions, modal integration)
- WordPress admin AJAX (progress updates, settings save)
- .htaccess (redirect rules)

### 3. Conversion Workflow (Batch)

```
1. User starts batch from admin panel
   ↓
2. System queries media library for unconverted images
   ↓
3. For each image in batch:
   a. Load original from disk
   b. Convert to WebP using GD Library
   c. Save WebP with same filename but .webp extension
   d. Update database references (4 queries per image)
   e. Delete original file
   f. Optionally backup original to backup directory
   g. Log conversion details
   ↓
4. Return progress update to admin UI
   ↓
5. Repeat until max_images_per_run reached or all images converted
```

### 4. Database Update Details

**Per-image database updates:**
- 1 query to `wp_posts` (post content)
- 1 query to `wp_postmeta` (custom fields)
- 1 query to `wp_options` (theme/plugin settings)
- 1 query to `wp_links` (link manager, if table exists)

**Pattern used:** Relative file path (e.g., `2024/07/photo.jpg`) matches anywhere in URL via `LIKE '%pattern%'`

**Replacement logic:** REPLACE() function converts old format to .webp
```sql
UPDATE wp_posts 
SET post_content = REPLACE(post_content, '/2024/07/photo.jpg', '/2024/07/photo.webp')
WHERE post_content LIKE '%2024/07/photo.jpg%'
```

---

## 📂 File Structure

```
gliffen-webp-converter/
├── webp-image-converter.php          # Main plugin file & entry point
├── README.md                          # Documentation (this file)
├── uninstall.php                      # Cleanup on uninstall
├── logs/
│   └── conversion.log                 # Activity log (file-based)
├── inc/
│   ├── class-converter.php            # Core conversion logic & metadata
│   ├── class-redirect-handler.php     # Database updates & .htaccess management
│   ├── class-file-logger.php          # File-based activity logging
│   └── class-settings.php             # Settings wrapper & defaults
├── admin/
│   ├── class-admin-page.php           # Admin UI & AJAX handlers
│   ├── css/
│   │   └── admin-style.css            # Admin page styling
│   ├── js/
│   │   └── batch-converter.js         # Frontend batch logic & progress UI
│   └── get-log.php                    # Real-time log endpoint (no WP bootstrap)
└── vendor/                            # Composer dependencies (if included)
```

### Backup Directory Structure
```
wp-content/uploads/original-backups/   # Mirrors upload directory structure
├── 2024/
│   ├── 01/
│   │   ├── image1.jpg                # Original backup
│   │   └── image2.png
│   └── 07/
│       └── photo.jpg
```

---

## 🔒 Logging & Storage

### Activity Log File
- **Location**: `/wp-content/plugins/gliffen-webp-converter/logs/conversion.log`
- **Format**: One entry per file conversion and database update
- **Auto-rotation**: Clear via admin UI to prevent bloat
- **Accessibility**: Real-time view in admin (polls every 5 seconds)

**Example Log Entries:**
```
[2026-06-01 14:23:45] Processing 2024/07/image.jpg → 4 file sizes processed
[2026-06-01 14:23:46] Database update for 2024/07/image.jpg → Posts: 3 | Postmeta: 1 | Options: 0 | Links: 0 | Total: 4
[2026-06-01 14:24:12] Processing 2024/07/photo.png → 8 file sizes processed
```

### Backup Directory
- **Purpose**: Optionally preserve original files before deletion
- **Organization**: Mirrors `/wp-content/uploads/` structure
- **Size Display**: Admin shows total backup folder size
- **Management**: Clear all backups with one-click button in admin

**Example Structure:**
```
Original:  /wp-content/uploads/2025/12/photo.jpg
Backup:    /wp-content/uploads/original-backups/2025/12/photo.jpg
```

---

## ⚙️ Configuration & Advanced Settings

### Plugin Options (stored in wp_options)
All settings prefixed with `wic_`:
- `wic_auto_convert_enabled`: Auto-convert on upload (true/false)
- `wic_webp_quality`: Compression level (1-100)
- `wic_auto_backup_enabled`: Backup originals (true/false)
- `wic_batch_size`: Images per request (1-500)
- `wic_max_images_per_run`: Total per session (default 500)
- `wic_htaccess_configured`: .htaccess rules added (true/false)

### Programmatic Usage

**Convert a single attachment:**
```php
$converter = new WIC_Converter();
$result = $converter->convert_attachment_with_sizes($attachment_id, $quality = 80, $backup = true);
```

**Update database references:**
```php
WIC_Redirect_Handler::update_database_references_for_attachment($attachment_id);
```

**Get conversion stats:**
```php
$stats = $converter->get_conversion_stats();
// Returns: [total_images, converted_count, unconverted_count, total_storage, webp_storage]
```

---

## 🔍 Performance Considerations

### Batch Processing
- **Batch Size**: Default 200 images per AJAX request
  - Larger batches = faster, but more memory usage
  - Smaller batches = slower, but more stable on shared hosting
  - Configurable in Settings (1-500)

- **Max Images Per Run**: Default 500
  - Total images processed before stopping
  - User can resume by clicking Start again
  - Allows chunked processing of large libraries

### Memory Usage
- Image conversion is memory-intensive
- Recommend: `memory_limit` of 256MB+ in `php.ini`
- Monitor admin panel for memory warnings
- Reduce batch size if memory limits exceeded

### Database Impact
- 4 queries per image (minimal footprint)
- Uses REPLACE() function (efficient string replacement)
- No temporary tables or locks
- Safe to run during business hours

---

## 🛡️ Security Considerations

✓ **File Validation**
- Verify MIME types before conversion
- Validate file paths to prevent directory traversal
- Check file permissions before deletion

✓ **Database Security**
- Use prepared statements for dynamic queries
- Sanitize file paths in SQL patterns
- LIKE queries validated against whitelist

✓ **Admin Protection**
- Nonce verification on all AJAX requests
- `manage_options` capability required
- No user input directly in file operations

✓ **Rate Limiting**
- Batch processing prevents abuse
- Configurable batch sizes limit resource usage
- File operations controlled by `wp_filesystem`

---

## 📝 Logging & Troubleshooting

### Check Conversion Status
1. Navigate to **Tools → WebP Converter → Maintenance**
2. View "Conversion Activity" log
3. Click **"Start Watching"** for real-time updates
4. Monitor progress and errors

### Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| "0 replacements" in database update | Images already converted in filesystem but not DB | Re-run conversion - now handles legacy images |
| Conversion times out | Batch size too large for memory limit | Reduce batch size in Settings |
| WebP not created | GD library not installed | Contact hosting, enable GD extension |
| .htaccess not saving | Permissions issue | Check `/var/www/` permissions, contact hosting |
| Images still showing old paths | Database update not run | Run conversion to update database references |

### Enable Debug Logging (Development)
Edit `wp-content/plugins/gliffen-webp-converter/logs/conversion.log` to monitor raw conversion output

---

## ♻️ Uninstall & Cleanup

### On Deactivation
- Plugin remains installed (can be reactivated)
- .htaccess rules remain (manual removal recommended)
- Database records preserved
- WebP files remain on server

### On Uninstall (via WordPress)
1. .htaccess redirect rules removed
2. Plugin files deleted
3. **Backup directory**: Optionally cleared via admin before uninstall
4. **Log file**: Optionally cleared via admin before uninstall

### Manual Cleanup
To restore original images (if backups exist):
```
1. Go to: /wp-content/uploads/original-backups/
2. Copy original files back to /wp-content/uploads/
3. Update database via Tools → WebP Converter or SQL manually
4. Delete backup directory
```

---

## 🤝 Contributing

Contributions welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Test thoroughly before submitting PR
4. Follow WordPress coding standards

---

## 📄 License

This plugin is licensed under the GPL v2 or later. See LICENSE file for details.

---

## 📞 Support

For issues, questions, or feature requests:
- Check this README's troubleshooting section
- Review GitHub Issues for existing solutions
- Create a new GitHub Issue with detailed description

---

## 🔄 Methodology in Detail

### Phase 1: Admin Interface
1. **Batch Conversion**: User navigates to "Tools" → "WebP Converter"
   - Scans media library
   - Starts batch conversion (200 images per batch)
   - Monitors progress in real-time

2. **Media Library Bulk Conversion**: User goes to "Media" library
   - Selects multiple images
   - Uses "Convert to WebP" bulk action
   - Monitors progress in modal dialog
   - Can also access conversion option in media attachment modal

### Phase 2: Conversion Execution
1. Plugin queries media library for unconverted images
2. For each image:
   - Load original file from disk
   - Convert to WebP using GD library (fallback to ImageMagick if available)
   - Save WebP with same filename but `.webp` extension
   - Update database records
   - Delete original file
   - Optionally store mapping in `wp_option` for reference
3. AJAX returns progress updates

### Phase 3: URL Redirection Setup
1. **Database Updates**
   - Update `wp_posts.post_content` (HTML with image tags)
   - Update `wp_postmeta` (custom fields with image URLs)
   - Update `wp_options` (theme/plugin settings with hardcoded image paths)
   - Update `wp_links.link_image` (link manager images)

2. **.htaccess Configuration**
   ```apache
   # WebP Redirect Rules (inserted by plugin)
   <IfModule mod_rewrite.c>
       RewriteEngine On
       
       # Check if WebP exists for requested image
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteCond %{REQUEST_URI} \.(jpg|jpeg|png|gif)$
       RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI%.jpg}.webp -f [OR]
       RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI%.jpeg}.webp -f [OR]
       RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI%.png}.webp -f [OR]
       RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI%.gif}.webp -f
       RewriteRule ^(.*)$ %{REQUEST_URI%.jpg}.webp [L,R=301]
   </IfModule>
   ```

3. **404 Handler**
   - WordPress intercepts 404s for missing files
   - Plugin checks if `.webp` equivalent exists
   - If yes: 301 redirect
   - If no: standard 404

### Phase 4: Auto-Conversion (On Upload)
If enabled in settings:
1. WordPress `wp_handle_upload` hook triggers
2. Plugin detects image file type
3. Creates WebP version immediately
4. **Deletes original file immediately** (if auto-conversion enabled)
5. Optionally backs up original to backup directory before deletion
6. Returns WebP path to WordPress
7. User sees WebP URL in media library

---

## Key Considerations

### Performance
- **Batch Processing**: Process in groups of **200 images** per batch via AJAX
- **Chunking**: Prevents timeout and memory exhaustion on large conversions
- **Memory**: Image conversion can be memory-intensive; may need to increase `memory_limit`

### Safety & Storage
- **Backups**: Optional feature to backup originals before deletion to `/wp-content/uploads/original-backups/`
- **Backup Management**: UI displays backup folder size with option to clear all backups
- **No Rollback**: If issues occur, developer intervention required (restoring from server backups)

### WebP Quality
- **Configurable Setting**: Admin can set WebP quality/compression level (recommend default: 80)
- **Quality Range**: 1-100 (lower = smaller file size, lower quality; higher = larger file size, better quality)

### Browser Compatibility
- WebP has ~95% browser support
- Edge case users without WebP support: Site images will not display, but functionality preserved
- No fallback mechanism implemented (intentional design choice)

---

## Implementation Steps

### Step 1: Plugin Initialization
- Create main plugin file with plugin header
- Register activation/deactivation hooks
- Create necessary directories (logs, backups)
- Initialize admin menu and settings page

### Step 2: Core Conversion Engine
- Build image detection logic
- Implement GD library conversion with fallback
- Create file management (save, delete, backup)
- Implement file-based logging

### Step 3: Admin UI & Batch Processing
- Create WordPress admin page under Tools menu
- Build AJAX handler for batch conversion (200 images per batch)
- Add progress tracking and live updates
- Implement settings form with quality slider
- Add backup folder size display and clear button

### Step 4: Media Library Integration
- Add bulk action for converting multiple images
- Create media modal with conversion link option
- Build dedicated JavaScript handler for bulk actions
- Implement progress dialog for batch processing

### Step 5: URL Redirect System
- Build database reference updater
- Create .htaccess manager
- Implement 404 handler
- Add basic request logging for troubleshooting

### Step 6: Auto-Upload Feature
- Hook into WordPress upload process
- Create auto-conversion logic (delete original immediately)
- Add settings toggle and quality setting

### Step 7: Testing & Optimization
- Test conversion quality and performance
- Verify redirects work correctly
- Test with various image types
- Optimization for large image libraries
- Test backup directory functionality

### Performance
- **Batch Processing**: Process in groups of **200 images** per batch via WP-Cron
- **Chunking**: Prevents timeout and memory exhaustion on large conversions
- **Memory**: Image conversion can be memory-intensive; may need to increase `memory_limit`

### Safety & Storage
- **Backups**: Optional feature to backup originals before deletion to `/wp-content/plugins/webp-image-converter/backups/`
- **Backup Management**: UI displays backup folder size with option to clear all backups
- **No Rollback**: If issues occur, developer intervention required (restoring from server backups)

### WebP Quality
- **Configurable Setting**: Admin can set WebP quality/compression level (recommend default: 80)
- **Quality Range**: 1-100 (lower = smaller file size, lower quality; higher = larger file size, better quality)

### Browser Compatibility
- WebP has ~95% browser support
- Edge case users without WebP support: Site images will not display, but functionality preserved
- No fallback mechanism implemented (intentional design choice)

---

## Security Considerations
- Validate all file paths to prevent directory traversal
- Sanitize database queries
- Check file permissions before deletion
- Implement nonce verification for admin actions
- Rate limiting for batch operations to prevent abuse

---

## Uninstall & Cleanup
- On plugin deactivation: .htaccess rules remain (user can decide to remove via admin)
- On plugin uninstall: Remove .htaccess rules, delete plugin files
- Backup directory: User has option to clear via admin UI before uninstalling
- WebP files: Remain on server (no automatic deletion)


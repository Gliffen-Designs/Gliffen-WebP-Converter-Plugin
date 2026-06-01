# Gliffen WebP Converter Plugin

## Purpose
A WordPress plugin designed to convert images to WebP format for improved performance and reduced server storage usage. The plugin automatically converts existing images (JPG, JPEG, PNG, GIF) to WebP and sets up intelligent redirects for legacy image URLs.

---

## High-Level Architecture

### 1. Image Conversion Process
```
Original Image (jpg/png/gif)
    ↓
ImageMagick/GD Library Conversion
    ↓
Create WebP Version
    ↓
Store WebP File (same directory)
    ↓
Update References in Database
    ↓
Delete Original File
    ↓
Redirect Legacy URL to WebP
```

### 2. Key Components

#### A. Conversion Engine
- **What it does**: Converts images using PHP's GD library or ImageMagick (if available)
- **When it runs**: 
  - Batch conversion via admin UI
  - Auto-conversion on upload (if enabled)
  - Scheduled cleanup via WP-Cron

#### B. URL Redirection System
Three-tiered approach:

1. **Database Reference Updates**
   - Scans WordPress tables: `wp_posts`, `wp_postmeta`, `wp_options`
   - Updates all image references from `.jpg` → `.webp`, `.png` → `.webp`, etc.
   - Maintains mapping of old → new URLs in a custom metadata table

2. **.htaccess Redirect Rules**
   - Catches direct requests to old image files
   - Uses pattern matching to detect WebP equivalents
   - Returns 301 permanent redirect to new WebP file
   - Only triggers if WebP exists (404 fallback if not)

3. **404 Handler (Fallback)**
   - WordPress 404 template intercepts missing images
   - Checks if a WebP equivalent exists
   - 301 redirects if found
   - Logs missing images for debugging

#### C. Admin UI
- **Batch Conversion Dashboard** (Tools → WebP Converter)
  - Scans media library
  - Shows conversion progress with AJAX
  - Displays file size savings
  - Process 200 images per batch

- **Media Library Integration**
  - Bulk action to convert multiple images at once
  - Quick conversion link in media attachment modal
  - Streamlined workflow using WordPress native media library

- **Settings Panel**
  - Enable/disable auto-conversion on upload
  - Image quality settings (WebP compression level)
  - Backup original files option (safety feature)
  - Database reference updates
  - .htaccess configuration manager

---

## Methodology in Detail

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

## Logging & Storage

### Log File (File-Based)
- Location: `/wp-content/plugins/webp-image-converter/logs/conversion.log`
- Format: Minimal entries to prevent bloat
- Contains: Timestamp, image filename, conversion status, file sizes, errors only
- Example entry:
  ```
  [2024-05-28 14:32:15] image.jpg → 2.3MB webp (saved 1.2MB) | Status: OK
  [2024-05-28 14:33:02] photo.png → ERROR: Memory limit exceeded
  ```

### Backup Directory Structure
- Location: `/wp-content/uploads/original-backups/`
- Contains: Original files organized by source directory structure
- Example mapping:
  ```
  Original: /wp-content/uploads/2025/12/photo.jpg
  Backup:   /wp-content/uploads/original-backups/2025/12/photo.jpg
  ```
- UI Feature: Display total backup folder size
- UI Feature: Clear/empty backup folder button
- Filename: Original filename preserved (no timestamps added)
- Easy restoration: Simply copy files back from backup folder to original location

---

## File Structure

```
gliffen-webp-converter/
├── webp-image-converter.php          # Main plugin file
├── README.md                          # This file
├── uninstall.php                      # Cleanup on uninstall
├── logs/
│   └── conversion.log                 # Conversion activity log (file-based)
├── inc/
│   ├── class-converter.php            # Core conversion logic
│   ├── class-redirect-handler.php     # URL redirect management
│   ├── class-file-logger.php          # File-based logging
│   └── class-settings.php             # Settings management
├── admin/
│   ├── class-admin-page.php           # Admin UI & AJAX handlers
│   ├── css/
│   │   ├── admin-style.css            # Settings page styling
│   │   └── media-library.css          # Media library integration styling
│   └── js/
│       ├── batch-converter.js         # Batch conversion interface
│       └── media-library.js           # Media library integration
└── includes/
    └── htaccess-template.txt          # .htaccess rules template

Backups stored separately:
wp-content/uploads/original-backups/   # Mirrors upload directory structure
```

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

---

## Key Considerations

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


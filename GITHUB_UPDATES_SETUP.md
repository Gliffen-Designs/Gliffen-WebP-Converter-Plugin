# GitHub Update Checker Setup Guide

## What This Does
Your plugin will now automatically check your GitHub repository for new releases and display an update notification to all site administrators across your installations.

---

## ⚙️ Configuration Steps

### 1. **Update Your GitHub Repository URL**
Edit `inc/class-update-checker.php` and find line 14:

```php
private $github_repo = 'your-username/Gliffen-WebP-Converter';
```

Replace with your actual GitHub username and repository:
```php
private $github_repo = 'your-github-username/Gliffen-WebP-Converter';
```

### 2. **Update Author Profile URL** (Optional)
On line 76, update:
```php
'author_profile' => 'https://github.com/your-username',
```

### 3. **Set Tested WordPress Version** (Optional but Recommended)
On line 66, update the tested version:
```php
'tested'            => '6.4', // Update to latest WordPress version you've tested
```

---

## 📦 GitHub Release Setup

For the update checker to work, you need to create releases on GitHub with **proper version tags**:

### Creating a Release:

1. Go to your GitHub repository
2. Click **"Releases"** → **"Create a new release"**
3. **Tag version**: Use semantic versioning (e.g., `v1.0.2`, `v1.1.0`)
4. **Release title**: Describe the update (e.g., "Version 1.0.2 - Bug Fixes")
5. **Description**: Write changelog details (this appears in the update modal)
6. **Attach the plugin file**:
   - Create a ZIP file of your plugin: `Gliffen-WebP-Converter.zip`
   - Upload as a binary attachment
7. Click **"Publish release"**

### Alternative: Auto-Generated ZIP
The checker also accepts GitHub's auto-generated zipball if you don't upload a custom ZIP file.

---

## 🧪 Testing

### Test Update Detection Locally:

1. **Manual Cache Clear** (for testing):
   Add this temporarily to your WordPress theme's `functions.php`:
   ```php
   // Remove this after testing!
   if ( current_user_can( 'manage_options' ) && isset( $_GET['clear_wic_cache'] ) ) {
       WIC_Update_Checker::clear_cache();
       wp_die( 'Cache cleared' );
   }
   ```
   Then visit: `yourdomain.com/?clear_wic_cache=1`

2. **Check WordPress Updates Page**:
   - Admin Dashboard → Updates
   - Should show "Gliffen WebP Converter" with available update

3. **View Details**:
   - Click "View details" to see changelog from GitHub

### Simulating a New Version:

1. Update `WIC_VERSION` in `webp-image-converter.php` to a lower version (e.g., `1.0.0`)
2. Create a GitHub release with a higher version tag (e.g., `v1.0.1`)
3. Clear the cache and refresh Updates page
4. Should show update available

---

## 🔄 Update Frequency

- **Check interval**: Every 12 hours (configurable)
- **Cache key**: `wic_github_release_info`
- To change interval, edit line 18:
  ```php
  private $cache_duration = 12 * HOUR_IN_SECONDS; // Change here
  ```

---

## 📋 How It Works

1. **Automatic Checking**: Every 12 hours, WordPress checks your GitHub releases
2. **Version Comparison**: Compares GitHub tag with `WIC_VERSION`
3. **Update Notification**: If remote version > current version, shows update button
4. **One-Click Updates**: Admins can update directly from WordPress dashboard
5. **Logging**: Failed checks logged to your plugin's log file

---

## ⚠️ Important Notes

### GitHub API Rate Limiting
- Free tier: 60 requests/hour for unauthenticated
- For higher volume, add a GitHub token (optional):

```php
private function get_github_release() {
    $url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
    
    $headers = array();
    if ( defined( 'GITHUB_TOKEN' ) ) {
        $headers['Authorization'] = 'token ' . GITHUB_TOKEN;
    }
    
    $response = wp_remote_get( $url, array(
        'headers'   => $headers,
        'sslverify' => true,
        'timeout'   => 10,
    ));
    // ... rest of code
}
```

### Version Format
- Tags should be: `v1.0.0`, `v1.0.1`, `v1.1.0`
- The `v` prefix is automatically stripped
- Must be valid semver format

### Plugin File Path
- Currently set to: `Gliffen-WebP-Converter/webp-image-converter.php`
- Must match your plugin folder structure exactly

---

## 🛠️ Common Issues

### "No update available" showing when it should

1. Check GitHub has a release with a **higher version tag**
2. Clear the transient cache (use the test method above)
3. Verify `$github_repo` is correct
4. Check plugin logs for API errors: `/logs/`

### Release asset not downloading

1. Make sure the ZIP filename contains `.zip` extension
2. Or let GitHub auto-generate the zipball
3. Upload file directly in release assets

### SSL Certificate Issues

If you get SSL errors, temporarily change:
```php
'sslverify' => false, // Not recommended for production
```

---

## 🚀 Next Steps

1. Replace `your-username` in `class-update-checker.php`
2. Create a release on GitHub with version tag
3. Visit your WordPress Updates page to test
4. All your plugin installations will now check for updates automatically!

---

## 📚 Additional Resources

- [GitHub Releases Documentation](https://docs.github.com/en/repositories/releasing-projects-on-github/managing-releases-in-a-repository)
- [WordPress Plugin Update Hooks](https://developer.wordpress.org/plugins/plugins/custom-plugin-repositories/)
- [Semantic Versioning](https://semver.org/)

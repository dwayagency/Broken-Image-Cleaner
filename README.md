# Broken Image Cleaner

**WordPress plugin to automatically find and remove broken images (404) from your posts.**

![Version](https://img.shields.io/badge/version-2.1.0-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-5.0%2B-green.svg)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-red.svg)

---

## 📋 Description

**Broken Image Cleaner** is a powerful and easy-to-use WordPress plugin that helps you keep your content clean by automatically identifying and removing images that return 404 errors.

### 🎯 Problems It Solves

- **Broken images in posts**: Links to images that no longer exist
- **Site migration**: Images with old URLs after a transfer
- **Negative SEO**: 404 images that damage your ranking
- **Poor UX**: Content with missing image placeholders

---

## ✨ Main Features

### 🔍 Smart Scanning
- **Real HTTP verification**: Checks every image with HEAD/GET requests
- **Progressive scanning**: Processes posts in batches to avoid timeouts
- **Advanced filters**: By content type and category
- **Optimized performance**: Configurable timeouts and optimized queries

### 🎨 Professional Interface
- **Intuitive dashboard**: Modern design with cards and statistics
- **Visual progress bar**: Monitor progress in real-time
- **Detailed statistics**: Posts analyzed, images found, 404s detected
- **Complete reports**: List of all broken images with direct links to posts

### 🛡️ Safe Mode
- **Dry Run (Preview)**: See what will be removed before proceeding
- **Result storage**: Save the scan to apply it later
- **Quick deletion**: Remove everything with one click, no rescanning
- **Action confirmation**: Popup confirmation before modifying posts

### 🧹 Smart Cleanup
- **Contextual removal**: Removes entire `<figure>` if it contains the broken image
- **Smart fallback**: Otherwise removes only the `<img>` tag
- **Preserve formatting**: Keeps the rest of HTML content intact

---

## 📦 Installation

### Method 1: Manual Upload

1. Download the `broken-image-cleaner.php` file
2. Access your WordPress dashboard
3. Go to **Plugins → Add New → Upload Plugin**
4. Upload the file and click **Install Now**
5. Activate the plugin

### Method 2: FTP

1. Upload the plugin folder to `/wp-content/plugins/`
2. Go to **Plugins** in the WordPress dashboard
3. Activate **Broken Image Cleaner**

### Method 3: WordPress CLI

```bash
# Copy the file to the plugins directory
cp broken-image-cleaner.php /path/to/wordpress/wp-content/plugins/

# Activate the plugin
wp plugin activate broken-image-cleaner
```

---

## 🚀 How to Use the Plugin

### Step 1: Access the Plugin

After activation, you'll find a new item in the WordPress menu:

**Image Cleaner** 📸 (in the left sidebar)

### Step 2: Configure the Scan

#### Available Options:

1. **Content Type**
   - Posts (blog articles)
   - Pages
   - Custom Post Types (if present)

2. **Category** (optional)
   - Filter only a specific category
   - Or select "All categories"

3. **Posts per Batch**
   - Number of posts to analyze at a time
   - **Recommended**: 50-200 posts
   - Range: 1-500

4. **Preview Mode (Dry Run)**
   - ✅ **Active**: Shows only a report, doesn't modify anything
   - ❌ **Inactive**: Applies changes immediately

### Step 3: Start the Scan

1. Click **"Start Scan"**
2. Wait for completion (may take a few minutes)
3. View the results

### Step 4: Analyze Results

The plugin will show you:

#### 📊 Statistics
- **Posts analyzed**: How many posts were checked
- **Total images**: Number of images found
- **Broken images**: How many return 404
- **Updated posts**: How many posts were modified (if not in dry-run)

#### 📈 Progress
If you have more posts than the batch limit, you'll see:
- Progress bar with percentage
- Remaining posts to analyze
- "Continue Scan" button

#### 🔍 Broken Images Details
For each post with broken images:
- Post ID and title
- Direct link to edit it
- List of broken URLs

### Step 5: Delete Broken Images

#### Method A: Immediate Deletion
1. Uncheck the "Preview Mode" checkbox
2. Click "Start Scan"
3. Changes are applied automatically

#### Method B: Two-Phase Deletion (Recommended)
1. **First scan**: Keep "Preview Mode" active
2. **Analyze results**: Check what will be removed
3. **Delete all**: Click the red button **"🗑️ Delete All Broken Images Now"**
4. **Confirm**: Accept the confirmation popup
5. **Done**: Changes are applied instantly without rescanning

---

## 💡 Usage Examples

### Example 1: Complete Blog Cleanup

**Scenario**: You have 1,500 posts and want to check the entire blog.

```
1. Content Type: Posts
2. Category: All categories
3. Posts per Batch: 100
4. Dry Run: ✅ Active

→ Start Scan
→ Batch 1: 100 posts analyzed (6.7%)
→ Continue Scan
→ Batch 2: 200 posts analyzed (13.3%)
→ ... (continues to 100%)
→ Batch 15: 1500 posts analyzed (100%)
→ Click "Delete All Broken Images Now"
```

### Example 2: Specific Category Check

**Scenario**: You migrated only the "News" category and want to check those images.

```
1. Content Type: Posts
2. Category: News
3. Posts per Batch: 50
4. Dry Run: ✅ Active

→ Start Scan (analyzes only posts in "News")
→ View results
→ Delete if necessary
```

### Example 3: Testing on Pages

**Scenario**: You want to check only static pages.

```
1. Content Type: Pages
2. Category: - (not applicable)
3. Posts per Batch: 200
4. Dry Run: ✅ Active

→ Start Scan
→ Check results
→ Apply changes
```

---

## 🔧 How It Works Technically

### Verification Process

1. **Database Query**: Retrieves posts according to set filters
2. **HTML Parsing**: Uses `DOMDocument` to extract all `<img>` tags
3. **URL Verification**: For each image:
   - Normalizes URL (handles relative, protocol-relative, absolute URLs)
   - Makes HTTP HEAD request (faster)
   - If it fails, tries GET (more reliable)
   - Timeout: 8 seconds, max 3 redirects
4. **Identify 404**: Checks if HTTP code is exactly 404
5. **Smart Removal**:
   - If image is inside `<figure>`: removes the entire block
   - Otherwise: removes only `<img>`
6. **Save**: Updates post content

### Result Storage

When using **Preview Mode**, the plugin:
- Saves results in a WordPress transient
- Stores: post ID, cleaned content, broken URLs
- Duration: 1 hour
- Allows quick deletion without rescanning

---

## 🎨 Screenshots and Interface

### 1. Main Dashboard
- Configuration form with all options
- Notifications if changes are pending
- Large, intuitive buttons

### 2. Scan Results
- Card with statistics in responsive grid
- Animated progress bar (if progressive scan)
- Colored notifications (warning for dry-run, success for completed)

### 3. Quick Delete Button
- Very visible red card
- Broken images counter and affected posts
- Confirmation before action

### 4. Broken Images Detail
- Complete list of problematic posts
- Monospaced tags for URLs
- Direct links to edit posts

---

## 📊 System Requirements

### Minimum Requirements

| Component | Minimum Version |
|-----------|----------------|
| WordPress | 5.0+ |
| PHP | 7.4+ |
| MySQL | 5.6+ |
| RAM | 128 MB |

### Recommended Requirements

| Component | Recommended Version |
|-----------|---------------------|
| WordPress | 6.0+ |
| PHP | 8.0+ |
| MySQL | 8.0+ |
| RAM | 256 MB+ |
| Max Execution Time | 300 seconds |

### Required PHP Functions

- `wp_remote_head()` / `wp_remote_get()`
- `DOMDocument`
- `DOMXPath`
- `libxml`

---

## ⚙️ Advanced Configuration

### Server Timeout

If you have many posts or slow images to verify, you might need to increase PHP timeout:

```php
// In wp-config.php file
set_time_limit(300); // 5 minutes
```

### Memory Limit

For very large sites:

```php
// In wp-config.php file
define('WP_MEMORY_LIMIT', '256M');
```

### Optimal Batch Size

- **Shared Hosting**: 50-100 posts per batch
- **VPS/Dedicated**: 100-200 posts per batch
- **Powerful Servers**: 200-500 posts per batch

---

## ❓ FAQ (Frequently Asked Questions)

### 1. Is the plugin safe to use?

**Yes, absolutely.** The plugin:
- Has a Preview mode that doesn't modify anything
- Asks for confirmation before deleting
- Saves only cleaned HTML content
- Doesn't touch the image database

### 2. What happens if I delete by mistake?

**Always make a backup first!** The plugin:
- Only modifies HTML content of posts
- Doesn't delete files from server
- Changes are permanent (use dry-run first)

### 3. How long does it take?

Depends on:
- Number of posts
- Number of images per post
- Server speed
- Image timeouts

**Estimate**: ~100 posts with 5 images = 2-5 minutes

### 4. Can I cancel the scan?

Yes, you can:
- Close the page during scan
- Redo the scan from scratch
- Dry-run changes expire after 1 hour

### 5. Does it work with Gutenberg and page builders?

**Yes!** The plugin:
- Works at pure HTML level
- Works with Gutenberg, Elementor, WPBakery, etc.
- Preserves all shortcodes and markup

### 6. Does it detect only 404 images?

**Yes, only 404.** The plugin:
- Doesn't detect slow images (timeout)
- Doesn't detect 500, 403 errors, etc.
- Handles redirects correctly

### 7. Are images deleted from the server?

**No.** The plugin:
- Only removes `<img>` tags from HTML content
- Doesn't touch Media Library
- Doesn't delete physical files

### 8. Can I use it on WooCommerce?

**Yes**, if you configure:
- Content Type: `product`
- Will work on product descriptions

---

## 🐛 Troubleshooting

### Problem: Timeout during scan

**Solution:**
- Reduce number of posts per batch (e.g., 50)
- Increase PHP timeout on server
- Use progressive scan instead of all at once

### Problem: Error 500

**Solution:**
- Check PHP logs
- Check memory limit
- Temporarily disable other plugins

### Problem: No images found

**Possible causes:**
- No broken images (great!)
- Category/type filter is too restrictive
- Images are in custom fields (not supported)

### Problem: Doesn't find some broken images

**Possible causes:**
- External server blocking HEAD requests
- Timeout too low (8 seconds)
- Images with errors other than 404 (403, 500, etc.)

---

## 🔄 Changelog

### Version 2.1.0 (2025-10-10)
- 🎨 Updated plugin name to "Eliminare immagini rotte"
- 📝 Updated author to "DWAY Agency"

### Version 2.0.0 (2025-10-10)
- ✨ **NEW**: Completely redesigned interface with modern cards and styles
- ✨ **NEW**: Progressive scanning to handle thousands of posts
- ✨ **NEW**: "Delete All" button after dry-run without rescanning
- ✨ **NEW**: Result storage for 1 hour
- ✨ **NEW**: Animated progress bar
- ✨ **NEW**: Category filter
- ✨ **NEW**: Custom Post Types support
- 🐛 **FIX**: Bug in `wp_update_post()` verification
- 🎨 **IMPROVEMENT**: UX with colored notifications and icons
- 🎨 **IMPROVEMENT**: Statistics in responsive grid

### Version 1.1.0 (2025-10-09)
- ✨ Added category filter
- ✨ Configurable number of posts per execution

### Version 1.0.0 (2025-10-08)
- 🎉 First release
- 🔍 Basic scanning with dry-run
- 🧹 Broken image removal
- 📊 Detailed reports

---

## 👨‍💻 Development

### Code Structure

```
broken-image-cleaner.php
├── Class: DWAY_Broken_Image_Cleaner
│   ├── __construct()           # Initialize hooks
│   ├── add_admin_page()         # Register admin menu
│   ├── enqueue_admin_styles()   # Load inline CSS
│   ├── render_page()            # Render interface
│   ├── count_posts()            # Count total posts
│   ├── scan_and_clean()         # Main scanning
│   ├── process_content()        # HTML parsing and cleanup
│   ├── is_404()                 # Check URL 404
│   ├── response_code()          # Extract HTTP code
│   ├── store_changes()          # Store results
│   └── apply_stored_changes()   # Apply saved changes
└── Plugin initialization
```

### Contributing

This is a private plugin developed by **DWAY Agency**. For requests or bugs, contact us.

---

## 📄 License

This plugin is released under the **GPL v2** license or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

## 👥 Credits

**Developed by:** DWAY Agency  
**Version:** 2.1.0  
**Last update:** October 2025

---

## 📞 Support

For support, bug reports or feature requests:

- 📧 Email: [insert support email]
- 🌐 Website: [insert website]
- 💬 GitHub Issues: [if applicable]

---

## 🎯 Future Roadmap

Features planned for future versions:

- [ ] **Automatic cron**: Weekly scheduled scanning
- [ ] **Email reports**: Notifications on results
- [ ] **CSV export**: Download broken images list
- [ ] **Advanced filters**: By date, author, tags
- [ ] **Automatic backup**: Before modifying posts
- [ ] **Detailed logs**: Operation history
- [ ] **Multi-site support**: WordPress Network compatibility
- [ ] **REST API**: External integration

---

## 🙏 Acknowledgments

Thank you for choosing **Broken Image Cleaner**!

If the plugin was useful to you, consider:
- ⭐ Leaving a review
- 📢 Recommending it to others
- 🐛 Reporting bugs or suggestions

---

**Made with ❤️ by DWAY Agency**

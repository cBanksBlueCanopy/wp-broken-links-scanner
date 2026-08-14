# Broken Links Scanner - WordPress Plugin

A comprehensive WordPress plugin that scans all pages and posts on your website for broken links (404 errors) and provides an easy-to-use admin interface to view and manage results.

## Features

- **Automatic Link Scanning**: Scans all published posts and pages for external links
- **404 Detection**: Identifies links that return HTTP 404 (Not Found) errors
- **Detailed Results**: Shows broken links and the specific pages where they're found
- **Edit Links**: Quick access to edit pages containing broken links
- **Persistent Results**: Stores scan results for easy reference
- **Admin Dashboard**: User-friendly interface in WordPress admin (Tools > Broken Links)
- **One-Click Scanning**: Simple button to start a new scan
- **Result Management**: Clear old results with one click

## Installation


### Method 1: Automatic Installation

1. Download the code as a zip file from the github repo.
2. Upload zip file to WP-Admin -> Plugins -> add new.

### Method 2: Manual Installation

1. Download the plugin files
2. Create a folder named `broken-links-scanner` in your WordPress plugins directory:
   ```
   /wp-content/plugins/broken-links-scanner/
   ```
3. Extract the plugin files into this folder:
   - `broken-links-scanner.php` (main plugin file)
   - `css/admin.css` (styles)
   - `js/admin.js` (JavaScript)

4. Go to **Plugins** in the WordPress admin and activate the plugin

### Method 3: Upload via WordPress Admin

1. Download the plugin as a ZIP file with the following structure:
   ```
   broken-links-scanner/
   ├── broken-links-scanner.php
   ├── css/
   │   └── admin.css
   ├── js/
   │   └── admin.js
   └── README.md
   ```

2. Go to **Plugins > Add New** in WordPress admin
3. Click **Upload Plugin**
4. Select the ZIP file and upload
5. Click **Activate Plugin**

## Usage

### Running a Scan

1. Go to **Tools > Broken Links** in the WordPress admin
2. Click the **Start Scan** button
3. Wait for the scan to complete (this may take a few minutes depending on your site size)
4. Results will automatically reload and display

### Understanding the Results

The plugin displays a table with three columns:

| Column | Description |
|--------|-------------|
| **Broken Link** | The URL that returned a 404 error |
| **Found On Page** | The page/post containing the broken link (with an Edit button) |
| **HTTP Status** | The HTTP status code returned (typically 404) |

### Clearing Results

1. Click the **Clear Results** button
2. Confirm the action when prompted
3. All previous scan results will be deleted

## How It Works

### Scanning Process

1. **Retrieves all content**: Fetches all published posts and pages from your WordPress database
2. **Extracts links**: Parses HTML content and extracts all `<a href="">` links
3. **Checks each link**: Makes HTTP requests to each URL to determine its status
4. **Identifies broken links**: Records any links that return a 404 status code
5. **Stores results**: Saves findings to WordPress options for later review

### Link Detection

The plugin:
- Extracts links from post/page content and HTML
- Removes duplicate links
- Skips anchor links (# fragments)
- Supports both HTTP and HTTPS URLs
- Ignores relative links and non-HTTP protocols

### HTTP Checking

The plugin:
- Uses WordPress's built-in HTTP API (`wp_remote_head()` and `wp_remote_get()`)
- Sets a 5-second timeout for each request
- Attempts both HEAD and GET requests for reliability
- Records the HTTP status code for each link

## Limitations

- **Internal links only in scope**: Links to external sites are checked; internal WordPress links are not typically 404
- **Timeout handling**: Links that timeout (no response within 5 seconds) are marked with status 0
- **Redirect handling**: Links that redirect are marked with their final HTTP status
- **SSL verification**: Disabled by default for compatibility, can be customized via filters
- **Performance**: Large sites may take longer to scan

## Technical Details

### Hooks and Filters

The plugin includes a filter to customize SSL verification:

```php
apply_filters( 'https_local_ssl_verify', false )
```

You can hook into this filter to enable SSL verification:

```php
add_filter( 'https_local_ssl_verify', '__return_true' );
```

### Database Storage

Results are stored as WordPress options:
- `broken_links_results` - Array of broken link data
- `broken_links_scan_time` - Timestamp of last scan

### Security

- Uses WordPress nonces for AJAX requests
- Checks user capabilities (`manage_options`) for all admin functions
- Sanitizes and escapes all output
- Follows WordPress coding standards and best practices

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- cURL or Streams PHP extension for HTTP requests

## Performance Considerations

Scanning large sites with many links may take several minutes. Consider:

- Running scans during off-peak hours
- Increasing PHP execution time if scans timeout
- Checking your hosting provider's policies on automated HTTP requests

If you experience timeouts:

```php
// Add to wp-config.php
define( 'WP_MEMORY_LIMIT', '256M' );
set_time_limit( 300 ); // 5 minutes
```

## Troubleshooting

### Scan doesn't complete

**Solution**: Increase PHP execution time or run the scan again. Check your server error logs for issues.

### No broken links found, but I know there are some

**Solution**: 
- The links might return status codes other than 404
- Links might be redirecting to valid pages
- External sites might block automated requests

### Can't access the plugin page

**Solution**: Ensure you're logged in as an administrator. The plugin requires `manage_options` capability.

### AJAX requests failing

**Solution**: 
- Check that your site's AJAX is working properly
- Ensure JavaScript is enabled in your browser
- Check browser console for error messages
- Verify the site's URL structure in WordPress Settings

## Support

For issues or feature requests, check:

1. The WordPress plugin repository
2. The plugin's documentation
3. Your hosting provider's support for PHP configuration issues

## License

This plugin is licensed under the GPL v2 or later. See LICENSE file for details.

## Changelog

### Version 1.1.0
- Scans the rendered frontend of each published post/page, including theme header and footer links
- Resolves relative links before checking their HTTP status
- Detects pages containing multiple H1 tags and reports the page name and H1 count
- Caches repeated link status checks to reduce duplicate requests

### Version 1.0.0
- Initial release
- Broken link detection (404 errors)
- Admin dashboard interface
- AJAX-based scanning
- Results storage and management
- Full WordPress compatibility

## Contributing

To contribute improvements:

1. Test the plugin thoroughly in your WordPress environment
2. Document any changes clearly
3. Ensure code follows WordPress coding standards
4. Test with various site configurations

---

**Happy link checking!**

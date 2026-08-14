<?php
/**
 * Plugin Name: Broken Links Scanner
 * Plugin URI: https://github.com/cBanksBlueCanopy/wp-broken-links-scanner
 * Description: Scan your WordPress site for broken links (404 errors) and view results in the admin panel
 * Version: 1.1.0
 * Author: Chris Banks
 * Author URI: https://mahoneymarketingllc.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: broken-links-scanner
 * Domain Path: /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'BROKEN_LINKS_SCANNER_VERSION', '1.1.0' );
define( 'BROKEN_LINKS_SCANNER_DIR', plugin_dir_path( __FILE__ ) );
define( 'BROKEN_LINKS_SCANNER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
class Broken_Links_Scanner {

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WordPress admin
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_scan_request' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_bls_run_scan', array( $this, 'ajax_run_scan' ) );
        add_action( 'wp_ajax_bls_clear_results', array( $this, 'ajax_clear_results' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __( 'Broken Links Scanner', 'broken-links-scanner' ),
            __( 'Broken Links', 'broken-links-scanner' ),
            'manage_options',
            'broken-links-scanner',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_styles( $hook ) {
        if ( 'tools_page_broken-links-scanner' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'broken-links-scanner-admin',
            BROKEN_LINKS_SCANNER_URL . 'css/admin.css',
            array(),
            BROKEN_LINKS_SCANNER_VERSION
        );

        wp_enqueue_script(
            'broken-links-scanner-admin',
            BROKEN_LINKS_SCANNER_URL . 'js/admin.js',
            array( 'jquery' ),
            BROKEN_LINKS_SCANNER_VERSION,
            true
        );

        wp_localize_script(
            'broken-links-scanner-admin',
            'brokenLinksScanner',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'broken_links_scanner_nonce' ),
                'scanning' => __( 'Scanning...', 'broken-links-scanner' ),
                'completed' => __( 'Scan completed!', 'broken-links-scanner' ),
            )
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'broken-links-scanner' ) );
        }

        $broken_links = get_option( 'broken_links_results', array() );
        $multiple_h1_results = get_option( 'broken_links_multiple_h1_results', array() );
        $scan_time = get_option( 'broken_links_scan_time', '' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Broken Links Scanner', 'broken-links-scanner' ); ?></h1>

            <div class="bls-container">
                <div class="bls-controls">
                    <button id="bls-scan-button" class="button button-primary">
                        <?php echo esc_html__( 'Start Scan', 'broken-links-scanner' ); ?>
                    </button>
                    <?php if ( ! empty( $broken_links ) || ! empty( $multiple_h1_results ) ) : ?>
                        <button id="bls-clear-button" class="button">
                            <?php echo esc_html__( 'Clear Results', 'broken-links-scanner' ); ?>
                        </button>
                    <?php endif; ?>
                    <div id="bls-status" class="bls-status"></div>
                </div>

                <?php if ( ! empty( $scan_time ) ) : ?>
                    <div class="bls-info">
                        <p>
                            <?php
                            echo sprintf(
                                /* translators: %s: scan timestamp */
                                esc_html__( 'Last scanned: %s', 'broken-links-scanner' ),
                                esc_html( $scan_time )
                            );
                            ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ( empty( $broken_links ) ) : ?>
                    <div class="bls-no-results">
                        <p><?php echo esc_html__( 'No broken links found. Run a scan to get started.', 'broken-links-scanner' ); ?></p>
                    </div>
                <?php else : ?>
                    <div class="bls-results">
                        <h2><?php echo esc_html__( 'Broken Links Found', 'broken-links-scanner' ); ?></h2>
                        <p class="bls-count">
                            <?php
                            echo sprintf(
                                /* translators: %d: number of broken links */
                                esc_html__( 'Total broken links: %d', 'broken-links-scanner' ),
                                count( $broken_links )
                            );
                            ?>
                        </p>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th class="column-link"><?php echo esc_html__( 'Broken Link', 'broken-links-scanner' ); ?></th>
                                    <th class="column-page"><?php echo esc_html__( 'Found On', 'broken-links-scanner' ); ?></th>
                                    <th class="column-status"><?php echo esc_html__( 'HTTP Status', 'broken-links-scanner' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $broken_links as $broken_link ) : ?>
                                    <tr>
                                        <td class="column-link">
                                            <code><?php echo esc_url( $broken_link['url'] ); ?></code>
                                        </td>
                                        <td class="column-page">
                                            <?php
                                            $location = isset( $broken_link['location'] ) ? $broken_link['location'] : 'page';
                                            if ( 'menu' === $location ) :
                                                echo esc_html__( 'Found in Menu', 'broken-links-scanner' );
                                            elseif ( 'footer' === $location ) :
                                                echo esc_html__( 'Found in Footer', 'broken-links-scanner' );
                                            elseif ( 'header' === $location ) :
                                                echo esc_html__( 'Found in Header', 'broken-links-scanner' );
                                            else :
                                                $page_id = $broken_link['page_id'];
                                                $page = get_post( $page_id );
                                                if ( $page ) :
                                                    echo esc_html( $page->post_title ) . ' ';
                                                    echo sprintf(
                                                        '(<a href="%s" target="_blank">%s</a>)',
                                                        esc_url( get_edit_post_link( $page_id ) ),
                                                        esc_html__( 'Edit', 'broken-links-scanner' )
                                                    );
                                                else :
                                                    echo esc_html__( 'Unknown Page', 'broken-links-scanner' );
                                                endif;
                                            endif;
                                            ?>
                                        </td>
                                        <td class="column-status">
                                            <span class="status-badge status-<?php echo esc_attr( $broken_link['status_code'] ); ?>">
                                                <?php echo esc_html( $broken_link['status_code'] ); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $multiple_h1_results ) ) : ?>
                    <div class="bls-results bls-h1-results">
                        <h2><?php echo esc_html__( 'H1 Tag Issues', 'broken-links-scanner' ); ?></h2>
                        <p class="bls-count">
                            <?php
                            echo sprintf(
                                /* translators: %d: number of pages with H1 tag issues */
                                esc_html__( 'Pages with H1 tag issues: %d', 'broken-links-scanner' ),
                                count( $multiple_h1_results )
                            );
                            ?>
                        </p>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th class="column-page"><?php echo esc_html__( 'Page Name', 'broken-links-scanner' ); ?></th>
                                    <th><?php echo esc_html__( 'H1 Tags', 'broken-links-scanner' ); ?></th>
                                    <th><?php echo esc_html__( 'Issue', 'broken-links-scanner' ); ?></th>
                                    <th><?php echo esc_html__( 'Page URL', 'broken-links-scanner' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $multiple_h1_results as $h1_result ) : ?>
                                    <tr>
                                        <td class="column-page">
                                            <?php
                                            echo esc_html( $h1_result['page_name'] ) . ' ';
                                            echo sprintf(
                                                '(<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>)',
                                                esc_url( get_edit_post_link( $h1_result['page_id'] ) ),
                                                esc_html__( 'Edit', 'broken-links-scanner' )
                                            );
                                            ?>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html( $h1_result['h1_count'] ); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo 0 === (int) $h1_result['h1_count'] ? esc_html__( 'No H1 tag found', 'broken-links-scanner' ) : esc_html__( 'Multiple H1 tags', 'broken-links-scanner' ); ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url( $h1_result['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html( $h1_result['url'] ); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle scan request via AJAX
     */
    public function ajax_run_scan() {
        check_ajax_referer( 'broken_links_scanner_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'broken-links-scanner' ) );
        }

        $scan_results = $this->scan_site_for_broken_links();
        $broken_links = $scan_results['broken_links'];
        $multiple_h1_results = $scan_results['multiple_h1_results'];
        $scan_time = current_time( 'mysql' );

        // Save results
        update_option( 'broken_links_results', $broken_links );
        update_option( 'broken_links_multiple_h1_results', $multiple_h1_results );
        update_option( 'broken_links_scan_time', $scan_time );

        wp_send_json_success( array(
            'count' => count( $broken_links ),
            'h1_count' => count( $multiple_h1_results ),
            'message' => sprintf(
                /* translators: %d: number of broken links, %d: number of pages with multiple H1s */
                __( 'Scan completed. Found %1$d broken links and %2$d pages with H1 tag issues.', 'broken-links-scanner' ),
                count( $broken_links ),
                count( $multiple_h1_results )
            ),
        ) );
    }

    /**
     * Handle clear results via AJAX
     */
    public function ajax_clear_results() {
        check_ajax_referer( 'broken_links_scanner_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions', 'broken-links-scanner' ) );
        }

        delete_option( 'broken_links_results' );
        delete_option( 'broken_links_multiple_h1_results' );
        delete_option( 'broken_links_scan_time' );

        wp_send_json_success( __( 'Results cleared.', 'broken-links-scanner' ) );
    }

    /**
     * Main scanning function
     */
    private function scan_site_for_broken_links() {
        $broken_links = array();
        $h1_results = array();
        $status_cache = array();
        $reported_links = array();

        $args = array(
            'post_type' => array( 'post', 'page' ),
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );

        $posts = get_posts( $args );

        foreach ( $posts as $post ) {
            $page_url = get_permalink( $post->ID );

            if ( ! $page_url ) {
                continue;
            }

            $page_html = $this->get_rendered_page_html( $page_url );

            if ( false === $page_html ) {
                $links = array();
                foreach ( $this->extract_links_from_content( $post->post_content ) as $link ) {
                    $links[] = array(
                        'url' => $link,
                        'location' => 'page',
                    );
                }
                $h1_count = null;
            } else {
                $links = $this->extract_links_from_html( $page_html, $page_url );
                $h1_count = $this->count_h1_tags( $page_html );
            }

            // Report both missing H1s and multiple H1s.
            if ( null !== $h1_count && ( 0 === $h1_count || $h1_count > 1 ) ) {
                $h1_results[] = array(
                    'page_id' => $post->ID,
                    'page_name' => $post->post_title,
                    'url' => $page_url,
                    'h1_count' => $h1_count,
                );
            }

            foreach ( $links as $link_data ) {
                $link = $link_data['url'];
                $location = $link_data['location'];

                if ( ! array_key_exists( $link, $status_cache ) ) {
                    $status_cache[ $link ] = $this->check_link_status( $link );
                }

                $status_code = $status_cache[ $link ];

                if ( 404 !== $status_code ) {
                    continue;
                }

                // Menu/footer/header links are site-wide. Report them once using their location
                // instead of attributing them to whichever page happened to be scanned first.
                if ( in_array( $location, array( 'menu', 'footer', 'header' ), true ) ) {
                    $report_key = $link . '|' . $location;

                    if ( isset( $reported_links[ $report_key ] ) ) {
                        continue;
                    }

                    $reported_links[ $report_key ] = true;
                    $broken_links[] = array(
                        'url' => $link,
                        'page_id' => 0,
                        'location' => $location,
                        'status_code' => $status_code,
                    );
                } else {
                    $broken_links[] = array(
                        'url' => $link,
                        'page_id' => $post->ID,
                        'location' => 'page',
                        'status_code' => $status_code,
                    );
                }
            }
        }

        return array(
            'broken_links' => $broken_links,
            'multiple_h1_results' => $h1_results,
        );
    }

    /**
     * Fetch the rendered frontend page so theme/header/footer links are included.
     */
    private function get_rendered_page_html( $url ) {
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 15,
                'redirection' => 5,
                'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
                'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code < 200 || $status_code >= 400 ) {
            return false;
        }

        return wp_remote_retrieve_body( $response );
    }

    /**
     * Extract links from the complete rendered HTML and convert relative URLs to absolute URLs.
     */
    private function extract_links_from_html( $html, $page_url ) {
        $links = array();

        if ( class_exists( 'DOMDocument' ) ) {
            $previous_state = libxml_use_internal_errors( true );
            $dom = new DOMDocument();
            $dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
            libxml_clear_errors();
            libxml_use_internal_errors( $previous_state );

            foreach ( $dom->getElementsByTagName( 'a' ) as $anchor ) {
                $href = trim( $anchor->getAttribute( 'href' ) );

                if ( empty( $href ) ) {
                    continue;
                }

                $absolute_url = $this->make_absolute_url( $href, $page_url );

                if ( ! $absolute_url ) {
                    continue;
                }

                $location = 'page';
                $node = $anchor;

                // A link inside a nav is considered a menu link. Footer takes precedence
                // so footer navigation is reported as Found in Footer rather than Menu.
                while ( $node instanceof DOMElement && $node->parentNode ) {
                    $node = $node->parentNode;

                    if ( $node instanceof DOMElement ) {
                        $tag = strtolower( $node->tagName );

                        if ( 'footer' === $tag ) {
                            $location = 'footer';
                            break;
                        }

                        if ( 'nav' === $tag ) {
                            $location = 'menu';
                            break;
                        }

                        if ( 'header' === $tag && 'page' === $location ) {
                            $location = 'header';
                        }
                    }
                }

                $links[] = array(
                    'url' => $absolute_url,
                    'location' => $location,
                );
            }
        } elseif ( preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\']/i', $html, $matches ) ) {
            foreach ( $matches[1] as $href ) {
                $absolute_url = $this->make_absolute_url( trim( $href ), $page_url );

                if ( $absolute_url ) {
                    $links[] = array(
                        'url' => $absolute_url,
                        'location' => 'page',
                    );
                }
            }
        }

        // Preserve the first occurrence/location of each link on a page.
        $unique = array();
        foreach ( $links as $link_data ) {
            $key = $link_data['url'] . '|' . $link_data['location'];
            $unique[ $key ] = $link_data;
        }

        return array_values( $unique );
    }

    /**
     * Convert a relative URL into an absolute HTTP(S) URL.
     */
    private function make_absolute_url( $url, $base_url ) {
        if ( empty( $url ) ) {
            return '';
        }

        // Remove URL fragments because they are not sent to the server.
        $fragment_position = strpos( $url, '#' );
        if ( false !== $fragment_position ) {
            $url = substr( $url, 0, $fragment_position );
        }

        // Ignore in-page anchors and non-web protocols.
        if ( empty( $url ) || preg_match( '/^(?:mailto:|tel:|javascript:|data:|sms:)/i', $url ) ) {
            return '';
        }

        // Protocol-relative URL.
        if ( 0 === strpos( $url, '//' ) ) {
            $scheme = wp_parse_url( $base_url, PHP_URL_SCHEME );
            return ( $scheme ? $scheme . ':' : 'https:' ) . $url;
        }

        // Already absolute HTTP(S) URL.
        if ( preg_match( '/^https?:\/\//i', $url ) ) {
            return $url;
        }

        $base = wp_parse_url( $base_url );

        if ( empty( $base['scheme'] ) || empty( $base['host'] ) ) {
            return '';
        }

        $scheme = $base['scheme'];
        $host = $base['host'];
        $port = ! empty( $base['port'] ) ? ':' . $base['port'] : '';

        if ( '/' === $url[0] ) {
            $path = $url;
        } else {
            $base_path = isset( $base['path'] ) ? $base['path'] : '/';
            $directory = trailingslashit( dirname( $base_path ) );
            $path = $directory . $url;
        }

        // Normalize /./ and /../ path segments.
        $segments = explode( '/', $path );
        $normalized = array();

        foreach ( $segments as $segment ) {
            if ( '' === $segment || '.' === $segment ) {
                continue;
            }

            if ( '..' === $segment ) {
                array_pop( $normalized );
            } else {
                $normalized[] = $segment;
            }
        }

        $path = '/' . implode( '/', $normalized );

        // Preserve a query string when one is present in the relative URL.
        return $scheme . '://' . $host . $port . $path;
    }

    /**
     * Count H1 elements in the complete rendered HTML.
     */
    private function count_h1_tags( $html ) {
        if ( class_exists( 'DOMDocument' ) ) {
            $previous_state = libxml_use_internal_errors( true );
            $dom = new DOMDocument();
            $dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
            libxml_clear_errors();
            libxml_use_internal_errors( $previous_state );

            return $dom->getElementsByTagName( 'h1' )->length;
        }

        return preg_match_all( '/<h1\b[^>]*>/i', $html, $matches );
    }

    /**
     * Extract all links from post content
     */
    private function extract_links_from_content( $content ) {
        $links = array();

        // Remove shortcodes to avoid parsing their content
        $content = preg_replace( '/\[[a-zA-Z_\-]*\].*?\[\/[a-zA-Z_\-]*\]/', '', $content );

        // Find all <a> tags with href attribute
        if ( preg_match_all( '/href=["\']([^"\']+)["\']/', $content, $matches ) ) {
            $links = $matches[1];
        }

        // Filter out anchors and remove duplicates
        $links = array_unique( $links );
        $links = array_filter( $links, function( $link ) {
            return ! empty( $link ) && '#' !== $link[0];
        } );

        return array_values( $links );
    }

    /**
     * Check HTTP status of a link
     */
    private function check_link_status( $url ) {
        // Skip non-HTTP(S) URLs
        if ( ! preg_match( '/^https?:\/\//', $url ) ) {
            return null;
        }

        // Use WordPress HTTP API
        $response = wp_remote_head(
            $url,
            array(
                'timeout' => 5,
                'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
                'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
            )
        );

        // If we got an error, try GET request
        if ( is_wp_error( $response ) ) {
            $response = wp_remote_get(
                $url,
                array(
                    'timeout' => 5,
                    'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
                    'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
                )
            );
        }

        // Return status code or error
        if ( is_wp_error( $response ) ) {
            return 0; // Connection error
        }

        return wp_remote_retrieve_response_code( $response );
    }

    /**
     * Handle manual scan request (legacy)
     */
    public function handle_scan_request() {
        if ( ! isset( $_POST['bls_scan'] ) ) {
            return;
        }

        check_admin_referer( 'broken_links_scanner_action', 'broken_links_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'broken-links-scanner' ) );
        }

        $scan_results = $this->scan_site_for_broken_links();
        update_option( 'broken_links_results', $scan_results['broken_links'] );
        update_option( 'broken_links_multiple_h1_results', $scan_results['multiple_h1_results'] );
        update_option( 'broken_links_scan_time', current_time( 'mysql' ) );

        wp_safe_remote_post( $_SERVER['REQUEST_URI'] );
    }
}

// Initialize the plugin
new Broken_Links_Scanner();

<?php
/**
 * Plugin Name: Broken Links Scanner
 * Plugin URI: https://example.com/broken-links-scanner
 * Description: Scan your WordPress site for broken links (404 errors) and view results in the admin panel
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
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
define( 'BROKEN_LINKS_SCANNER_VERSION', '1.0.0' );
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
        $scan_time = get_option( 'broken_links_scan_time', '' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Broken Links Scanner', 'broken-links-scanner' ); ?></h1>

            <div class="bls-container">
                <div class="bls-controls">
                    <button id="bls-scan-button" class="button button-primary">
                        <?php echo esc_html__( 'Start Scan', 'broken-links-scanner' ); ?>
                    </button>
                    <?php if ( ! empty( $broken_links ) ) : ?>
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
                                    <th class="column-page"><?php echo esc_html__( 'Found On Page', 'broken-links-scanner' ); ?></th>
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

        $broken_links = $this->scan_site_for_broken_links();
        $scan_time = current_time( 'mysql' );

        // Save results
        update_option( 'broken_links_results', $broken_links );
        update_option( 'broken_links_scan_time', $scan_time );

        wp_send_json_success( array(
            'count' => count( $broken_links ),
            'message' => sprintf(
                /* translators: %d: number of broken links */
                __( 'Scan completed. Found %d broken links.', 'broken-links-scanner' ),
                count( $broken_links )
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
        delete_option( 'broken_links_scan_time' );

        wp_send_json_success( __( 'Results cleared.', 'broken-links-scanner' ) );
    }

    /**
     * Main scanning function
     */
    private function scan_site_for_broken_links() {
        $broken_links = array();

        // Get all published posts and pages
        $args = array(
            'post_type' => array( 'post', 'page' ),
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );

        $posts = get_posts( $args );

        foreach ( $posts as $post ) {
            // Get all links from post content
            $links = $this->extract_links_from_content( $post->post_content );

            foreach ( $links as $link ) {
                // Check if link is broken
                $status_code = $this->check_link_status( $link );

                if ( 404 === $status_code ) {
                    $broken_links[] = array(
                        'url' => $link,
                        'page_id' => $post->ID,
                        'status_code' => $status_code,
                    );
                }
            }
        }

        return $broken_links;
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

        $broken_links = $this->scan_site_for_broken_links();
        update_option( 'broken_links_results', $broken_links );
        update_option( 'broken_links_scan_time', current_time( 'mysql' ) );

        wp_safe_remote_post( $_SERVER['REQUEST_URI'] );
    }
}

// Initialize the plugin
new Broken_Links_Scanner();

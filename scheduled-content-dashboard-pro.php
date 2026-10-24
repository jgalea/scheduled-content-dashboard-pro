<?php
/**
 * Plugin Name:       Scheduled Content Dashboard Pro
 * Plugin URI:        https://wordpress.org/plugins/scheduled-content-dashboard/
 * Description:       Adds a drag-and-drop Content Board to Scheduled Content Dashboard: move posts between statuses, publish from the board, undo any move.
 * Version:           1.0.1
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            jeangalea
 * Author URI:        https://jeangalea.com
 * Text Domain:       scheduled-content-dashboard-pro
 * Domain Path:       /languages
 * Update URI:        https://github.com/jgalea/scheduled-content-dashboard-pro
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  scheduled-content-dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SCDP_VERSION', '1.0.1' );
define( 'SCDP_FILE', __FILE__ );
define( 'SCDP_MIN_FREE_VERSION', '3.1.0' );

require_once plugin_dir_path( __FILE__ ) . 'includes/updater.php';

/**
 * The board reads the free plugin's settings, status registry, and menu, so it
 * only boots once that plugin is active and new enough.
 */
function scdp_dependency_error() {
    if ( ! class_exists( 'Scheduled_Content_Dashboard' ) ) {
        return __( 'Scheduled Content Dashboard Pro needs the free Scheduled Content Dashboard plugin to be installed and active.', 'scheduled-content-dashboard-pro' );
    }
    if ( version_compare( Scheduled_Content_Dashboard::VERSION, SCDP_MIN_FREE_VERSION, '<' ) ) {
        return sprintf(
            /* translators: 1: required version, 2: installed version. */
            __( 'Scheduled Content Dashboard Pro needs Scheduled Content Dashboard %1$s or newer. You have %2$s.', 'scheduled-content-dashboard-pro' ),
            SCDP_MIN_FREE_VERSION,
            Scheduled_Content_Dashboard::VERSION
        );
    }
    return '';
}

add_action(
    'plugins_loaded',
    function () {
        load_plugin_textdomain( 'scheduled-content-dashboard-pro', false, dirname( plugin_basename( SCDP_FILE ) ) . '/languages' );

        $error = scdp_dependency_error();
        if ( $error ) {
            add_action(
                'admin_notices',
                function () use ( $error ) {
                    printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $error ) );
                }
            );
            return;
        }

        require_once plugin_dir_path( SCDP_FILE ) . 'includes/class-scdp-board.php';

        SCDP_Board::init();
    },
    5
);

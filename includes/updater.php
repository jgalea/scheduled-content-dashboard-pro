<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const SCDP_REPO = 'jgalea/scheduled-content-dashboard-pro';

function scdp_latest_release() {
    $release = get_site_transient( 'scdp_release' );
    if ( false !== $release ) {
        return $release ? $release : null;
    }

    $release  = array();
    $response = wp_remote_get(
        'https://api.github.com/repos/' . SCDP_REPO . '/releases/latest',
        array(
            'timeout' => 10,
            'headers' => array( 'Accept' => 'application/vnd.github+json' ),
        )
    );

    if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
            if ( 'scheduled-content-dashboard-pro.zip' === ( $asset['name'] ?? '' ) ) {
                $release = array(
                    'version' => ltrim( (string) $data['tag_name'], 'v' ),
                    'package' => $asset['browser_download_url'],
                    'url'     => $data['html_url'],
                    'notes'   => (string) ( $data['body'] ?? '' ),
                );
                break;
            }
        }
    }

    set_site_transient( 'scdp_release', $release, $release ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );

    return $release ? $release : null;
}

// Hooked through the plugin's Update URI header, so WordPress.org never answers for this slug.
function scdp_check_update( $update, $plugin_data, $plugin_file ) {
    if ( plugin_basename( SCDP_FILE ) !== $plugin_file ) {
        return $update;
    }

    $release = scdp_latest_release();
    if ( ! $release ) {
        return $update;
    }

    return array(
        'slug'    => 'scheduled-content-dashboard-pro',
        'version' => $release['version'],
        'url'     => $release['url'],
        'package' => $release['package'],
    );
}
add_filter( 'update_plugins_github.com', 'scdp_check_update', 10, 3 );

function scdp_plugin_info( $result, $action, $args ) {
    if ( 'plugin_information' !== $action || 'scheduled-content-dashboard-pro' !== ( $args->slug ?? '' ) ) {
        return $result;
    }

    $release = scdp_latest_release();
    if ( ! $release ) {
        return $result;
    }

    return (object) array(
        'name'          => 'Scheduled Content Dashboard Pro',
        'slug'          => 'scheduled-content-dashboard-pro',
        'version'       => $release['version'],
        'author'        => '<a href="https://jeangalea.com/">Jean Galea</a>',
        'homepage'      => 'https://github.com/' . SCDP_REPO,
        'download_link' => $release['package'],
        'requires'      => '5.0',
        'requires_php'  => '7.4',
        'sections'      => array( 'changelog' => wp_kses_post( wpautop( $release['notes'] ) ) ),
    );
}
add_filter( 'plugins_api', 'scdp_plugin_info', 10, 3 );

function scdp_clear_release_cache( $upgrader, $options ) {
    if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) ) {
        delete_site_transient( 'scdp_release' );
    }
}
add_action( 'upgrader_process_complete', 'scdp_clear_release_cache', 10, 2 );

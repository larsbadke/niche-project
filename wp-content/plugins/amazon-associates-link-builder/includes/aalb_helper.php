<?php

/*
Copyright 2016-2017 Amazon.com, Inc. or its affiliates. All Rights Reserved.

Licensed under the GNU General Public License as published by the Free Software Foundation,
Version 2.0 (the "License"). You may not use this file except in compliance with the License.
A copy of the License is located in the "license" file accompanying this file.

This file is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND,
either express or implied. See the License for the specific language governing permissions
and limitations under the License.
*/

/**
 * Hepler class for commonly used functions in the plugin.
 *
 * @since      1.0.0
 * @package    AmazonAssociatesLinkBuilder
 * @subpackage AmazonAssociatesLinkBuilder/includes
 */
class Aalb_Helper {

    /**
     * Build key for storing rendered template in cache.
     *
     * @since 1.0.0
     *
     * @param string $asins List of hyphen separated asins.
     * @param string $marketplace Marketplace of the asin to look into.
     * @param string $store The identifier of the store to be used for current adunit
     * @param string $template Template to render the display unit.
     *
     * @return string Template cache key.
     */
    public function build_template_cache_key( $asins, $marketplace, $store, $template ) {
        return 'aalb' . '-' . $asins . '-' . $marketplace . '-' . $store . '-' . $template;
    }

    /**
     * Build key for storing product information in cache.
     *
     * @since 1.0.0
     *
     * @param string $asins List of hyphen separated asins.
     * @param string $marketplace Marketplace of the asin to look into.
     * @param string $store The identifier of the store to be used for current adunit
     *
     * @return string Products information cache key.
     */
    public function build_products_cache_key( $asins, $marketplace, $store ) {
        return 'aalb' . '-' . $asins . '-' . $marketplace . '-' . $store;
    }

    /**
     * Clears the cache for the given template name
     *
     * @since 1.0.0
     *
     * @param string $template The template to clear the cache for
     */
    public function clear_cache_for_template( $template ) {
        $this->clear_cache_for_substring( $template );
    }

    /**
     * Clear the cache for keys which contain the given substring
     *
     * @since 1.0.0
     *
     * @param string $substring The substring which is a part of the keys to be cleared
     */
    public function clear_cache_for_substring( $substring ) {
        global $wpdb;
        $table_prefix = $wpdb->prefix;
        $statement = 'DELETE from ' . $table_prefix . 'options
        WHERE option_name like %s or option_name like %s';
        $transient_timeout_cache = '_transient_timeout_aalb%' . $substring . '%';
        $transient_cache = '_transient_aalb%' . $substring . '%';
        $prepared_statement = $wpdb->prepare( $statement, $transient_timeout_cache, $transient_cache );

        try {
            $wpdb->query( $prepared_statement );
        } catch ( Exception $e ) {
            error_log( 'Unable to clear cache. Query to clear cache for substring ' . $substring . ' failed with the Exception ' . $e->getMessage() );
        }
    }

    /**
     * Clear the dead expired transients from cache at intervals
     *
     * @since 1.0.0
     */
    public function clear_expired_transients_at_intervals() {
        $randomNumber = rand( 1, 50 );
        // Clear the expired transients approximately once in 50 requests.
        if ( $randomNumber == 25 ) {
            $this->clear_expired_transients();
        }
    }

    /**
     * Clear the dead expired transients from cache
     *
     * @since 1.0.0
     */
    public function clear_expired_transients() {
        global $wpdb;
        $table_prefix = $wpdb->prefix;
        $transients_prefix = esc_sql( "_transient_timeout_aalb%" );
        $sql = $wpdb->prepare( '
        SELECT option_name
        FROM ' . $table_prefix . 'options
        WHERE option_name LIKE %s
      ', $transients_prefix );
        $transients = $wpdb->get_col( $sql );
        foreach ( $transients as $transient ) {
            // Strip away the WordPress prefix in order to arrive at the transient key.
            $key = str_replace( '_transient_timeout_', '', $transient );
            delete_transient( $key );
        }
        wp_cache_flush();
    }

    /**
     * Displays error messages in preview mode only
     *
     * @since 1.0.0
     *
     * @param string $error_message Error message to be displayed
     */
    public function show_error_in_preview( $error_message ) {
        if ( is_preview() ) {
            //If it's preview mode
            echo "<br><font color='red'><b>" . $error_message . "</b></font>";
        }
    }

    /**
     * Returns the Store IDs Array.
     * Returns AALB_DEFAULT_STORE_ID_NAME if the nothing is specified.
     *
     * @since 1.0.0
     */
    public function get_store_ids_array() {
        return explode( "\r\n", strlen( get_option( AALB_STORE_ID_NAMES ) ) ? get_option( AALB_STORE_ID_NAMES ) : AALB_DEFAULT_STORE_ID_NAME );
    }

    /**
     * Fetches the Wordpress version number
     *
     * @since 1.0.0
     * @return string Version number of Wordpress
     */
    function get_wordpress_version() {
        global $wp_version;
        return $wp_version;
    }

    /**
     * Gets the template uploads dir URL.
     *
     * @since 1.3.2
     * @return full URL of the template uploads directory
     */
    public function get_template_upload_directory_url() {
        $upload_dir = wp_upload_dir();

        return $upload_dir['baseurl'] . '/' . AALB_TEMPLATE_UPLOADS_FOLDER;
    }

    /**
     * Reads both the templates/ and the uploads/ directory and updates the template list.
     * Helper to replicate the current status of the default and custom templates
     *
     * @since 1.3
     */
    public function refresh_template_list() {
        global $wp_filesystem;
        $this->aalb_initialize_wp_filesystem_api();

        $aalb_templates = array();
        $upload_dir = $this->get_template_upload_directory();

        //Read and update templates from the plugin's template/ directory (Default Templates)
        if ( $handle = opendir( AALB_TEMPLATE_DIR ) ) {
            while ( false !== ( $entry = readdir( $handle ) ) ) {
                $file_name = $this->aalb_get_file_name( $entry );
                $file_extension = $this->aalb_get_file_extension( $entry );
                if ( $file_extension == "css" and file_exists( AALB_TEMPLATE_DIR . $file_name . '.mustache' ) ) {
                    $aalb_templates[] = $file_name;
                }
            }
            closedir( $handle );
        }

        //Read and update templates from the uploads/ directory (Custom Templates)
        if ( $handle = opendir( $upload_dir ) ) {
            while ( false !== ( $entry = readdir( $handle ) ) ) {
                $file_name = $this->aalb_get_file_name( $entry );
                $file_extension = $this->aalb_get_file_extension( $entry );
                if ( $file_extension == "css" and file_exists( $upload_dir . $file_name . '.mustache' ) ) {
                    $aalb_templates[] = $file_name;
                }
            }
        }
        update_option( AALB_TEMPLATE_NAMES, $aalb_templates );
    }

    /**
     * Loads necessary files and initializes WP Filesystem API
     *
     * @since 1.3
     */
    public function aalb_initialize_wp_filesystem_api() {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        WP_Filesystem();
    }

    /**
     * Fetches the Uploads Directory where custom templates are stored.
     * If the dir doesn't exists, it is created and returned.
     *
     * @since 1.3
     * @return Full directory path of the template uploads directory
     */
    public function get_template_upload_directory() {
        global $wp_filesystem;
        $this->aalb_initialize_wp_filesystem_api();
        $aalb_template_upload_dir = $this->get_template_upload_directory_name( $wp_filesystem );
        if ( ! $wp_filesystem->is_dir( $aalb_template_upload_dir ) ) {
            if ( ! $this->create_template_upload_dir( $aalb_template_upload_dir ) ) {
                return false;
            }
        }

        return $aalb_template_upload_dir;
    }

    /**
     * Gets the template uploads dir name.
     *
     * @since 1.3
     * @return full path of the template uploads directory
     */
    public function get_template_upload_directory_name() {
        global $wp_filesystem;
        $upload_dir = wp_upload_dir();

        return $wp_filesystem->find_folder( $upload_dir['basedir'] ) . AALB_TEMPLATE_UPLOADS_FOLDER;
    }

    /**
     * Creates the Uploads Directory where custom templates are stored
     *
     * @since 1.3
     * @return TRUE on successful creation of the dir; FALSE otherwise
     */
    public function create_template_upload_dir( $aalb_template_upload_dir ) {
        global $wp_filesystem;
        if ( ! wp_mkdir_p( $aalb_template_upload_dir ) ) {
            error_log( "Error Creating Dir . " . $aalb_template_upload_dir . ". Please set the folder permissions correctly." );

            return false;
        }

        return true;
    }

    /**
     * Gets the name of the file without the extension
     *
     * @since 1.0
     *
     * @param string $file_name Name of the file
     *
     * @return string  Name of the file without the extension
     */
    function aalb_get_file_name( $file_name ) {
        return substr( $file_name, 0, strlen( $file_name ) - strlen( strrchr( $file_name, '.' ) ) );
    }

    /**
     * Gets the extension of the file
     *
     * @since 1.0
     *
     * @param string $file_name Name of the file
     *
     * @return string  Extension of the file
     */
    public function aalb_get_file_extension( $file_name ) {
        return substr( strrchr( $file_name, '.' ), 1 );
    }
}

?>

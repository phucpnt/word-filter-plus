<?php

/*
  Plugin Name: Word Filter Plus
  Plugin URI: http://wordpress.org/extend/plugins/word-filter-plus/
  Description: Filter or replace words or phrases in posts, pages, excerpts, titles and/or comments.
  Author: Brian C. Layman
  Version: 1.0.1
  Author URI: http://TheCodeCave.com

 */

/*  Copyright 2012 eHermits, Inc.

  This plugin includes code & inspiration from many GNU projects including:
  The core replacement code was based upon the "Word Replacer" plugin by Takien.
  The CVS Export includes some code from Otto's member export plugin.
  The Donate button was ripped from Mark Jaquith's I Make Plugins plugin.
  This article helped me create the tabbed settings page http://bit.ly/Ao1snB
  Other code and knowledge was gathered from php.net.

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  ( at your option ) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  For a copy of the GNU General Public License, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once(dirname(__FILE__) . '/pwp-filter-plus.php');
if (!defined('ABSPATH'))
    die();
define('WFP_DEBUG', false);

if (!class_exists('WordFilter')) {

    class WordFilter {

        /**
         * @var object The settings object
         */
        var $settings = '';

        /**
         * @var string The name of the plugin
         */
        var $plugin_name = 'Word Filter Plus';

        /**
         * @var string The version of the plugin
         */
        var $plugin_version = '1.0.1';

        /**
         * @var string The name of the table this plugin uses
         */
        var $plugin_table_name = 'wp_word_filter_plus';

        /**
         * @var string The slug this plugin uses
         */
        var $plugin_slug = 'wordfilterplus';

        /**
         * @var array The list of the current fields in the database
         */
        var $field_names = array('original', 'replacement', 'in_posts', 'in_comments', 'in_pages', 'in_titles', 'case_insensitive', 'partial_match', 'use_regex');

        function word_filter_plus() {
            $this->__construct();
        }

        function __construct() {
            global $wpdb;

            /**
             * constants Defining the modes in which the plugin can operate.
             */
            define("WFP_MODE_OFF", 0);
            define("WFP_MODE_PASSIVE", 1);
            define("WFP_MODE_ACTIVE", 2);

            define("WFP_OPTION_BATCH_SCHEDULE", 'wfp_batch_schedule');
            define("WFP_OPTION_MODE", 'wfp_mode');
            define("WFP_OPTION_VER", 'wfp_version');
            define("WFP_OPTION_BATCH_SIZE", 'wfp_batch_size');
            define("WFP_OPTION_BATCH_SLEEP", 'wfp_batch_sleep');
            define("WFP_OPTION_ITERATION", 'wfp_iteration');

            define("DEFAULT_BATCH_SIZE", 1000);
            define("DEFAULT_BATCH_SLEEP", 10);

            define("WFP_SCHEDULE_FIVE", 'five-minute');
            define("WFP_SCHEDULE_FIFTEEN", 'quarter-hour');
            define("WFP_SCHEDULE_THIRTY", 'half-hour');
            define("WFP_SCHEDULE_SIXTY", 'hourly');

            define("WFP_POST_EVENT", 'wfpPostEvent'); // Note that there is a function which is named from this value. Update the value and you must update the function name.
            define("WFP_COMMENT_EVENT", 'wfpCommentEvent'); // Note that there is a function which is named from this value. Update the value and you must update the function name.
            // Hook into that action that'll fire if the cron is scheduled
            add_action(WFP_POST_EVENT, array($this, 'process_' . WFP_POST_EVENT));
            add_action(WFP_COMMENT_EVENT, array($this, 'process_' . WFP_COMMENT_EVENT));

            $this->plugin_table_name = $wpdb->prefix . "word_filter_plus";

            register_activation_hook(__FILE__, array($this, 'installed_version'));
            $plugin = plugin_basename(__FILE__);
            add_filter("plugin_action_links_$plugin", array($this, 'settings_link'));

            add_filter('cron_schedules', array($this, 'add_cron_schedule'));

            $mode = $this->valid_modes(get_option(WFP_OPTION_MODE));

            if ($mode == WFP_MODE_PASSIVE) {
                // Upon Display Settings
                add_filter('comment_text', array($this, 'filter_comment'), 200);
                add_filter('the_content', array($this, 'filter_content'), 200);
                add_filter('the_title', array($this, 'filter_title'), 200);
                add_filter('wp_title', array($this, 'filter_title'), 200);
            } elseif ($mode == WFP_MODE_ACTIVE) {
                // Upon Save Settings
                add_filter('content_save_pre', array($this, 'filter_content'), 200);
                add_filter('excerpt_save_pre', array($this, 'filter_content'), 200);
                add_filter('comment_save_pre', array($this, 'filter_comment'), 200);
                add_filter('title_save_pre', array($this, 'filter_title'), 200);
            }

            if (is_admin()) {
                require_once( WP_PLUGIN_DIR . "/" . basename(dirname(__FILE__)) . '/wfp-settings.php' );
                $this->settings = new wfp_settings($this);
            }
        }

        public function replacement_list() {
            global $wpdb;
            if (!$word_filter_plus_replacements = get_transient('word_filter_plus_replacements')) {
                /* It wasn't there, so regenerate the data and save the transient */
                $word_filter_plus_replacements = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $this->plugin_table_name . " ORDER BY id"), ARRAY_A);
                set_transient('word_filter_plus_replacements', $word_filter_plus_replacements);
            }
            return $word_filter_plus_replacements;
        }

// end replacement_list

        public function _specialchar($string) {
            return htmlspecialchars(stripcslashes($string));
        }

// end _specialchar

        public function valid_modes($input) {
            if (!( in_array($input, array(WFP_MODE_OFF, WFP_MODE_PASSIVE, WFP_MODE_ACTIVE)) ))
                return WFP_MODE_OFF;
            return $input;
        }

// end valid_modes

        public function valid_schedules($input) {
            if (!( in_array($input, array(WFP_SCHEDULE_FIVE, WFP_SCHEDULE_FIFTEEN, WFP_SCHEDULE_THIRTY, WFP_SCHEDULE_SIXTY)) ))
                return WFP_SCHEDULE_SIXTY;
            return $input;
        }

// end valid_schedules

        function installed_version() {
            global $wpdb;
            $sql = "CREATE TABLE " . $this->plugin_table_name . " ( 
			  id mediumint( 9 ) NOT NULL AUTO_INCREMENT, 
			  original TEXT NOT NULL, 
			  replacement TEXT NOT NULL, 
			  in_posts VARCHAR( 5 ) NOT NULL, 
			  in_comments VARCHAR( 5 ) NOT NULL, 
			  in_pages VARCHAR( 5 ) NOT NULL, 
			  in_titles VARCHAR( 5 ) NOT NULL, 
			  case_insensitive VARCHAR( 5 ) NOT NULL, 
			  partial_match VARCHAR( 5 ) NOT NULL, 
			  use_regex VARCHAR( 5 ) NOT NULL, 
			  UNIQUE KEY id ( id )
			 );";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

            if ($wpdb->get_var($wpdb->prepare('show tables like "' . $this->plugin_table_name . '"')) !== $this->plugin_table_name) {
                dbDelta($sql);
                add_option(WFP_OPTION_VER, $this->plugin_version);
            } elseif (get_option(WFP_OPTION_VER) !== $this->plugin_version) {
                dbDelta($sql);
                update_option(WFP_OPTION_VER, $this->plugin_version);
            }
        }

// end installed_version

        private function do_replacement($original = '', $replacement = '', $case_insensitive = '', $partial_match = '', $use_regex = '', $string = '') {
            $b = '';
            $i = '';

            if ($partial_match != 'yes')
                $b = '\b';
            if ($case_insensitive == 'yes')
                $i = 'i';

            $original = ( $use_regex == 'yes' ) ? $original : preg_quote($original, '/');

            $result = PWP_FilterPlus::getInstance()->doReplacement($original, $replacement, $string, $b, $i, $this->valid_modes(get_option(WFP_OPTION_MODE)) );

            if (false === $result) {
                return preg_replace("/$b$original$b/$i", $replacement, $string);
            } else {
                return $result;
            }
        }

// end do_replacement

        function filter_content($content, $force = false) {
            $i = 1;
            foreach ($this->replacement_list() as $aReplacement) {
                $i++;
                $original         = base64_decode($aReplacement['original'], true) ? base64_decode($aReplacement['original']) : $aReplacement['original'];
                $replacement      = htmlspecialchars_decode($this->_specialchar($aReplacement['replacement']));
                $in_posts         = $aReplacement['in_posts'];
                $in_pages         = $aReplacement['in_pages'];
                $case_insensitive = $aReplacement['case_insensitive'];
                $partial_match    = $aReplacement['partial_match'];
                $use_regex        = $aReplacement['use_regex'];

                if (( is_page() && ( $in_pages == 'yes' ) ) || (!is_page() && ( $in_posts == 'yes' ) || $force )) {
                    $content = $this->do_replacement($original, $replacement, $case_insensitive, $partial_match, $use_regex, $content);
                }
            }
            return $content;
        }

// end filter_content 

        function filter_comment($content) {
            $i = 1;
            foreach ($this->replacement_list() as $aReplacement) {
                $i++;
                if ($aReplacement['in_comments'] == 'yes') {
                    $original         = base64_decode($aReplacement['original'], true) ? base64_decode($aReplacement['original']) : $aReplacement['original'];
                    $replacement      = stripslashes($aReplacement['replacement']);
                    $case_insensitive = $aReplacement['case_insensitive'];
                    $partial_match    = $aReplacement['partial_match'];
                    $use_regex        = $aReplacement['use_regex'];

                    $content = $this->do_replacement($original, $replacement, $case_insensitive, $partial_match, $use_regex, $content);
                }
            }
            return $content;
        }

// end filter_comment

        function filter_title($content, $force = false) {
            $i = 1;
            foreach ($this->replacement_list() as $aReplacement) {
                $i++;
                if ($aReplacement['in_titles'] == 'yes') {
                    $original         = base64_decode($aReplacement['original'], true) ? base64_decode($aReplacement['original']) : $aReplacement['original'];
                    $replacement      = stripslashes($aReplacement['replacement']);
                    $case_insensitive = $aReplacement['case_insensitive'];
                    $partial_match    = $aReplacement['partial_match'];
                    $use_regex        = $aReplacement['use_regex'];

                    $content = $this->do_replacement($original, $replacement, $case_insensitive, $partial_match, $use_regex, $content);
                }
            }
            return $content;
        }

// end filter_title

        function settings_link($links) {
            $settings_link = '<a href="tools.php?page=' . $this->plugin_slug . '">Settings</a>';
            array_unshift($links, $settings_link);
            return $links;
        }

// end settings_link

        function schedule_batch($event) {
            $scheduledInterval = $this->valid_schedules(get_option(WFP_OPTION_BATCH_SCHEDULE));
            $scheduled_for     = time();
            if (!wp_next_scheduled($event)) {
                wp_schedule_event($scheduled_for, $scheduledInterval, $event);
            } else {
                if (WFP_DEBUG)
                    error_log("ALREADY scheduled:$scheduledInterval, $event");
            }
            if ($next_event = wp_next_scheduled($event)) {
                if (WFP_DEBUG)
                    error_log("Now scheduled:  " . date('h:i:s', $scheduled_for) . ", " . date('h:i:s', $next_event) . ", $event");
            } else {
                if (WFP_DEBUG)
                    error_log("Still NOT scheduled: $scheduledInterval, $event");
            }
        }

// end schedule_batch

        function cancel_batch($event) {
            global $wpdb;
            if (WFP_DEBUG)
                error_log("Now canceled:  $event");
            if (wp_next_scheduled($event)) {
                wp_clear_scheduled_hook($event);
                if (get_transient('batch_running')) {
                    if (WFP_DEBUG)
                        error_log("Adding cancel Record");
                    $wpdb->query($wpdb->prepare("insert into $wpdb->options (option_name, option_value, autoload) values ('wfp_cancel','1','no');"));
                }
                delete_transient('batch_running');
                delete_option(WFP_OPTION_ITERATION);
            }
        }

// end cancel_batch

        function add_cron_schedule($schedules) {
            $schedules[WFP_SCHEDULE_FIVE] = array(
                'interval' => 300, // 5 minutes in seconds
                'display'  => __('Every 5 minutes'),
            );

            $schedules[WFP_SCHEDULE_FIFTEEN] = array(
                'interval' => 900, // 15 minutes in seconds
                'display'  => __('Quarter Hourly'),
            );

            $schedules[WFP_SCHEDULE_THIRTY] = array(
                'interval' => 1800, // 30 minutes in seconds
                'display'  => __('Quarter Hourly'),
            );

            return $schedules;
        }

// end add_cron_schedule

        function process_wfpPostEvent() {
            if (get_transient('batch_running')) {
                if (WFP_DEBUG)
                    error_log("Batch already running. Exiting");
                exit;
            }
            set_transient('batch_running', true);
            global $wpdb;

            if (WFP_DEBUG)
                error_log("!!!!!!!!!!!!!!IN process_wfpPostEvent");
            $wfp_batch_size  = intval(get_option(WFP_OPTION_BATCH_SIZE, DEFAULT_BATCH_SIZE));
            $wfp_batch_sleep = intval(get_option(WFP_OPTION_BATCH_SLEEP, DEFAULT_BATCH_SLEEP));
            $iteration       = intval(get_option(WFP_OPTION_ITERATION, 0));
            $wpdb_outerloop  = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

            $sql    = $wpdb_outerloop->prepare('SELECT ID, post_content, post_excerpt, post_title FROM ' . $wpdb->posts . ' limit %d, %d', $iteration, $wfp_batch_size);
            $result = mysql_query($sql, $wpdb_outerloop->dbh); // Note we are using mysql_query here to allow large batches and less memory consumption.
            if (WFP_DEBUG)
                error_log("sql: $sql");

            $record_count = mysql_num_rows($result);
            if (!$result || ( $record_count === 0 )) {
                // Exit out with no errors.  End batch.
                if (WFP_DEBUG)
                    error_log("Exiting. No Results");
                wp_clear_scheduled_hook(WFP_POST_EVENT);
                delete_option(WFP_OPTION_ITERATION);
                delete_transient('batch_running');
                exit;
            }

            while ($row = mysql_fetch_assoc($result)) {
                set_time_limit(30); // No one query can take more than 30 seconds, but the script can run for longer.
                $sql       = $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->options where option_name = 'wfp_cancel';");
                $cancelled = $wpdb->get_var($sql);
                if ($cancelled > 0) {
                    if (WFP_DEBUG)
                        error_log("Batch cancelled. Exiting: $cancelled || $sql");
                    $wpdb->query($wpdb->prepare("delete FROM $wpdb->options where option_name = 'wfp_cancel';"));
                    wp_clear_scheduled_hook(WFP_POST_EVENT);
                    delete_option(WFP_OPTION_ITERATION);
                    delete_transient('batch_running');
                    exit;
                } else {
                    // abort near end of run
                    $nts = wp_next_scheduled(WFP_POST_EVENT);
                    if ($nts < time()) {
                        if (WFP_DEBUG)
                            error_log("Exceeded time limit. Exiting:  " . date('h:i:s', time()) . ", " . date('h:i:s', $nts));
                        delete_transient('batch_running');
                        exit;
                    }
                }
                $cur_id = $row['ID'];
                if (WFP_DEBUG)
                    error_log("Processing $cur_id: ");

                if ($row['post_content'] > '') {
                    $new_post_content = $this->filter_content($row['post_content'], TRUE);
                } else {
                    $new_post_content = $row['post_content'];
                }

                if ($row['post_excerpt'] > '') {
                    $new_post_excerpt = $this->filter_content($row['post_excerpt'], TRUE);
                } else {
                    $new_post_excerpt = $row['post_excerpt'];
                }

                /*
                  // This section contains the original & safest method used to update post content.
                  // In testing it took 7 seconds to update a single post compared to milliseconds with a direct update query.
                  // If there are any compatiblity issues, this original method could be reinstated.
                  // There could even be a "Compatiblity Mode" option to enable this method.

                  $cur_post = get_post( $cur_id, ARRAY_A );
                  $cur_post[ 'post_content' ] = $this->filter_content( $cur_post[ 'post_content' ], TRUE );
                  $cur_post[ 'post_excerpt' ] = $this->filter_content( $cur_post[ 'post_excerpt' ], TRUE );
                  $cur_post[ 'post_title' ] = $this->filter_title( $cur_post[ 'post_title' ] );

                  // Update the post into the database
                  wp_update_post( $cur_post );
                 */

                // The next two if statements comprise the new method
                if ($row['post_title'] > '') {
                    $new_post_title = $this->filter_title($row['post_title']);
                } else {
                    $new_post_title = $row['post_title'];
                }

                if (( $new_post_content <> $row['post_content'] ) || ( $new_post_excerpt <> $row['post_excerpt'] ) || ( $new_post_title <> $row['post_title'] )) {
                    if (WFP_DEBUG)
                        error_log("Update Required for $cur_id: ");
                    $update_sql = $wpdb->prepare("update $wpdb->posts set `post_content` = '%s', `post_excerpt` = '%s', `post_title` = '%s' where ID = %s;", $new_post_content, $new_post_excerpt, $new_post_title, $cur_id);
                    $wpdb->query($update_sql);
                }
                $iteration++;
                update_option(WFP_OPTION_ITERATION, intval($iteration));
                usleep($wfp_batch_sleep * 1000);
            }
            if ($record_count < $wfp_batch_size) {
                // Exit out with no errors. We're done. End batch.
                if (WFP_DEBUG)
                    error_log("Ending Scheduled Batch. We're done.");
                wp_clear_scheduled_hook(WFP_POST_EVENT);
                delete_option(WFP_OPTION_ITERATION);
            }
            if (WFP_DEBUG)
                error_log("Batch Done. Exiting");
            delete_transient('batch_running');
        }

// end process_wfpPostEvent

        function process_wfpCommentEvent() {
            if (get_transient('batch_running')) {
                if (WFP_DEBUG)
                    error_log("Batch already running. Exiting");
                exit;
            }
            set_transient('batch_running', true);
            global $wpdb;

            if (WFP_DEBUG)
                error_log("!!!!!!!!!!!!!!IN process_wfpPostEvent");
            $wfp_batch_size  = intval(get_option(WFP_OPTION_BATCH_SIZE, DEFAULT_BATCH_SIZE));
            $wfp_batch_sleep = intval(get_option(WFP_OPTION_BATCH_SLEEP, DEFAULT_BATCH_SLEEP));
            $iteration       = intval(get_option(WFP_OPTION_ITERATION, 0));
            $wpdb_outerloop  = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);

            $sql    = $wpdb_outerloop->prepare('SELECT comment_ID, comment_content FROM ' . $wpdb->comments . ' limit %d, %d', $iteration, $wfp_batch_size);
            $result = mysql_query($sql, $wpdb_outerloop->dbh); // Note we are using mysql_query here to allow large batches and less memory consumption.
            if (WFP_DEBUG)
                error_log("sql: $sql");

            $record_count = mysql_num_rows($result);
            if (!$result || ( $record_count === 0 )) {
                // Exit out with no errors.  End batch.
                if (WFP_DEBUG)
                    error_log("Exiting. No Results");
                wp_clear_scheduled_hook(WFP_COMMENT_EVENT);
                delete_option(WFP_OPTION_ITERATION);
                delete_transient('batch_running');
                exit;
            }

            while ($row = mysql_fetch_assoc($result)) {
                set_time_limit(30); // No one query can take more than 30 seconds, but the script can run for longer.
                $sql       = $wpdb->prepare("SELECT COUNT(*) FROM $wpdb->options where option_name = 'wfp_cancel';");
                $cancelled = $wpdb->get_var($sql);
                if ($cancelled > 0) {
                    if (WFP_DEBUG)
                        error_log("Batch cancelled. Exiting: $cancelled || $sql");
                    $wpdb->query($wpdb->prepare("delete FROM $wpdb->options where option_name = 'wfp_cancel';"));
                    wp_clear_scheduled_hook(WFP_COMMENT_EVENT);
                    delete_option(WFP_OPTION_ITERATION);
                    delete_transient('batch_running');
                    exit;
                } else {
                    // abort near end of run
                    $nts = wp_next_scheduled(WFP_COMMENT_EVENT);
                    if ($nts < time()) {
                        if (WFP_DEBUG)
                            error_log("Exceeded time limit. Exiting:  " . date('h:i:s', time()) . ", " . date('h:i:s', $nts));
                        delete_transient('batch_running');
                        exit;
                    }
                }
                $cur_id = $row['comment_ID'];
                if (WFP_DEBUG)
                    error_log("Processing $cur_id: ");

                /*
                  // This section contains the original & safest method used to update post content.
                  // In testing it took seconds to update a single comment compared to milliseconds with a direct update query.
                  // If there are any compatiblity issues, this original method could be reinstated.
                  // There could even be a "Compatiblity Mode" option to enable this method.

                  $cur_post = get_comment( $cur_id, ARRAY_A );
                  $cur_post[ 'comment_content' ] = $this->filter_content( $cur_post[ 'comment_content' ], TRUE );

                  // Update the post into the database
                  wp_update_comment( $cur_comment );
                 */

                // The next two if statements comprise the new method
                if ($row['comment_content'] > '') {
                    $new_comment_content = $this->filter_comment($row['comment_content']);
                } else {
                    $new_comment_content = $row['comment_content'];
                }

                if (( $new_comment_content <> $row['comment_content'])) {
                    if (WFP_DEBUG)
                        error_log("Update Required for comment: $cur_id: ");
                    $update_sql = $wpdb->prepare("update $wpdb->comments set `comment_content` = '%s' where comment_ID = %s;", $new_comment_content, $cur_id);
                    $wpdb->query($update_sql);
                }

                $iteration++;
                update_option(WFP_OPTION_ITERATION, intval($iteration));
                usleep($wfp_batch_sleep * 1000);
            }

            if ($record_count < $wfp_batch_size) {
                // Exit out with no errors. We're done. End batch.
                if (WFP_DEBUG)
                    error_log("Ending Scheduled Batch. We're done.");
                wp_clear_scheduled_hook(WFP_COMMENT_EVENT);
                delete_option(WFP_OPTION_ITERATION);
            }
            if (WFP_DEBUG)
                error_log("Batch Done. Exiting");
            delete_transient('batch_running');
        }

// end process_wfpCommentEvent
    }

    // class WordFilter
} // end if !class_exists

if (class_exists('WordFilter')) {
    $WordFilter = new WordFilter();
} // end if class_exists
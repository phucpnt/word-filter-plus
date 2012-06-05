<?php
/**
 * This file displays the options page and tabs for the Word Filter Plus plugin
 *
 * @package word-filter-plus
 * 
 * Copyright 2012 - eHermits, Inc. LTD - GNU 2
 * 
 */
if (!class_exists('wfp_settings')) {

    class wfp_settings {

        /**
         * @var object The creator of this plugin
         */
        var $parent = null;

        /**
         * @var object An object used to import or export the replacment list
         */
        var $csvManip = null;

        /**
         * @var string The link to the current tab
         */
        var $curLink = '';

        function wfp_settings($owner) {
            $this->__construct($owner);
        }

// end wfp_settings

        function __construct($owner) {
            $this->parent = $owner;


            define("WFP_PLUGIN_TAB1_SLUG", 'wfp-section-options');
            define("WFP_TAB1_SLUG", 'options');
            define("WFP_TAB1_LABEL", 'Options');

            define("WFP_PLUGIN_TAB2_SLUG", 'wfp-section-replacements');
            define("WFP_TAB2_SLUG", 'replacements');
            define("WFP_TAB2_LABEL", 'Replacements');

            define("WFP_PLUGIN_TAB3_SLUG", 'wfp-section-batch');
            define("WFP_TAB3_SLUG", 'batch');
            define("WFP_TAB3_LABEL", 'Batch');

            define("WFP_PLUGIN_TAB4_SLUG", 'wfp-section-csv');
            define("WFP_TAB4_SLUG", 'csv');
            define("WFP_TAB4_LABEL", 'Import/Export');

            define("WFP_START_POST_NONCE", 'start-post-batch');
            define("WFP_START_COMMENT_NONCE", 'start-comment-batch');
            define("WFP_CANCEL_POST_NONCE", 'cancel-post-batch');
            define("WFP_CANCEL_COMMENT_NONCE", 'cancel-comment-batch');

            add_action('admin_menu', array($this, 'settings_page_init'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_init', array($this, 'process_post_tab4')); // Must be be done before any header info is sent
            add_action('admin_head', array($this, 'script'));
            add_action('contextual_help', array($this, 'help_tab2'));

            require_once( WP_PLUGIN_DIR . "/" . basename(dirname(__FILE__)) . '/csv-manip.php' );
            $this->csvManip = new csv_manip($this);
        }

// end __construct

        function script() {
            if ($_GET['page'] == $this->parent->plugin_slug) {
                ?>
                <script type="text/javascript">
                    //<![CDATA[
                    jQuery(document).ready(function($){
                        $('#add_more_field').click(function(){
                            var count = $("input[name='count']").length;
                            $('#word-replacer-list').append("<tr><td><input type='checkbox' name='delete["+count+"]' value='1' /></td><td><input type='hidden' name='id["+count+"]' value='' /><input type='hidden' name='count' value='' /><input style='width:100%' name='original["+count+"]' value='' type='text' /></td>\n<td> &raquo; </td>\n<td><textarea style='resize:vertical;width:100%' name='replacer["+count+"]'></textarea>			</td>\n<td class='replacer_expandable'><input value='yes' name='in_posts["+count+"]' type='checkbox' /></td>\n<td class='replacer_expandable'><input value='yes' name='in_comments["+count+"]' type='checkbox' /></td>\n<td class='replacer_expandable'><input value='yes' name='in_pages["+count+"]' type='checkbox' /></td>\n<td class='replacer_expandable'><input value='yes' name='in_titles["+count+"]' type='checkbox' /></td>\n<td class='replacer_expandable'><input value='yes' name='case_insensitive["+count+"]' type='checkbox' /></td>\n<td class='replacer_expandable'><input value='yes' name='partial_match["+count+"]' type='checkbox' /></td>\n<td class='replacer_expandable'><input value='yes' name='use_regex["+count+"]' type='checkbox' /></td><td></td>\n</tr>\n");
                            cektkp_growtextarea($('#word-replacer-list textarea'));
                            return false;
                        });
                        $('#show_hide_help').click(function(){
                            $('#contextual-help-link').trigger('click');
                            return false;
                        });
                        $('.replacer_expandall a').click(function(){
                            $('.replacer_expandable').toggle();
                            return false;
                        });

                        function cektkp_growtextarea(textarea){
                            textarea.each(function(index){
                                textarea = $(this);
                                textarea.css({'overflow':'hidden', 'word-wrap':'break-word'});
                                var pos = textarea.position();
                                var growerid = 'textarea_grower_'+index;
                                textarea.after('<div style="position:absolute;z-index:-1000;visibility:hidden;top:'+pos.top+';height:'+textarea.outerHeight()+'" id="'+growerid+'"></div>');
                                var growerdiv = $('#'+growerid);
                                growerdiv.css({'min-height':'20px', 'font-size':textarea.css('font-size'), 'width':textarea.width(), 'word-wrap':'break-word'});
                                growerdiv.html($('<div/>').text(textarea.val()).html().replace(/\n/g, "<br />."));
                                if(textarea.val() == ''){
                                    growerdiv.text('.');
                                }
                					
                                textarea.height(growerdiv.height()+10);
                							
                                textarea.keyup(function(){
                                    growerdiv.html($('<div/>').text($(this).val()).html().replace(/\n/g, "<br />."));
                                    if($(this).val() == ''){
                                        growerdiv.text('.');
                                    }
                                    $(this).height(growerdiv.height()+10);
                                });
                            });
                        }
                        cektkp_growtextarea($('#word-replacer-list textarea'));
                    });
                    //]]>
                </script>
                <?php
            }
        }

// end script

        function help_tab2($help) {
            if (( $_GET['page'] == $this->parent->plugin_slug ) && ( isset($_GET['tab']) && ( $_GET['tab'] === WFP_TAB2_SLUG ) )) {
                $help = '
						<h2>' . $this->parent->plugin_name . ' ' . $this->parent->plugin_version . '</h2>
						<h5>Instruction:</h5>
						<ol>
						<li>To <strong>Add a New Word</strong> click "Add More Fields" and a new group of fields will appear. Simply type your word, the replacement and choose filter, then press the "' . esc_attr('Save Changes') . '" button.</li>
						<li>To <strong>Update a Replacement</strong>, edit the values and then press the "' . esc_attr('Save Changes') . '" button.</li>
						<li>To <strong>Delete a Word</strong>, mark the "Delete" column or erase the value in the "Original" field and then press the "' . esc_attr('Save Changes') . '" button.</li></ol>
						<h5>Regex:</h5>
						<ol>
						<li>Do not use a delimiter ("/") in the begining and the end of each REGEX pattern. It will be added when it\'s processed.</li>
						<li>The REGEX options <em>i</em> will be added automatically if you check Case Insensitive on</li>
						</ol>
						<h5>Example:</h5>
						<ol>
							<li>BASIC: To replace word "<strong>foo</strong>" in a post with "bar", put "foo" in the original field, and "bar" in the replacement field, tick on the <em>Post</em> column and Save.</li>
							<li>BASIC REGEX: To replace words "ipsum, dolor, amet" become bold "<strong>ipsum, dolor, amet</strong>", put "( lorem|dolor|amet )" in the original field, and "&lt;strong&gt;$1&lt;/strong&gt;" in the replacement field, tick on the <em>Post</em>, <em>Insensitive</em>, and <em>Regex</em> column and Save/Update. This will replace sentence "Lorem ipsum dolor sit amet" become "<strong>Lorem</strong> ipsum <strong>dolor</strong> sit <strong>amet</strong>" in your posts.</li>
						</ol>
						<h5>Notes:</h5>
						<ol>
							<li>If you are making changes to your replacements, you might want to test your changes with the plugin in "Passive" mode. Then you will not have changed your post content in unexpected ways.</li>
							<li>Before running a batch change, or switching to "Active mode", after updating your replacements, you might want to make a backup of your database - just in case anything goes wrong.</li>
							<li>It is always better to NOT check the "Partial Match" box unless you are certain it has to be checked. Partial matches can surprise you.</li>
							<li>Replacement fields accept HTML tags, make sure you do not replace a <em>title</em> with an HTML tag.</li>
						</ol>
						
						<p>Further question, suggestion, comment, or help please <a href="http://thecodecave.com/word-filter-plus/" target="_blank">go here.</a></p>
						<p>Support is limited to plugins usage and feature only, for advanced info about RegEx and preg_replace please
						refers to the following resources:</p>
						<ol>
						<li>Regex info <a href="http://www.regular-expressions.info/" target="_blank">http://www.regular-expressions.info/</a></li>
						<li>preg_replace function <a href="http://www.php.net/manual/en/function.preg-replace.php" target="_blank">http://www.php.net/manual/en/function.preg-replace.php</a></li>
						</ol>
						';
            }
            return $help;
        }

// end help_tab2

        function help_tab4($help) {
            if (( $_GET['page'] == $this->parent->plugin_slug ) && ( isset($_GET['tab']) && ( $_GET['tab'] === WFP_TAB4_SLUG ) )) {
                $help = '
						<h2>Configuring ' . $this->parent->plugin_name . ' ' . $this->parent->plugin_version . ' Imports/Exports</h2>
						<h5>Introduction:</h5>					
						<p>Further question, suggestion, comment, or help please <a href="http://takien.com/587/word-replacer-wordpress-plugin.php" target="_blank">go here.</a></p>
						<p>Support is limited to plugins usage and feature only, for advanced info about RegEx and preg_replace please
						refers to the following resources:</p>
						<ol>
						<li>Regex info <a href="http://www.regular-expressions.info/" target="_blank">http://www.regular-expressions.info/</a></li>
						<li>preg_replace function <a href="http://www.php.net/manual/en/function.preg-replace.php" target="_blank">http://www.php.net/manual/en/function.preg-replace.php</a></li>
						</ol>
						';
            }
            return $help;
        }

// end help_tab4

        function save_replacement_data() {
            global $wpdb;
            $message = false;
            if (!wp_verify_nonce($_POST['wfp_settings_plus_nonce'], 'wfp_settings_plus_nonce_action')) {
                wp_die('Not allowed');
            }
            $id               = @$_POST['id'];
            $original         = @$_POST['original']; //do not stripslashes_deep, base_64 encode then. ( ver 0.2 )
            $replacer         = stripslashes_deep(@$_POST['replacer']);
            $in_posts         = @$_POST['in_posts'];
            $in_comments      = @$_POST['in_comments'];
            $in_pages         = @$_POST['in_pages'];
            $in_titles        = @$_POST['in_titles'];
            $case_insensitive = @$_POST['case_insensitive'];
            $partial_match    = @$_POST['partial_match'];
            $use_regex        = @$_POST['use_regex'];
            $delete           = @$_POST['delete'];

            if (is_array($original) && !empty($original)) {
                $numfield = array_diff($original, Array(''));
                $numfield = count($numfield);
                for ($i        = 0; $i <= $numfield; $i++) {
                    $in_posts[$i]         = ( empty($in_posts[$i]) ? 'no' : $in_posts[$i] );
                    $in_comments[$i]      = ( empty($in_comments[$i]) ? 'no' : $in_comments[$i] );
                    $in_pages[$i]         = ( empty($in_pages[$i]) ? 'no' : $in_pages[$i] );
                    $in_titles[$i]        = ( empty($in_titles[$i]) ? 'no' : $in_titles[$i] );
                    $case_insensitive[$i] = ( empty($case_insensitive[$i]) ? 'no' : $case_insensitive[$i] );
                    $partial_match[$i]    = ( empty($partial_match[$i]) ? 'no' : $partial_match[$i] );
                    $use_regex[$i]        = ( empty($use_regex[$i]) ? 'no' : $use_regex[$i] );

                    if (!empty($original[$i]) && empty($id[$i])) {
                        $wpdb->query($wpdb->prepare("INSERT INTO " . $this->parent->plugin_table_name . " 
							( original, replacement, in_posts, in_comments, in_pages, in_titles, case_insensitive, partial_match, use_regex )
							VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s )", array(
                                $wpdb->escape(base64_encode(trim($original[$i]))),
                                $wpdb->escape(trim($replacer[$i])),
                                $wpdb->escape($in_posts[$i]),
                                $wpdb->escape($in_comments[$i]),
                                $wpdb->escape($in_pages[$i]),
                                $wpdb->escape($in_titles[$i]),
                                $wpdb->escape($case_insensitive[$i]),
                                $wpdb->escape($partial_match[$i]),
                                $wpdb->escape($use_regex[$i]))));
                        $message = '<div id="message" class="updated fade"><p><strong>Replacement(s) Inserted.</strong></p></div>';
                    } elseif (( empty($original[$i]) && !empty($id[$i]) ) OR (!empty($delete[$i]) && !empty($id[$i]) )) {
                        $wpdb->query($wpdb->prepare("DELETE FROM " . $this->parent->plugin_table_name . " WHERE id = '" . $id[$i] . "'"));
                        $message = '<div id="message" class="updated fade"><p><strong>Replacement(s) Deleted.</strong></p></div>';
                    } elseif (!empty($original[$i]) && !empty($id[$i])) {
                        $wpdb->update($this->parent->plugin_table_name, array('original'         => $wpdb->escape(base64_encode(trim($original[$i]))),
                            'replacement'      => $wpdb->escape(trim($replacer[$i])),
                            'in_posts'         => $wpdb->escape($in_posts[$i]),
                            'in_comments'      => $wpdb->escape($in_comments[$i]),
                            'in_pages'         => $wpdb->escape($in_pages[$i]),
                            'in_titles'        => $wpdb->escape($in_titles[$i]),
                            'case_insensitive' => $wpdb->escape($case_insensitive[$i]),
                            'partial_match'    => $wpdb->escape($partial_match[$i]),
                            'use_regex'        => $wpdb->escape($use_regex[$i])
                            ), array('id' => $id[$i]), array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'), array('%d'));
                        $message = '<div id="message" class="updated fade"><p><strong>Replacement(s) Updated.</strong></p></div>';
                    }
                }
            } else {
                $message = '<div id="message" class="updated fade"><p><strong>Please add a field first.</strong></p></div>';
            }
            delete_transient('word_filter_plus_replacements');
            return $message;
        }

// end save_replacement_data

        function import_csv() {
            $message = false;
            if (!wp_verify_nonce($_POST['import_id'], 'wfp_import_nonce_verification')) {
                wp_die('Not allowed');
            }
            $startrow = intval($_POST['has_titles']) + 1;
            $truncate = intval($_POST['empty_first']);
            $message  = $this->csvManip->csv2table($_FILES['file']['tmp_name'], $this->parent->plugin_table_name, $this->parent->field_names, $startrow, $truncate);
            delete_transient('word_filter_plus_replacements');
            return $message;
        }

// end import_csv

        function export_csv() {
            $message = false;
            if (!wp_verify_nonce($_POST['export_id'], 'wfp_export_nonce_verification')) {
                wp_die('Not allowed');
            }
            return $this->csvManip->createcsv($this->parent->plugin_table_name, ",", $this->parent->field_names);
        }

// export_csv

        function process_post_tab2() {
            $message = false;
            if (isset($_POST['submit-word-replacer'])) {
                $message = $this->save_replacement_data();
            }
            echo $message;
        }

// end process_post_tab2

        function process_post_tab3() {
            $message = false;

            if (isset($_POST[WFP_START_POST_NONCE])) {
                $message = $this->parent->schedule_batch(WFP_POST_EVENT);
            } elseif (isset($_POST[WFP_START_COMMENT_NONCE])) {
                $message = $this->parent->schedule_batch(WFP_COMMENT_EVENT);
            } elseif (isset($_POST[WFP_CANCEL_POST_NONCE])) {
                $message = $this->parent->cancel_batch(WFP_POST_EVENT);
            } elseif (isset($_POST[WFP_CANCEL_COMMENT_NONCE])) {
                $message = $this->parent->cancel_batch(WFP_COMMENT_EVENT);
            } else {
                // No errors are raised because we want this to be a non-event.
            }
            echo $message;
        }

// end process_post_tab3

        function process_post_tab4() {
            if (( $_GET['page'] == $this->parent->plugin_slug ) && ( isset($_GET['tab']) && ( $_GET['tab'] === WFP_TAB4_SLUG ) )) {
                $message = false;
                if (isset($_POST['submit-import'])) {
                    $message = $this->import_csv();
                } elseif (isset($_POST['submit-export'])) {
                    $message = $this->export_csv();
                }
                echo $message;
            }
        }

// end process_post_tab4

        function wfp_do_settings_section($page, $sectionID) {
            global $wp_settings_sections, $wp_settings_fields;

            if (!isset($wp_settings_sections) || !isset($wp_settings_sections[$page]))
                return;

            foreach ((array) $wp_settings_sections[$page] as $section) {
                if ($sectionID !== $section['id'])
                    continue;
                if ($section['title'])
                    echo "<h3>{$section['title']}</h3>\n";
                call_user_func($section['callback'], $section);
                if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']]))
                    continue;
                echo '<table class="form-table">';
                do_settings_fields($page, $section['id']);
                echo '</table>';
            }
        }

// end wfp_do_settings_section

        function register_settings() {
            // Register our settings
            add_settings_section(WFP_PLUGIN_TAB1_SLUG, 'Basic Options', array($this, 'display_section1_text'), $this->parent->plugin_slug);
            register_setting('wfp-group-tab1', WFP_OPTION_MODE, array($this->parent, 'valid_modes'));
            add_settings_field('wfp-wfp-mode-id', 'How will you use this plugin?', array($this, 'display_wfp_mode'), $this->parent->plugin_slug, WFP_PLUGIN_TAB1_SLUG);

            add_settings_section(WFP_PLUGIN_TAB3_SLUG, 'Batch Operations', array($this, 'display_section3_text'), $this->parent->plugin_slug);

            register_setting('wfp-group-tab3', WFP_OPTION_BATCH_SCHEDULE, array($this->parent, 'valid_schedules'));
            add_settings_field('wfp-tab3-wfp-batch-schedule', 'How often?', array($this, 'display_batch_schedule'), $this->parent->plugin_slug, WFP_PLUGIN_TAB3_SLUG);

            register_setting('wfp-group-tab3', WFP_OPTION_BATCH_SIZE, 'intval');
            add_settings_field('wfp-tab3-wfp-batch-size', 'Batch Size', array($this, 'display_batch_size'), $this->parent->plugin_slug, WFP_PLUGIN_TAB3_SLUG);

            register_setting('wfp-group-tab3', WFP_OPTION_BATCH_SLEEP, 'intval');
            add_settings_field('wfp-tab3-wfp-batch-sleep', 'Pause length between posts?', array($this, 'display_batch_sleep'), $this->parent->plugin_slug, WFP_PLUGIN_TAB3_SLUG);
        }

// end 

        function settings_page_init() {
            $settings_page = add_management_page($this->parent->plugin_name, $this->parent->plugin_name, 'manage_options', $this->parent->plugin_slug, array($this, 'wfp_settings_page'));
        }

// end register_settings

        function wfp_admin_tabs($current = WFP_TAB1_SLUG) {
            $tabs = array(WFP_TAB1_SLUG => WFP_TAB1_LABEL, WFP_TAB2_SLUG => WFP_TAB2_LABEL, WFP_TAB3_SLUG => WFP_TAB3_LABEL, WFP_TAB4_SLUG => WFP_TAB4_LABEL);
            $links        = array();
            switch ($current) {
                case WFP_TAB4_SLUG:
                    echo '<div id="icon-tools" class="icon32"><br></div>';
                    break;
                case WFP_TAB3_SLUG:
                    echo '<div id="icon-tools" class="icon32"><br></div>';
                    break;
                case WFP_TAB2_SLUG:
                    echo '<div id="icon-edit" class="icon32"><br></div>';
                    break;
                default:
                    echo '<div id="icon-plugins" class="icon32"><br></div>';
                    break;
            }
            echo '<h2 class="nav-tab-wrapper">';
            foreach ($tabs as $tab => $name) {
                $class = ( $tab == $current ) ? ' nav-tab-active' : '';
                echo "<a class='nav-tab$class' href='?page=" . $this->parent->plugin_slug . "&tab=$tab'>$name</a>";
            }
            echo '</h2>';
        }

// end wfp_admin_tabs

        function wfp_settings_page() {
            if (isset($_GET['tab']) && ( in_array($_GET['tab'], array(WFP_TAB1_SLUG, WFP_TAB2_SLUG, WFP_TAB3_SLUG, WFP_TAB4_SLUG)) )) {
                $tab = $_GET['tab'];
            } else {
                $tab = WFP_TAB1_SLUG;
            }

            $this->curLink = admin_url('tools.php?page=' . $this->parent->plugin_slug . '&tab=' . $tab);
            ?>
            <div class="wrap">
                <h2><?php echo $this->parent->plugin_name; ?> Settings</h2>	
            <?php
            if ('true' == esc_attr(@$_GET['updated']))
                echo '<div class="updated" ><p>' . $this->parent->plugin_name . ' Settings updated.</p></div>';
            if (isset($_GET['tab']))
                $this->wfp_admin_tabs($_GET['tab']); else
                $this->wfp_admin_tabs(WFP_TAB1_SLUG);
            ?>
                <div id="poststuff">
            <?php
            switch ($tab) {
                case WFP_TAB1_SLUG :
                    $this->display_tab1();
                    break;
                case WFP_TAB2_SLUG :
                    $this->display_tab2();
                    break;
                case WFP_TAB3_SLUG :
                    $this->display_tab3();
                    break;
                case WFP_TAB4_SLUG :
                    $this->display_tab4();
                    break;
            }
            ?>		
                </div>
            </div>
            <?php
        }

// end wfp_settings_page

        function display_section1_text() {
            echo '<p>Having certain words on your blog can affect your ad revenue, where your site is listed, even who can view your site. Blog authors are occasionally told to remove all references to a product or personality from their site.  This plugin can solve all of those problems.</p>';
            echo '<p>The Word Filter Plus plugin allows you to the replace words or phrases on your site with other words or phrases.  It can even use advanced regex replacement logic to do "smart" replacements looking before or after a word to detect its context to determine if it is the word you are looking for.</p>';
            echo '<p></p>';
            echo '<p>This plugin can be used passively (it changes text as it is displayed) or actively (it changes the contents of the database as the information is saved).  It can also can be used do batch cleanup, replacing the content of existing posts.</p>';
        }

// end display_section1_text

        function display_section3_text() {
            echo '<p>This plugin has the ablity to process all of the existing content on your site and perform the replacements.  This can be a very time consuming process. A large site might have tens of thousands of posts. If not done strategically, an intensive search and replace could bring the entire webserver to a screeching halt. This plugin is written in a way that this can be avoided.</p>';
            echo '<p>WordPress Filter Plus takes advantage of the ablity of WordPress to schedule tasks so that the process of cleaning up your content can be done over an extended period of time. Use the options below to specify the rate at which your site is updated.</p>';
        }

// end display_section3_text

        function display_wfp_mode() {
            $optionValue = $this->parent->valid_modes(get_option(WFP_OPTION_MODE));
            echo '<input type="radio" name="' . WFP_OPTION_MODE . '" value="' . WFP_MODE_OFF . '" ' . checked(WFP_MODE_OFF, $optionValue, false) . '/> Off <small>(Do nothing during normal operation. Does not affect Batch Mode.)</small><br />';
            echo '<input type="radio" name="' . WFP_OPTION_MODE . '" value="' . WFP_MODE_PASSIVE . '" ' . checked(WFP_MODE_PASSIVE, $optionValue, false) . '/> Passive <small>(Change content as it is displayed. Slight performance cost.</small><br />';
            echo '<input type="radio" name="' . WFP_OPTION_MODE . '" value="' . WFP_MODE_ACTIVE . '" ' . checked(WFP_MODE_ACTIVE, $optionValue, false) . '/> Active<small>(Change content as it is saved. Alters content.)</small><br />';
        }

// end display_wfp_mode

        function display_batch_schedule() {
            $optionValue = $this->parent->valid_schedules(get_option(WFP_OPTION_BATCH_SCHEDULE));
            echo '<input type="radio" name="' . WFP_OPTION_BATCH_SCHEDULE . '" value="' . WFP_SCHEDULE_FIVE . '" ' . checked(WFP_SCHEDULE_FIVE, $optionValue, false) . '/> Every 5 Minutes <small></small><br />';
            echo '<input type="radio" name="' . WFP_OPTION_BATCH_SCHEDULE . '" value="' . WFP_SCHEDULE_FIFTEEN . '" ' . checked(WFP_SCHEDULE_FIFTEEN, $optionValue, false) . '/> Every 15 Minutes <small></small><br />';
            echo '<input type="radio" name="' . WFP_OPTION_BATCH_SCHEDULE . '" value="' . WFP_SCHEDULE_THIRTY . '" ' . checked(WFP_SCHEDULE_THIRTY, $optionValue, false) . '/> Every 30 Minutes <small></small><br />';
            echo '<input type="radio" name="' . WFP_OPTION_BATCH_SCHEDULE . '" value="' . WFP_SCHEDULE_SIXTY . '" ' . checked(WFP_SCHEDULE_SIXTY, $optionValue, false) . '/> Every 60 Minutes <small></small><br />';
        }

// end display_batch_schedule

        function display_batch_size() {
            $optionVal = intval(get_option(WFP_OPTION_BATCH_SIZE, DEFAULT_BATCH_SIZE));
            echo "How many posts/pages/comments to process in each interval?<br/>";
            echo "The batch size you want will vary depending upon how long your replacement list is, how long your posts are, and the speed and size of your server.<br/>";
            echo "The next setting, the milliseconds to sleep between items, can also dictate the maximum number of items you can process per interval. e.g. 300 posts with a 1 second delay for each will exceed five minutes guaranteed.<br/>";
            echo "Start with a smaller number if in doubt.<br/>";
            echo "<input id='wfp-tab3-wfp-batch-size' name='" . WFP_OPTION_BATCH_SIZE . "' size='6' type='text' value='{$optionVal}' />";
        }

// end display_batch_size

        function display_batch_sleep() {
            $optionVal = intval(get_option(WFP_OPTION_BATCH_SLEEP, DEFAULT_BATCH_SLEEP));
            echo "How many milliseconds between each posts item is checked? This gives a server time to respond. Use small numbers. 120 is just over 1/10 of a second, but would still add 2 minutes to a 1000 post batch.<br/>";
            echo "<input id='wfp-tab3-wfp-batch-sleep' name='" . WFP_OPTION_BATCH_SLEEP . "'. size='6' type='text' value='{$optionVal}' />";
        }

// end display_batch_sleep

        function display_tab1() {
            ?>
            <form method="post" action="options.php">
            <?php
            settings_fields('wfp-group-tab1');
            $this->wfp_do_settings_section($this->parent->plugin_slug, WFP_PLUGIN_TAB1_SLUG);
            ?>		
                <br/><br/>
                <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />		
            </form>
            <style>
                #tcc-donate-donate {
                    width: 250px;
                    position: absolute;
                    bottom: 0;
                    right: 0;
                    padding: 0 10px;
                    background: #464646;
                    color: #fff;
                    -moz-border-radius: 5px;
                    -webkit-border-radius: 5px;
                }
                #tcc-donate-donate img {
                    float: left;
                    margin-right: 5px;
                    -moz-border-radius: 5px;
                    -webkit-border-radius: 5px;
                }
                #tcc-donate-donate a {
                    color: #ff0;
                }
                #tcc-donate-donate a:hover {
                    color: #fff;
                }

            </style>
            <div id="tcc-donate-donate">
                <p><img src="http://www.gravatar.com/avatar/1f7624ae2dd3d0ed7a9e423017313c9e?s=64" height="64" width="64" /><?php esc_html_e('Hi there! If you enjoy this plugin, consider showing your appreciation by making a small donation to its author!', 'tcc-donate'); ?></p>
                <p style="text-align: center"><a href="http://thecodecave.com/donate" target="_new"><?php esc_html_e('Click here to donate using PayPal'); ?></a></p>
            </div>
            </div>
                    <?php
                }

// end display_tab1

                function display_tab2() {
                    echo '
			<div class="wrap">
				<h2>Filter Configuration</h2>';
                    $this->process_post_tab2();

                    $basefield        = '
				<tr>
					<td><input type="checkbox" name="delete[]" value="1" /></td>
					<td><input type="hidden" name="id[]" value="" /><input type="hidden" name="id[]" value="" />
					<input style="width:100%" name="original[]" type="text" /></td><td> &raquo; </td>
					<td><textarea style="resize:none;width:100%" name="replacer[]"></textarea></td>
					<td class="replacer_expandable"><input value="yes" name="in_posts[]" type="checkbox" /></td>
					<td class="replacer_expandable"><input value="yes" name="in_comments[]" type="checkbox" /></td>
					<td class="replacer_expandable"><input value="yes" name="in_pages[]" type="checkbox" /></td>
					<td class="replacer_expandable"><input value="yes" name="in_titles[]" type="checkbox" /></td>
					<td class="replacer_expandable"><input value="yes" name="case_insensitive[]" type="checkbox" /></td>
					<td class="replacer_expandable"><input value="yes" name="partial_match[]" type="checkbox" /></td>
					<td class="replacer_expandable"><input value="yes" name="use_regex[]" type="checkbox" /></td>
					<td></td>
				</tr>';
                    ?>

            <p>Put the word to be replaced on the left, and what to change it to on the right. <a id="show_hide_help" href="#">Help?</a></p>
            <form method="post" action="<?php echo $this->curLink; ?>">
            <?php wp_nonce_field('wfp_settings_plus_nonce_action', 'wfp_settings_plus_nonce'); ?>
                <table class="widefat fixed" width="650" align="center" width="100%" id="word-replacer-list">
                    <thead>
                        <tr>
                            <th width="40">Delete</th>
                            <th>Original</th><th width="5">&nbsp;</th><th>Replacement</th>
                            <th class="replacer_expandable" width="40">Posts</th>
                            <th class="replacer_expandable" width="70">Comments</th>
                            <th class="replacer_expandable" width="40">Pages</th>
                            <th class="replacer_expandable" width="40">Titles</th>
                            <th class="replacer_expandable" width="80">Case Insensitive</th>
                            <th class="replacer_expandable" width="80">Partial Match</th>
                            <th class="replacer_expandable" width="40">Regex</th>
                            <th class="replacer_expandall" width="20"><a style="color:black" href="#" title="Expand/Collapse">&laquo;&raquo;</a></th>
                        </tr>
                    </thead>
                    <?php
                    $i                = -1;
                    $replacement_list = $this->parent->replacement_list();
                    if (is_array($replacement_list) AND !empty($replacement_list)) {
                        foreach ($replacement_list as $aReplacement) {
                            $i++;
                            ?>
                    <?php $alternate = ( empty($alternate) ? 'class="alternate"' : '' ); ?>
                            <tr <?php echo $alternate; ?>>
                                <td>
                                    <input type="checkbox" name="delete[<?php echo intval($i); ?>]" value="1" />
                                </td>
                                <td>
                                    <input type="hidden" name="id[<?php echo intval($i); ?>]" value="<?php echo $aReplacement['id']; ?>" />
                                    <input type="hidden" name="count" value="" />
                                    <input style="width:100%" type="text" name="original[<?php echo intval($i); ?>]" id="original_<?php echo intval($i); ?>" value="<?php echo htmlspecialchars(base64_decode($aReplacement['original'], true) ? base64_decode($aReplacement['original']) : $aReplacement['original'] ) ?>" /></td><td> &raquo; </td>
                                <td>
                                    <textarea style="resize:vertical;width:100%" name="replacer[<?php echo intval($i); ?>]"><?php echo $this->parent->_specialchar($aReplacement['replacement']); ?></textarea>
                                </td>
                                <td class="replacer_expandable">
                                    <input value="yes" name="in_posts[<?php echo intval($i); ?>]" <?php checked($aReplacement['in_posts'], 'yes'); ?> type="checkbox" />
                                </td>
                                <td class="replacer_expandable">
                                    <input value="yes" name="in_comments[<?php echo intval($i); ?>]" <?php checked($aReplacement['in_comments'], 'yes'); ?> type="checkbox" />
                                </td>
                                <td class="replacer_expandable">
                                    <input value="yes" name="in_pages[<?php echo intval($i); ?>]" <?php checked($aReplacement['in_pages'], 'yes'); ?> type="checkbox" />
                                </td>
                                <td class="replacer_expandable">
                                    <input value="yes" name="in_titles[<?php echo intval($i); ?>]" <?php checked($aReplacement['in_titles'], 'yes'); ?> type="checkbox" />
                                </td>
                                <td class="replacer_expandable">
                                    <input value="yes" name="case_insensitive[<?php echo intval($i); ?>]" <?php checked($aReplacement['case_insensitive'], 'yes'); ?> type="checkbox" />
                                </td>
                                <td class="replacer_expandable">
                                    <input value="yes" name="partial_match[<?php echo intval($i); ?>]" <?php checked($aReplacement['partial_match'], 'yes'); ?> type="checkbox" />
                                </td>
                                <td class="replacer_expandable">
                                    <input value="yes" name="use_regex[<?php echo intval($i); ?>]" <?php checked($aReplacement['use_regex'], 'yes'); ?> type="checkbox" />
                                </td>
                                <td></td>
                            </tr>
                    <?php
                }
            } else {
                echo $basefield;
            }
            ?>
                </table>
                <input type="button" id="add_more_field" value="+ Add More Fields" style="cursor:pointer" />
                <input type="hidden" name="action" value="update" /> 
                <input name="submit-word-replacer" class="button-primary" type="submit" value="<?php _e('Save Changes') ?>" />
            </form>
            </div>
            <?php
        }

// end display_tab2

        function display_tab3() {
            $this->process_post_tab3();

            if (!( wp_next_scheduled(WFP_POST_EVENT) || wp_next_scheduled(WFP_COMMENT_EVENT) )) {
                ?>
                <form method="post" action="options.php">
                <?php
                settings_fields('wfp-group-tab3');
                $this->wfp_do_settings_section($this->parent->plugin_slug, WFP_PLUGIN_TAB3_SLUG);
                ?>
                    <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />		
                </form>
                            <?php
                        } else {
                            echo('<h3>A Batch is Currently Executing</h3>');
                            echo('<p><strong>Settings cannnot be changed while a batch is running.</strong></p>');
                            $iteration = intval(get_option(WFP_OPTION_ITERATION, 0));
                            echo('Current Iteration: ' . $iteration . '<br/>');
                            if (!$timestamp = wp_next_scheduled(WFP_POST_EVENT))
                                $timestamp = wp_next_scheduled(WFP_COMMENT_EVENT);
                            echo('Next Event Iteration: ' . date(DATE_RFC822, $timestamp));
                        }
                        ?>
            <br />
            <br />
                        <?php
                        if (!( wp_next_scheduled(WFP_POST_EVENT) || wp_next_scheduled(WFP_COMMENT_EVENT) )) {
                            ?>
                <div class="postbox">
                    <h3><label for="title">Batch Process for Posts</label></h3>
                    <div class="inside">
                        <strong>PRESSING THIS BUTTON WILL MAKE PERMANENT CHANGES TO YOUR SITE.</strong> <br /><br />Pressing this button will apply all current replacement logic to ALL of your posts.  It will use the timing settings you last saved to schedule a batch replacement run.<br/>
                        <br />
                        <form enctype="multipart/form-data" action="<?php echo $curLink; ?>" method="POST">
                <?php wp_nonce_field('wfp_export_nonce_verification', WFP_START_POST_NONCE); ?>
                            <input name="submit-export" type="submit" value="Start Post Batch" />
                        </form>
                    </div>
                </div>

                <div class="postbox">
                    <h3><label for="title">Batch Process for Comments</label></h3>
                    <div class="inside">
                        <strong>PRESSING THIS BUTTON WILL MAKE PERMANENT CHANGES TO YOUR SITE.</strong> <br /><br />Pressing this button will apply all current replacement logic to ALL of your comments.  It will use the timing settings you last saved to schedule a batch replacement run.<br /> To make processing comments complete faster, be sure to empty out any unneeded spam comments before starting this batch.<br/>
                        <br />
                        <form enctype="multipart/form-data" action="<?php echo $curLink; ?>" method="POST">
                <?php wp_nonce_field('wfp_export_nonce_verification', WFP_START_COMMENT_NONCE); ?>
                            <input name="submit-export" type="submit" value="Start Comment Batch" />
                        </form>
                    </div>
                </div>
                <?php
            } else {
                if (wp_next_scheduled(WFP_POST_EVENT)) {
                    ?>
                    <div class="postbox">
                        <h3><label for="title">Batch Process for Posts</label></h3>
                        <div class="inside">
                            <strong>PRESSING THIS BUTTON WILL NOT UNDO CHANGES ALREADY MADE TO YOUR SITE.</strong> <br /><br />There is no undo. However, you can cancel the current batch before it has completed and then even change your settings and start the batch process again from the start.<br/>
                            <br />
                            <form enctype="multipart/form-data" action="<?php echo $curLink; ?>" method="POST">
                    <?php wp_nonce_field('wfp_export_nonce_verification', WFP_CANCEL_POST_NONCE); ?>
                                <input name="submit-export" type="submit" value="CANCEL CURRENT BATCH" />
                            </form>
                        </div>
                    </div>
                                    <?php
                                } elseif (wp_next_scheduled(WFP_COMMENT_EVENT)) {
                                    ?>
                    <div class="postbox">
                        <h3><label for="title">Batch Process for Comments</label></h3>
                        <div class="inside">
                            <strong>PRESSING THIS BUTTON WILL NOT UNDO CHANGES ALREADY MADE TO YOUR SITE.</strong> <br /><br />There is no undo. However, you can cancel the current batch before it has completed and then even change your settings and start the batch process again from the start.<br/>
                            <br />
                            <form enctype="multipart/form-data" action="<?php echo $curLink; ?>" method="POST">
                    <?php wp_nonce_field('wfp_export_nonce_verification', WFP_CANCEL_COMMENT_NONCE); ?>
                                <input name="submit-export" type="submit" value="CANCEL CURRENT BATCH" />
                            </form>
                        </div>
                    </div>
                    <?php
                } else {
                    echo('<strong>Umm.. Plugin Developers aren\'t perfect. At least this one obviously isn\'t. You should never ever see this text on your site. Please let him know you have. </strong>');
                }
            }
        }

// end display_tab3

        function display_tab4() {
            ?>
            <div class="wrap">
                <div class="postbox">
                    <h3><label for="title">Import a new list of replacements</label></h3>
                    <div class="inside">
                        <strong>Upload a File</strong>
                        <br />

                        <form enctype="multipart/form-data" action="<?php echo $curLink; ?>" method="POST">
                            <input type="hidden" name="file_upload" id="file_upload" value="true" />
                            <input type="hidden" name="MAX_FILE_SIZE" value="1000000" />
            <?php wp_nonce_field('wfp_import_nonce_verification', 'import_id'); ?>
                            Choose a CSV file to upload: <input name="file" id="file" type="file" /><br />
                            <br />
                            <label><input type="checkbox" name="has_titles" value="1" /> Row 1 contains column titles </label><br />
                            <label><input type="checkbox" name="empty_first" value="1" /> Erase all existing replacements before performing the import. </label><br />
                            <br />
                            <input name="submit-import" type="submit" value="Upload File" />
                        </form>
                    </div>
                </div>
                <div class="postbox">
                    <h3><label for="title">Export the current list of replacements</label></h3>
                    <div class="inside">
                        <strong>Click this button to save the current list of replacements to your local computer.</strong>
                        <br />
                        <form enctype="multipart/form-data" action="<?php echo $curLink; ?>" method="POST">
            <?php wp_nonce_field('wfp_export_nonce_verification', 'export_id'); ?>
                            <input name="submit-export" type="submit" value="Save to file" />
                        </form>
                    </div>
                </div>
            </div>
            <?php
        }

// end display_tab4
    }

    // class wfp_settings
} // end !class_exists
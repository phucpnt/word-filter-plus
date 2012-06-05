<?php

/**
  Copyright (c) 2012, Phuc PN.Truong - pn.truongphuc@gmail.com
  All rights reserved.

  Redistribution and use in source and binary forms, with or without
  modification, are permitted provided that the following conditions are met:
 * Redistributions of source code must retain the above copyright
  notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
  notice, this list of conditions and the following disclaimer in the
  documentation and/or other materials provided with the distribution.
 * Neither the name of the <organization> nor the
  names of its contributors may be used to endorse or promote products
  derived from this software without specific prior written permission.

  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
  ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
  WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
  DISCLAIMED. IN NO EVENT SHALL Phuc PN.Truong BE LIABLE FOR ANY
  DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
  (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
  ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
  (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
  SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.* */
class PWP_FilterPlus {

    private static $_INSTANCE             = NULL;
    private $assignCat             = NULL;
    private $shortCodeReplacements = array();

    const SC_PFPLUS = 'wfp';

    private function __construct() {
        add_shortcode(self::SC_PFPLUS, array(&$this, 'scCatLink'));
    }

    public static function getInstance() {
        if (self::$_INSTANCE === NULL) {
            self::$_INSTANCE = new self;
        }
        return self::$_INSTANCE;
    }

    public function doReplacement($original, $replacement, $string, $b = '', $i = '') {
        if (strpos($replacement, '[' . self::SC_PFPLUS) === 0) {
            if (isset($this->shortCodeReplacements[$replacement])) {
                $newReplace = $this->shortCodeReplacements[$replacement];
            } else {
                $newReplace = json_decode(do_shortcode($replacement), true);
                $this->shortCodeReplacements[$replacement] = $newReplace;
            }
        } else {
            $newReplace = false;
        }

        if ($newReplace) {
            $count  = 0;
            $result = preg_replace("/{$b}{$original}{$b}/$i", $newReplace['replacement'], $string, -1, $count);
            if ($count > 0) {
                global $post;
                if (!in_category($newReplace['catid'], $post)) {
                    wp_set_post_terms($post->ID, array(intval($newReplace['catid'])), 'category', true);
                }
                wp_set_post_categories($this->assignCat['postid'], $this->assignCat['cats']);
                return $result;
            }
        }
        return false;
    }

    public function scCatLink($atts, $content = NULL) {
        $atts = shortcode_atts(array(
            'catslug'    => FALSE,
            ), $atts);
        $replacement = false;

        if (isset($atts['catslug']) && $atts['catslug']) {
            $assignCat   = get_category_by_slug($atts['catslug']);
            $catlink     = get_category_link($assignCat);
            $replacement = json_encode(array(
                'replacement' => "<a href=\"$catlink\">$content</a>",
                'catid'       => $assignCat->term_id,
                ));
        }
        return $replacement;
    }

}

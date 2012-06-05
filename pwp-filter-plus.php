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

    private static $_INSTANCE = NULL;
    private $assignCat = NULL;

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

    public function doReplacement($original, $replacement, $string, $b='', $i='') {
        $replacement = do_shortcode($replacement);
        $count       = 0;
        $result      = preg_replace("/{$b}{$original}{$b}/$i", $replacement, $string, -1, $count);
        if ($count > 0) {
            if ($this->assignCat !== NULL) {
                wp_set_post_categories($this->assignCat['postid'], $this->assignCat['cats']);
                $this->assignCat = NULL;
            }
            return $result;
        } else {
            return false;
        }
    }

    public function scCatLink($atts, $content = NULL) {
        $atts = shortcode_atts(array(
            'catslug'    => FALSE,
            ), $atts);
        $replacement = $content;

        if (isset($atts['catslug']) && $atts['catslug']) {
            global $post;

            $alreadyAssignCat = FALSE;
            $assignedCats       = wp_get_post_categories($post->ID, array('fields'      => 'ids'));
            $postCatSlugs = wp_get_post_categories($post->ID, array('fields' => 'slugs'));

            foreach ($postCatSlugs as $slug) {
                if ($slug === $atts['catslug']) {
                    $alreadyAssignCat = TRUE;
                }
            }

            $assignCat = get_category_by_slug($atts['catslug']);
            if ($alreadyAssignCat === FALSE) {
                $assignedCats[] = $assignCat->term_id;
                $this->assignCat = array(
                    'postid' => $post->ID,
                    'cats'   => $assignedCats,
                );
            }
            $replacement = PWP_CatLinks::getLink($assignCat, $replacement);
        }

        return $replacement;
    }

}

class PWP_CatLinks{
    private static $catList = array();
    public static function getLink($cat, $anchorText){
        $nofollow = '';
        if(isset(self::$catList[$cat->term_id])){
            $nofollow = 'rel="nofollow"';
        }
        else{
            self::$catList[$cat->term_id] = $cat;
        }
        $catlink = get_category_link($cat);
        return "<a href=\"$catlink\" $nofollow>$anchorText</a>";
    }
}

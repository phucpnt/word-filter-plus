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

    private $shortCodeReplacements = array();
    private $currentMaskedImgLink = array();
    static private $_INSTANCE     = NULL;
    static public $stripforCat   = FALSE;
    static public $stripCatLimit = 20;
    //@FIXME
    static public $excludePostId = array(5186, 555, 5187);

    const SC_PFPLUS          = 'wfp';
    const ENABLE_AUTO_ASSIGN = true;
    const MIN_NOFOLLOW_LINK  = 5;

    private function __construct() {
        add_shortcode(self::SC_PFPLUS, array(&$this, 'scCatLink'));
    }

    public static function getInstance() {
        if (self::$_INSTANCE === NULL) {
            self::$_INSTANCE = new self;
        }
        return self::$_INSTANCE;
    }

    public function doReplacement($original, $replacement, $string, $b = '', $i = '', $mode = 1) {
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
            $count              = 0;
            $stringExcludeLinks = preg_replace_callback("/(<a[^>]*>.*<\/a>|<img[^>]*\/>)/siU", array(&$this, 'maskLinkImage'), $string);
            $result = preg_replace("/{$b}{$original}{$b}/$i", $newReplace['replacement'], $stringExcludeLinks, -1, $count);
            $result = strtr($result, $this->currentMaskedImgLink);
            $this->currentMaskedImgLink = array();
            if ($count > 0) {
                if (self::ENABLE_AUTO_ASSIGN) {
                    global $post;
                    if (!in_category($newReplace['catid'], $post)
                        && !in_array($post->ID, self::$excludePostId)
                    ) {
                        wp_set_post_terms($post->ID, array(intval($newReplace['catid'])), 'category', true);
                        add_post_meta($post->ID, 'wfp-catid-auto', $newReplace['catid']);
                    }
                }
                if (self::$stripforCat && is_category($newReplace['catid']) && $mode == WFP_MODE_PASSIVE) {
                    $result           = strip_tags($result);
                    $stripReplacement = strip_tags($newReplace['replacement']);
                    $result           = self::getParagraphLimitContainsWord($result, $stripReplacement, self::$stripCatLimit);
                }
            }
            return $result;
        }
        return false;
    }

    public function maskLinkImage($matches) {
        $count = count($this->currentMaskedImgLink);
        $this->currentMaskedImgLink["{_otex_imglink_mask_{$count}_}"] = $matches[0];
        return "{_otex_imglink_mask_{$count}_}";
    }

    public function scCatLink($atts, $content = NULL) {
        $atts = shortcode_atts(array(
            'catslug'    => FALSE,
            'num'        => self::MIN_NOFOLLOW_LINK,
            ), $atts);
        $replacement = false;

        if (isset($atts['catslug']) && $atts['catslug']) {
            $assignCat = get_category_by_slug($atts['catslug']);
            $query     = new WP_Query(array(
                    'cat'            => $assignCat->term_id,
                    'posts_per_page' => $atts['num'],
                ));
            $nofollow        = '';
            if ($query->post_count < intval($atts['num'])) {
                $nofollow    = 'rel="nofollow"';
            }
            $catlink     = get_category_link($assignCat);
            $replacement = json_encode(array(
                'replacement' => "<a href=\"$catlink\" $nofollow>$content</a>",
                'catid'       => $assignCat->term_id,
                ));
        }
        return $replacement;
    }

    /**
     * Limits a phrase to a given number of words.
     *
     *     $text = Text::limit_words($text);
     *
     * @param   string   phrase to limit words of
     * @param   integer  number of words to limit to
     * @param   string   end character or entity
     * @return  string
     */
    public static function limit_first_words($str, $limit = 100, $end_char = NULL) {
        $limit    = (int) $limit;
        $end_char = ($end_char === NULL) ? '…' : $end_char;

        if (trim($str) === '')
            return $str;

        if ($limit <= 0)
            return $end_char;

        preg_match('/^\s*+(?:\S++\s*+){1,' . $limit . '}/u', $str, $matches);

        // Only attach the end character if the matched string is shorter
        // than the starting string.
        return rtrim($matches[0]) . ((strlen($matches[0]) === strlen($str)) ? '' : $end_char);
    }

    public static function limit_last_words($str, $limit = 100, $end_char = NULL) {
        $limit    = (int) $limit;
        $end_char = ($end_char === NULL) ? '…' : $end_char;

        if (trim($str) === '')
            return $str;

        if ($limit <= 0)
            return $end_char;

        preg_match('/\s*+(?:\S++\s*+){1,' . $limit . '}$/u', $str, $matches);

        // Only attach the end character if the matched string is shorter
        // than the starting string.
        return ((strlen($matches[0]) === strlen($str)) ? '' : $end_char) . ltrim($matches[0]);
    }

    public static function getParagraphLimitContainsWord($text, $searchWord, $limit) {
        $strpos     = strpos($text, $searchWord);
        $upString   = substr($text, 0, $strpos);
        $upString   = self::limit_last_words($upString, $limit, '[...] ');
        $downString = substr($text, $strpos + strlen($searchWord));
        $downString = self::limit_first_words($downString, $limit, ' [...]');
        $text       = $upString . ' ' . $searchWord . $downString;
        return $text;
    }

}

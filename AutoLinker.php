<?php
/*
    Copyright 2013 p12 <tir5c3@yahoo.co.uk>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('MEDIAWIKI')) {
    die();
}

$wgExtensionCredits['parserhook'][] = array(
    'path'           => __FILE__,
    'name'           => 'AutoLinker',
    'author'         => 'p12',
    'descriptionmsg' => 'autolinker_desc',
    'url'            => '', // TODO
    'version'        => '', // TODO
);

$wgExtensionMessagesFiles['AutoLinkerMagic'] = dirname( __FILE__ ) . '/' . 'AutoLinker.i18n.magic.php';
$wgExtensionMessagesFiles['AutoLinker'] = dirname( __FILE__ ) . '/' . 'AutoLinker.i18n.php';

$wgHooks['ParserFirstCallInit'][] = 'AutoLinker::setup';

class AutoLinker {

    // Has Autolinker been initialised this session?
    private static $initialised = false;

    // Cache link mapping for the currently processed page
    private static $cached_links = array();

    static function setup(&$parser)
    {
        $parser->setFunctionHook('autolink', 'AutoLinker::on_parse');

        return true;
    }

    static function on_parse(&$parser, $param1 = '')
    {
        self::initialize();

        // remove surrounding whitespace and trailing () for functions
        $p = preg_replace('/\(\)^/', '', trim($param1));
        if (array_key_exists($p, self::$cached_links)) {
            $output = '[[' . self::$cached_links[$p] . '|' . $param1 . ']]';
        } else {
            $output = $param1;
        }
        return $output;
    }

    private static function initialize()
    {
        global $wgTitle;

        if (self::$initialised) {
            return;
        }

        self::$cached_links = [];

        $json_string = wfMsgGetKey('autolinker-definition', true, false, false);
        $data = json_decode($json_string, true);


        // while parsing the decoded definition, immediately check whether
        // a group includes currently processed page
        $current_page_groups = [];

        if (isset($data['groups'])) {
            foreach ($data['groups'] as $group) {
                if (!isset($group['name']) || !is_array($group['urls'])) {
                    continue;
                }

                $url_to_check = $wgTitle;

                // check base url (if provided)
                if (isset($group['base_url'])) {
                    $url = $group['base_url'];
                    $len = strlen($url);
                    if ($len > 0 && strncmp($url_to_check, $url, $len) != 0) {
                        // base url does not match
                        continue;
                    }
                    $url_to_check = substr($url_to_check, strlen($url));
                }

                // check urls
                if (!isset($group['urls'])) {
                    continue;
                }
                $found = false;
                foreach ($group['urls'] as $url) {
                    if (strcmp($url_to_check, $url) == 0) {
                        $found = true;
                        break;
                    }
                }

                if ($found == false) {
                    continue;
                }

                $current_page_groups[] = $group['name'];
            }
        }

        // check which identifiers shouold be linked on the current page
        if (isset($data['links'])) {
            foreach ($data['links'] as $linkdef) {
                if (!isset($linkdef['string']) || !isset($linkdef['target'])) {
                    continue;
                }

                if (isset($linkdef['on_group'])) {
                    if (!array_key_exists($linkdef['on_group'], $current_page_groups)) {
                        continue;
                    }
                }

                if (isset($linkdef['on_page'])) {
                    if (strcmp($wgTitle, $linkdef['on_page']) != 0) {
                        continue;
                    }
                }

                self::$cached_links[$linkdef['string']] = $linkdef['target'];
            }
        }
        self::$initialised = true;
    }
}


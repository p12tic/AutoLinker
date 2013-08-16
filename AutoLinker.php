<?php
/*
    Copyright 2013 Povilas Kanapickas <tir5c3@yahoo.co.uk>

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
    'author'         => 'Povilas Kanapickas',
    'descriptionmsg' => 'autolinker_desc',
    'url'            => 'https://github.com/p12tic/AutoLinker',
    'version'        => '', // TODO
);

$AutoLinkerDefinitions = array(
    '' => 'autolinker-definition'
);

$wgExtensionMessagesFiles['AutoLinkerMagic'] = dirname( __FILE__ ) . '/' . 'AutoLinker.i18n.magic.php';
$wgExtensionMessagesFiles['AutoLinker'] = dirname( __FILE__ ) . '/' . 'AutoLinker.i18n.php';

$wgHooks['ParserFirstCallInit'][] = 'AutoLinker::setup';

class AutoLinker {

    // Cache link mapping for the currently processed page
    private static $cached_links = array();

    static function setup(&$parser)
    {
        $parser->setFunctionHook('autolink', 'AutoLinker::on_parse');

        return true;
    }

    static function on_parse(&$parser, $param_list = '', $param_text = '')
    {
        global $AutoLinkerDefinitions;
        if (!array_key_exists($param_list, $AutoLinkerDefinitions)) {
            return $param_text;
        }

        if (!self::initialize($param_list)) {
            return $param_text;
        }

        $p = $param_text;

        // Remove surrounding whitespace and trailing () for functions
        // A special case is operator(), which we leave as it is
        $p = trim($p);
        if ($p != 'operator()') {
            $p = preg_replace('/\(\)$/', '', $p);
        }

        if (array_key_exists($p, self::$cached_links[$param_list])) {
            $output = '[[' . self::$cached_links[$param_list][$p] . '|' . $param_text . ']]';
        } else {
            $output = $param_text;
        }
        return $output;
    }

    private static function initialize($list_name)
    {
        global $wgTitle, $AutoLinkerDefinitions;

        if (array_key_exists($list_name, self::$cached_links)) {
            // already initialized
            return true;
        }

        if (!array_key_exists($list_name, $AutoLinkerDefinitions)) {
            // no such list
            return false;
        }


        self::$cached_links[$list_name] = array();
        $curr_list = &self::$cached_links[$list_name];

        $json_string = wfMsgGetKey($AutoLinkerDefinitions[$list_name], true,
                                   false, false);

        $data = json_decode($json_string, true);

        // while parsing the decoded definition, immediately check whether
        // a group includes currently processed page
        $current_page_groups = array();

        if (isset($data['groups'])) {
            foreach ($data['groups'] as $group) {
                if (!isset($group['name']) || !is_array($group['urls'])) {
                    continue;
                }

                $url_to_check = str_replace(' ', '_', $wgTitle);

                // check base url (if provided)
                if (isset($group['base_url'])) {
                    $url = str_replace(' ', '_', $group['base_url']);
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
                    $url = str_replace(' ', '_', $url);
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
                    if (!in_array($linkdef['on_group'], $current_page_groups)) {
                        continue;
                    }
                }

                if (isset($linkdef['on_page'])) {
                    if (strcmp($wgTitle, $linkdef['on_page']) != 0) {
                        continue;
                    }
                }

                $curr_list[$linkdef['string']] = $linkdef['target'];
            }
        }
        return true;
    }
}


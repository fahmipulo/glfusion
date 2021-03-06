<?php
// +--------------------------------------------------------------------------+
// | Forum Plugin for glFusion CMS                                            |
// +--------------------------------------------------------------------------+
// | format.inc.php                                                           |
// |                                                                          |
// | General formatting routines                                              |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2008-2017 by the following authors:                        |
// |                                                                          |
// | Mark R. Evans          mark AT glfusion DOT org                          |
// | Eric M. Kingsley       kingsley AT trains-n-town DOTcom                  |
// |                                                                          |
// | Copyright (C) 2000-2008 by the following authors:                        |
// |                                                                          |
// | Authors: Blaine Lang       - blaine AT portalparts DOT com               |
// |                              www.portalparts.com                         |
// | Version 1.0 co-developer:    Matthew DeWyer, matt@mycws.com              |
// | Prototype & Concept :        Mr.GxBlock, www.gxblock.com                 |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+

// this file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

// Magic url types
define('MAGIC_URL_EMAIL', 1);
define('MAGIC_URL_FULL', 2);
define('MAGIC_URL_LOCAL', 3);
define('MAGIC_URL_WWW', 4);

define('DISABLE_BBCODE',1);
define('DISABLE_SMILIES',2);
define('DISABLE_URLPARSE',4);

function convertlinebreaks ($text) {
    return preg_replace ("/\015\012|\015|\012/", "\n", $text);
}

function bbcode_stripcontents ($text) {
    return preg_replace ("/[^\n]/", '', $text);
}

function bbcode_htmlspecialchars($text) {
    global $_FF_CONF;

    return (@htmlspecialchars ($text,ENT_NOQUOTES, COM_getEncodingt()));
}

function do_bbcode_url ($action, $attributes, $content, $params, $node_object) {
    global $_FF_CONF, $_CONF;

    if ($action == 'validate') {
        return true;
    }

	$retval = '';
    $url = '';
    $linktext = '';
    $target = '';

    if (!isset ($attributes['default'])) {
        if ( stristr($content,'http') ) {
            $url = strip_tags($content);
            $linktext = @htmlspecialchars ($content,ENT_QUOTES, COM_getEncodingt());
        } else {
            $url = 'http://'.strip_tags($content);
            $linktext = @htmlspecialchars ($content,ENT_QUOTES, COM_getEncodingt());
        }
    } else if ( stristr($attributes['default'],'http') ) {
        $url = strip_tags($attributes['default']);
//        $linktext = @htmlspecialchars ($content,ENT_QUOTES,COM_getEncodingt());
        $linktext = strip_tags($content);
    } else {
        $url = 'http://'.strip_tags($attributes['default']);
        $linktext = @htmlspecialchars ($content,ENT_QUOTES,COM_getEncodingt());
    }

    if ( isset($_CONF['open_ext_url_new_window']) && $_CONF['open_ext_url_new_window'] == true && stristr($url,$_CONF['site_url']) === false ) {
        $target = ' target="_blank" rel="noopener noreferrer" ';
    }
	$url = COM_sanitizeUrl( $url );
    $retval = '<a href="'. $url .'" rel="nofollow"'.$target.'>'.$linktext.'</a>';
	return $retval;
}

function do_bbcode_list ($action, $attributes, $content, $params, $node_object) {
    if ($action == 'validate') {
        return true;
    }
    if (!isset ($attributes['default'])) {
        return '<ul>'.$content.'</ul>';
    } else {
        if ( is_numeric($attributes['default']) ) {
            return '<ol>'.$content.'</ol>';
        } else {
            return '<ul>'.$content.'</ul>';
        }
    }
    return '<ul>'.$content.'</ul>';
}

function do_bbcode_file ($action, $attributes, $content, $params, $node_object) {
    global $_CONF,$_TABLES,$_FF_CONF,$topicRec,$forumfiles;
    global $previewitem,$filemgmt_FileStoreURL,$LANG_GF10;

    $retval = '';
    if ( $action == 'validate') {
        return true;
    }

    $uniqueID = 0;
    if ( isset($_POST['uniqueid']) ) {
        $uniqueID = COM_applyFilter($_POST['uniqueid'],true);
    }

    $sql = "SELECT id,filename,repository_id,show_inline,topic_id FROM {$_TABLES['ff_attachments']} ";
    if ( $uniqueID > 0 ) {  // User is previewing a new post
        $sql .= "WHERE topic_id = ". (int) $uniqueID ." AND tempfile=1 ";
    } else if (isset($previewitem['id'])) {
         $sql .= "WHERE topic_id = ".(int) $previewitem['id']." ";
    } else if (isset($topicRec['id'])){
        $sql .= "WHERE topic_id = ".(int) $topicRec['id']." ";
    } else {
        return '';
    }
    $sql .= "ORDER BY id";
    $query = DB_query($sql);
    $i = 1;

    if ( isset($attributes['align'] ) ) {
        if ( !in_array(strtolower($attributes['align']),array('left','right','center') ) ) {
            $attributes['align'] = 'left';
        }
        $align = ' align="' . $attributes['align'] . '" ';
    } else {
        $align = '';
    }

    if ( isset($attributes['lightbox'] ) ) {
        $lb = ' rel="lightbox" data-uk-lightbox ';
    } else {
        $lb = '';
    }

    while (list($id,$fileinfo,$repository_id,$showinline,$topicid) = DB_fetchArray($query)) {
        if ($i == $content) {
            if ($showinline == 0) {
                DB_query("UPDATE {$_TABLES['ff_attachments']} SET show_inline = 1 WHERE id=".(int)$id);
            }
            $forumfiles[$i] = $id;   // used to track attachments used inline and reset others in case user is changing them
            $fileparts = explode(':',$fileinfo);
            $pos = strrpos($fileparts[0],'.');
            $filename = substr($fileparts[0], 0,$pos);
            $ext = substr($fileparts[0], $pos+1);
            if ($repository_id > 0) {
                $srcImage = "{$filemgmt_FileStoreURL}/{$filename}.{$ext}";
            } else {
                $srcImage = "{$_FF_CONF['downloadURL']}/{$filename}.{$ext}";
            }

            if (file_exists("{$_FF_CONF['uploadpath']}/tn/{$filename}.{$ext}")) {
                $srcThumbnail = "{$_FF_CONF['downloadURL']}/tn/{$filename}.{$ext}";
            } else {
                if (file_exists("{$_CONF['path_html']}/forum/images/icons/{$ext}.gif")) {
                    $srcThumbnail = "{$_CONF['site_url']}/forum/images/icons/{$ext}.gif";
                } else {
                    $srcThumbnail = "{$_CONF['site_url']}/forum/images/icons/none.gif";
                }
            }
            if ( $lb == '' ) {
                $titletext = $LANG_GF10['click2download'];
            } else {
                $titletext = $LANG_GF10['click2view'];
            }
            $retval = '<a href="'.$srcImage.'" '.$lb.' target="_new"><img src="'. $srcThumbnail . '" '.$align.' style="padding:5px;" title="'.$titletext.'" alt="'.$titletext.'"/></a>';
            break;
         }
        $i++;
    }

    return $retval;

}

function do_bbcode_img ($action, $attributes, $content, $params, $node_object) {
    global $_FF_CONF;

    if ($action == 'validate') {
        if (isset($attributes['caption'])) {
            $node_object->setFlag('paragraph_type', BBCODE_PARAGRAPH_BLOCK_ELEMENT);
            if ($node_object->_parent->type() == STRINGPARSER_NODE_ROOT OR
                in_array($node_object->_parent->_codeInfo['content_type'], array('block', 'list', 'listitem'))) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    if ($_FF_CONF['allow_img_bbcode']) {
        if ( isset($attributes['h']) AND isset ($attributes['w']) ) {
            $dim = 'width=' . (int) $attributes['w'] . ' height=' . (int) $attributes['h'];
        } else {
            $dim = '';
        }
        if ( isset($attributes['align'] ) ) {
            if ( !in_array(strtolower($attributes['align']),array('left','right','center') ) ) {
                $attributes['align'] = 'left';
            }
            $align = ' align=' . $attributes['align'] . ' ';
        } else {
            $align = '';
        }
        $content = bbcode_cleanHTML($content);
        return '<img src="'.htmlspecialchars($content,ENT_QUOTES, COM_getEncodingt()).'" ' . $dim . $align . ' alt=""/>';
    } else {
        return '[img]' . bbcode_cleanHTML($content) . '[/img]';
    }
}

function do_bbcode_size  ($action, $attributes, $content, $params, $node_object) {
    if ( $action == 'validate') {
        return true;
    }
    return '<span style="font-size: '.(int) $attributes['default'].'px;">'.$content.'</span>';
}

function do_bbcode_color  ($action, $attributes, $content, $params, $node_object) {
    if ( $action == 'validate') {
        return true;
    }
    return '<span style="color: '. strip_tags($attributes['default']).';">'.$content.'</span>';
}

function do_bbcode_code($action, $attributes, $content, $params, $node_object) {
    global $_FF_CONF, $_ff_pm;

    if ( $action == 'validate') {
        return true;
    }
    if ($_FF_CONF['use_geshi']) {
        /* Support for formatting various code types : [code=java] for example */
        if (!isset ($attributes['default'])) {
            $codeblock = '</p>' . _ff_geshi_formatted($content) . '<p>';
        } else {
            $codeblock = '</p>' . _ff_geshi_formatted($content,strtoupper(strip_tags($attributes['default']))) . '<p>';
        }
    } else {
        $codeblock = '<pre class="codeblock">'  . $content . '</pre>';
    }

    $codeblock = str_replace('{','&#123;',$codeblock);
    $codeblock = str_replace('}','&#125;',$codeblock);

    if ( ($_FF_CONF['use_wysiwyg_editor'] == 1 && $_ff_pm != 'text') || $_ff_pm == 'html' ) {
        $codeblock = str_replace('&lt;','<',$codeblock);
        $codeblock = str_replace('&gt;','>',$codeblock);
        $codeblock = str_replace('&amp;','&',$codeblock);
        $codeblock = str_replace("<br /><br />","<br />",$codeblock);
        $codeblock = str_replace("<p>","",$codeblock);
        $codeblock = str_replace("</p>","",$codeblock);
    }

    return $codeblock;
}

/**
* Cleans (filters) HTML - only allows safe HTML tags
*
* @param        string      $str    string to filter
* @return       string      filtered HTML code
*/
function bbcode_cleanHTML($str) {
    global $_FF_CONF, $_CONF;

    $filter = sanitizer::getInstance();
    $AllowedElements = $filter->makeAllowedElements($_FF_CONF['allowed_html']);
    $filter->setAllowedelements($AllowedElements);
    $filter->setNamespace('forum','post');
    $filter->setPostmode('html');

    return $filter->filterHTML($str);
}

/* for display */
function FF_formatTextBlock($str,$postmode='html',$mode='',$status = 0, $query = '') {
    global $_CONF, $_FF_CONF, $_ff_pm;

    $bbcode = new StringParser_BBCode ();
    $bbcode->setGlobalCaseSensitive (false);
    $filter = sanitizer::getInstance();

    $status = (int) $status;

    if ($postmode == 'text' ) {
        $_ff_pm = 'text';
    } else {
        $_ff_pm = 'html';
    }
    $filter->setPostmode($postmode);

    if ( $postmode == 'text') {
        // filter all code prior to replacements
        $bbcode->addFilter(STRINGPARSER_FILTER_PRE, 'bbcode_htmlspecialchars');
    }
    $bbcode->addFilter(STRINGPARSER_FILTER_PRE, '_ff_fixmarkup');
    if ( $_FF_CONF['use_glfilter'] == 1 && ($postmode == 'html' || $postmode == 'HTML')) {
        $str = str_replace('<pre>','[code]',$str);
        $str = str_replace('</pre>','[/code]',$str);
    }
    if ( $postmode != 'html' && $postmode != 'HTML') {
        $bbcode->addParser(array('block','inline','link','listitem'), '_ff_nl2br');
    }

    if ( $query != '' ) {
        $filter->query = $query;
        $bbcode->addParser(array('block','inline','listitem'), array(&$filter,'highlightQuery'));
    }

    if ( ! ($status & DISABLE_SMILIES ) ) {
//        $bbcode->addFilter(STRINGPARSER_FILTER_PRE, '_ff_replacesmilie');      // calls replacesmilie on all text blocks
        $bbcode->addParser (array ('block', 'inline', 'listitem'), '_ff_replacesmilie');
    }

    if ( ! ($status & DISABLE_URLPARSE ) ) {
        $bbcode->addParser (array('block','inline','listitem'), array (&$filter, 'linkify'));
    }

    if ( ! ( $status & DISABLE_BBCODE ) ) {
        $bbcode->addParser ('list', 'bbcode_stripcontents');
        $bbcode->addCode ('code', 'usecontent?', 'do_bbcode_code', array ('usecontent_param' => 'default'),
                          'code', array('listitem', 'block', 'inline', 'quote'), array ('link'));

        $bbcode->addCode ('b', 'simple_replace', null, array ('start_tag' => '<b>', 'end_tag' => '</b>'),
                          'inline', array ('listitem', 'block', 'inline', 'link'), array ());
        $bbcode->addCode ('i', 'simple_replace', null, array ('start_tag' => '<i>', 'end_tag' => '</i>'),
                          'inline', array ('listitem', 'block', 'inline', 'link'), array ());
        $bbcode->addCode ('u', 'simple_replace', null, array ('start_tag' => '<span style="text-decoration: underline;">', 'end_tag' => '</span>'),
                          'inline', array ('listitem', 'block', 'inline', 'link'), array ());
        $bbcode->addCode ('p', 'simple_replace', null, array ('start_tag' => '<p>', 'end_tag' => '</p>'),
                          'inline', array ('listitem', 'block', 'inline', 'link'), array ());
        $bbcode->addCode ('s', 'simple_replace', null, array ('start_tag' => '<del>', 'end_tag' => '</del>'),
                          'inline', array ('listitem', 'block', 'inline', 'link'), array ());
        $bbcode->addCode ('size', 'callback_replace', 'do_bbcode_size', array('usecontent_param' => 'default'),
                          'inline', array ('listitem', 'block', 'inline', 'link'), array ());
        $bbcode->addCode ('color', 'callback_replace', 'do_bbcode_color', array ('usercontent_param' => 'default'),
                          'inline', array ('listitem', 'block', 'inline', 'link'), array ());
        $bbcode->addCode ('list', 'callback_replace', 'do_bbcode_list', array ('usecontent_param' => 'default'),
                          'list', array ('inline','block', 'listitem'), array ());
        $bbcode->addCode ('*', 'simple_replace', null, array ('start_tag' => '<li>', 'end_tag' => '</li>'),
                          'listitem', array ('list'), array ());
        if ($mode != 'noquote' ) {
            $bbcode->addCode ('quote','simple_replace',null,array('start_tag' => '</p><blockquote>', 'end_tag' => '</blockquote><p>'),
                              'inline', array('listitem','block','inline','link'), array());
        }
        $bbcode->addCode ('url', 'usecontent?', 'do_bbcode_url', array ('usecontent_param' => 'default'),
                          'link', array ('listitem', 'block', 'inline'), array ('link'));
        $bbcode->addCode ('img', 'usecontent', 'do_bbcode_img', array (),
                          'image', array ('listitem', 'block', 'inline', 'link'), array ());
        $bbcode->addCode ('file', 'usecontent', 'do_bbcode_file', array (),
                          'image', array ('listitem', 'block', 'inline', 'link'), array ());
        $bbcode->setCodeFlag ('quote', 'paragraph_type', BBCODE_PARAGRAPH_ALLOW_INSIDE);
        $bbcode->setCodeFlag ('*', 'closetag', BBCODE_CLOSETAG_OPTIONAL);
        $bbcode->setCodeFlag ('*', 'paragraphs', true);
        $bbcode->setCodeFlag ('list', 'opentag.before.newline', BBCODE_NEWLINE_DROP);
        $bbcode->setCodeFlag ('list', 'closetag.before.newline', BBCODE_NEWLINE_DROP);
    }
    if ($mode != 'noquote' ) {
        $bbcode->addParser(array('block','inline','listitem'), '_ff_replacetags');
    }
    $bbcode->setRootParagraphHandling (true);

    if ($_FF_CONF['use_censor']) { // and $mode == 'preview') {
        $str = COM_checkWords($str);
    }
    $str = $bbcode->parse ($str);

    return $str;
}

function _ff_nl2br($str) {
    $str = str_replace(array("\r\n", "\r", "\n"), "<br>", $str);
    return $str;
}

function _ff_fixmarkup($str) {
    $str = str_replace(array("[/list]\r\n", "[/list]\r", "[/list]\n","[/list] \r\n", "[/list] \r", "[/list] \n"), "[/list]", $str);
    $str = str_replace(array("[/code]\r\n", "[/code]\r", "[/code]\n","[/code] \r\n", "[/code] \r", "[/code] \n"), "[/code]", $str);
    $str = str_replace(array("[quote]\r\n", "[quote]\r", "[quote]\n","[quote] \r\n", "[quote] \r", "[quote] \n"), "[quote]", $str);
    $str = str_replace(array("[/quote]\r\n", "[/quote]\r", "[/quote]\n","[/quote] \r\n", "[/quote] \r", "[/quote] \n"), "[/quote]", $str);
    $str = str_replace(array("[QUOTE]\r\n", "[QUOTE]\r", "[QUOTE]\n","[QUOTE] \r\n", "[QUOTE] \r", "[QUOTE] \n"), "[QUOTE]", $str);
    $str = str_replace(array("[/QUOTE]\r\n", "[/QUOTE]\r", "[/QUOTE]\n","[/QUOTE] \r\n", "[/QUOTE] \r", "[/QUOTE] \n"), "[/QUOTE]", $str);

    return $str;
}


function FF_getSignature( $tagline, $signature, $postmode = 'html'  )
{
    global $_CONF, $_FF_CONF, $_TABLES;

    USES_lib_bbcode();

    $retval = '';
    $sig    = '';

    if ( $_FF_CONF['bbcode_signature'] && $signature != '') {
        if ( $_FF_CONF['allow_img_bbcode'] != true ) {
            $exclude = array('img');
        } else {
            $exclude = array();
        }
        $retval = '<div class="signature">'.BBC_formatTextBlock( $signature, 'text',array(),array(), $exclude).'</div><div style="clear:both;"></div>';
    } else {
        if (!empty ($tagline)) {
            if ( $postmode == 'html' ) {
                $retval = nl2br($tagline);
            } else {
                $retval = nl2br($tagline);
            }
            $retval = '<strong>'.$retval.'</strong>';
        }
    }

    return $retval;
}

function _ff_geshi_formatted($str,$type='php') {
    global $_CONF, $_FF_CONF, $LANG_GF01;

    $str = @htmlspecialchars_decode($str,ENT_QUOTES);
    $str = preg_replace('/^\s*?\n|\s*?\n$/','',$str);
    $geshi = new GeSHi($str,$type);
    $geshi->set_encoding(COM_getEncodingt());
    $geshi->set_header_type(GESHI_HEADER_DIV);
    if ( $_CONF['open_ext_url_new_window'] && $_CONF['open_ext_url_new_window'] == true ) {
        $geshi->set_link_target(true);
    }
    if ( isset($_FF_CONF['geshi_line_numbers']) && $_FF_CONF['geshi_line_numbers']) {
        $geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
    } else {
        $geshi->enable_line_numbers(GESHI_NO_LINE_NUMBERS);
    }
    $geshi->enable_keyword_links(false);
    if ( isset($_FF_CONF['geshi_overall_style']) ) {
        $geshi->set_overall_style($_FF_CONF['geshi_overall_style'],true);
    } else {
        $geshi->set_overall_style('font-size: 12px; color: #000066; border: 1px solid #d0d0d0; background-color: #FAFAFA;', true);
    }
    if ( isset($_FF_CONF['geshi_line_style'] ) ) {
        $geshi->set_line_style($_FF_CONF['geshi_line_style'],true);
    } else {
        $geshi->set_line_style('font: normal normal 95% \'Courier New\', Courier, monospace; color: #003030;', 'font-weight: bold; color: #006060;', true);
    }
    if ( isset($_FF_CONF['geshi_code_style'] ) ) {
        $geshi->set_code_style($_FF_CONF['geshi_code_style'],true);
    } else {
        $geshi->set_code_style('color: #000020;', 'color: #000020;');
    }
    $geshi->set_link_styles(GESHI_LINK, 'color: #000060;');
    $geshi->set_link_styles(GESHI_HOVER, 'background-color: #f0f000;');
    $geshi->set_header_content(strtoupper($type) . " " . $LANG_GF01['formatted_code']);
    if ( isset($_FF_CONF['geshi_header_style'] ) ) {
        $geshi->set_header_content_style($_FF_CONF['geshi_header_style'],true);
    } else {
        $geshi->set_header_content_style('font-family: Verdana, Arial, sans-serif; color: #fff; font-size: 90%; font-weight: bold; background-color: #325482; border-bottom: 1px solid #d0d0d0; padding: 2px;');
    }
    return $geshi->parse_code();
}

function _ff_FormatForEmail( $str, $postmode='html' ) {
    global $_CONF, $_FF_CONF;

    $_FF_CONF['use_geshi']     = true;
    $_FF_CONF['allow_smilies'] = false;
    $str = FF_formatTextBlock($str,$postmode,'text');

    $str = str_replace('<img src="' . $_CONF['site_url'] . '/forum/images/img_quote.gif" alt=""/>','',$str);

    // we don't have a stylesheet for email, so replace our div with the style...
    $str = str_replace('<div class="quotemain">','<div style="border: 1px dotted #000;border-left: 4px solid #8394B2;color:#465584;  padding: 4px;  margin: 5px auto 8px auto;">',$str);
    return $str;
}

function gfm_getoutput( $id, $type = 'digest' ) {
    global $_TABLES,$LANG_GF01,$LANG_GF02,$_CONF,$_FF_CONF,$_USER;

    $dt = new Date('now',$_USER['tzid']);

    $id = COM_applyFilter($id,true);
    $result = DB_query("SELECT * FROM {$_TABLES['ff_topic']} WHERE id=".(int) $id);
    $A = DB_fetchArray($result);

    $forum_name = DB_getItem($_TABLES['ff_forums'],'forum_name',"forum_id=". (int) $A['forum']);

    if ( $A['pid'] == 0 ) {
        $pid = $id;
    } else {
        $pid = $A['pid'];
    }
    $permalink = $_CONF['site_url'].'/forum/viewtopic.php?topic='.$id.'#'.$id;
    $A['name'] = COM_checkWords($A['name']);
    $A['name'] = @htmlspecialchars($A['name'],ENT_QUOTES, COM_getEncodingt());
    $A['subject'] = COM_checkWords($A['subject']);
    $A['subject'] = @htmlspecialchars($A["subject"],ENT_QUOTES, COM_getEncodingt());
    $A['comment'] = _ff_FormatForEmail( $A['comment'], $A['postmode'] );
    $notifymsg = sprintf($LANG_GF02['msg27'],'<a href="'.$_CONF['site_url'].'/forum/notify.php">'.$_CONF['site_url'].'/forum/notify.php</a>');
    $dt->setTimestamp($A['date']);
    $date = $dt->format($_CONF['date'],true);
    if ($A['pid'] == '0') {
        $postid = $A['id'];
    } else {
        $postid = $A['pid'];
    }
    $T = new Template($_CONF['path'] . 'plugins/forum/templates');
    $T->set_file ('email', 'notifymessage.thtml');

    $T->set_var(array(
        'post_id'       => $postid,
        'topic_id'      => $A['id'],
        'post_subject'  => $A['subject'],
        'post_date'     => $date,
        'post_name'     => $A['name'],
        'notify_msg'    => $notifymsg,
        'site_name'     => $_CONF['site_name'],
        'online_version' => sprintf($LANG_GF02['view_online'],$permalink),
        'permalink'     => $permalink,
        'forum_name'    => $forum_name,
    ));

    if ( $type == 'digest' ) {
        $T->set_var('post_comment',$A['comment']);
    } else {
        $notify_msg = sprintf($LANG_GF02['html_notify_message'],
            $A['subject'],$A['name'],$forum_name,$_CONF['site_name'],$permalink,$A['subject']);
        $T->set_var('post_notify', $notify_msg);
        $T->unset_var('post_comment');
    }

    $T->parse('output','email');
    $message = $T->finish($T->get_var('output'));

    $T = new Template($_CONF['path'] . 'plugins/forum/templates');
    $T->set_file ('email', 'notifymessage_text.thtml');

    $T->set_var(array(
        'post_id'       => $postid,
        'topic_id'      => $A['id'],
        'post_subject'  => $A['subject'],
        'post_date'     => $date,
        'post_name'     => $A['name'],
        'notify_msg'    => $notifymsg,
        'site_name'     => $_CONF['site_name'],
        'online_version' => sprintf($LANG_GF02['view_online'],$_CONF['site_url'].'/forum/viewtopic.php?showtopic='.$postid.'&lastpost=true#'.$A['id']),
        'permalink'     => $permalink,
        'forum_name'    => $forum_name,
    ));

    if ( $type == 'digest' ) {
        $T->set_var('post_comment',$A['comment']);
    } else {
        $notify_msg = sprintf($LANG_GF02['text_notify_message'],
            $A['subject'],$A['name'],$forum_name,$_CONF['site_name'],$permalink);
        $T->set_var('post_notify', $notify_msg);
        $T->unset_var('post_comment');
    }

    $T->parse('output','email');
    $msgText = $T->finish($T->get_var('output'));

    $html2txt = new Html2Text\Html2Text($msgText,false);

    $messageText = $html2txt->get_text();
    return array($message,$messageText);
}

function _ff_checkHTMLforSQL($str,$postmode='html') {
    global $_FF_CONF;

    $bbcode = new StringParser_BBCode ();
    $bbcode->setGlobalCaseSensitive (false);

    if ( $postmode == 'html' || $postmode == 'HTML') {
        $bbcode->addParser(array('block','inline'), '_ff_cleanHTML');
    }
    $bbcode->addCode ('code', 'simple_replace', null, array ('start_tag' => '[code]', 'end_tag' => '[/code]'),
                      'code', array ('listitem', 'block', 'inline', 'link'), array ());
    $str = $bbcode->parse ($str);
    return $str;
}

/**
* Cleans (filters) HTML - only allows HTML tags specified in the
* $_FF_CONF['allowed_html'] string.  This function is designed to be called
* by the stringparser class to filter everything except [code] blocks.
*
* @param        string      $message        The topic post to filter
* @return       string      filtered HTML code
*/
function _ff_cleanHTML($message) {
    global $_CONF, $_FF_CONF;

    $filter = sanitizer::getInstance();
    $AllowedElements = $filter->makeAllowedElements($_FF_CONF['allowed_html']);
    $filter->setAllowedelements($AllowedElements);
    $filter->setNamespace('forum','post');
    $filter->setPostmode('html');
    return $filter->filterHTML($message);
}

function _ff_fixtemplate($text) {
    $text = str_replace('{','&#123;',$text);
    $text = str_replace('}','&#125;',$text);

    return $text;
}

function _ff_replaceTags($text) {
    return PLG_replaceTags($text,'forum','post');
}

function _ff_preparefordb($message,$postmode) {
    global $_FF_CONF, $_CONF;

    if ( $postmode == 'html' || $postmode == 'HTML' ) {
        $message = _ff_checkHTMLforSQL($message,$postmode);
    }

    if ($_FF_CONF['use_censor']) {
        $message = COM_checkWords($message);
    }
    $filter = sanitizer::getInstance();
    $message = $filter->prepareForDB($message);

    return $message;
}

function _ff_replacesmilie($str) {
    global $_CONF,$_TABLES,$_FF_CONF;

    if($_FF_CONF['allow_smilies']) {
        if (function_exists('msg_showsmilies') AND $_FF_CONF['use_smilies_plugin']) {
            $str = msg_replaceEmoticons($str);
        } else {
            $str = forum_xchsmilies($str);
        }
    }

    return $str;
}

function _ff_showattachments($topic,$mode='') {
    global $_TABLES,$_CONF,$_FF_CONF;

    $retval = '';
    $sql = "SELECT id,repository_id,filename FROM {$_TABLES['ff_attachments']} WHERE topic_id=".(int) $topic." ";
    if ($mode != 'edit') {
        $sql .= "AND show_inline=0 ";
    }
    $sql .= "ORDER BY id";
    $query = DB_query($sql);

    // Check and see if the filemgmt plugin is installed and enabled
    if (function_exists('filemgmt_buildAccessSql')) {
        $filemgmtSupport = true;
    } else {
        $filemgmtSupport = false;
    }

    while (list($id,$lid,$field_value) =  DB_fetchArray($query)) {
        $retval .= '<div class="tblforumfile">';
        $filename = explode(':',$field_value);
        if ($filemgmtSupport AND $lid > 0) {   // Check and see if user has access to file
            $groupsql = filemgmt_buildAccessSql();
            $sql = "SELECT COUNT(*) FROM {$_TABLES['filemgmt_filedetail']} a ";
            $sql .= "LEFT JOIN {$_TABLES['filemgmt_cat']} b ON a.cid=b.cid ";
            $sql .= "WHERE a.lid='$lid' $groupsql";
            list($testaccess_cnt) = DB_fetchArray(DB_query($sql));
        }
        if ($lid > 0 AND (!$filemgmtSupport OR $testaccess_cnt == 0 OR DB_count($_TABLES['filemgmt_filedetail'],"lid",$lid ) == 0)) {
            $retval .= "<img src=\"{$_CONF['site_url']}/forum/images/document_sm.gif\" border=\"0\" alt=\"\"/>Insufficent Access";
        } elseif (!empty($field_value)) {
            $retval .= "<img src=\"{$_CONF['site_url']}/forum/images/document_sm.gif\" border=\"0\" alt=\"\"/>";
            $retval .= "<a href=\"{$_CONF['site_url']}/forum/getattachment.php?id=$id\" target=\"_new\">";
            $retval .= "{$filename[1]}</a>&nbsp;";
            if ($mode == 'edit') {
                $retval .= "<a href=\"#\" onclick='ajaxDeleteFile($topic,$id);'>";
                $retval .= "<img src=\"{$_CONF['site_url']}/forum/images/delete.gif\" border=\"0\" alt=\"\"/></a>";
            }
        } else {
            $retval .= 'N/A&nbsp;';
        }
        $retval .= '</div>';
    }
    return $retval;
}


function forum_pagination( $base_url, $curpage, $num_pages,
                                  $page_str='page=', $do_rewrite=false, $msg='',
                                  $open_ended = '',$suffix='')
{
    global $_CONF, $LANG05;

    $retval = '';

    $output = outputHandler::getInstance();

    if ( $num_pages < 2 ) {
        return $retval;
    }

    $T = new Template($_CONF['path'] . 'plugins/forum/templates');
    $T->set_file('pagination','pagination.thtml');

    if ( !$do_rewrite ) {
        $hasargs = strstr( $base_url, '?' );
        if ( $hasargs ) {
            $sep = '&amp;';
        } else {
            $sep = '?';
        }
    } else {
        $sep = '/';
        $page_str = '';
    }

    if ( $curpage > 1 ) {
        $T->set_var('first',true);
        $T->set_var('first_link',$base_url . $sep . $page_str . '1' . $suffix);
        $pg = $sep . $page_str . ( $curpage - 1 );
        $T->set_var('prev',true);
        $T->set_var('prev_link',$base_url . $pg . $suffix);
        $output->addLink('prev', urldecode($base_url . $pg . $suffix));
    } else {
        $T->unset_var('first');
        $T->unset_var('first_link');
        $T->unset_var('prev');
        $T->unset_var('prev_link');
    }

    $T->set_block('pagination', 'datarow', 'datavar');

    if ( $curpage == 1 ) {
        $T->set_var('page_str','1');
        $T->set_var('page_link','#');
        $T->set_var('disabled',true);
        $T->set_var('active',true);
        $T->parse('datavar', 'datarow',true);
        $T->unset_var('active');
        $T->unset_var('disabled');
    } else {
        $T->set_var('page_str','1');
        $pg = $sep . $page_str . 1;
        $T->set_var('page_link',$base_url . $pg . $suffix);
        $T->parse('datavar', 'datarow',true);
    }

    if ( $num_pages > 5 ) {
        $start_cnt = min(max(1, $curpage - 4), $num_pages - 5);
        $end_cnt = max(min($num_pages,$curpage + 2), 6);
        if ( $start_cnt > 1 ) {
            $T->set_var('page_str','...');
            $T->set_var('page_link','#');
            $T->set_var('disabled',true);
            $T->parse('datavar', 'datarow',true);
        }

        for ( $i = ($start_cnt + 1); $i < $end_cnt; $i++ ) {
            if ( $i == $curpage ) {
                $T->set_var('page_str',$i);
                $T->set_var('page_link','#');
                $T->set_var('disabled',true);
                $T->set_var('active',true);
            } else {
                $T->set_var('page_str',$i);
                $pg = $sep . $page_str . $i;
                $T->set_var('page_link',$base_url . $pg . $suffix);
            }
            $T->parse('datavar', 'datarow',true);
            $T->unset_var('active');
            $T->unset_var('disabled');
        }
        if ( $end_cnt < $num_pages ) {
            $T->set_var('page_str','...');
            $T->set_var('page_link','#');
            $T->set_var('disabled',true);
            $T->parse('datavar', 'datarow',true);
        }
        if ( $curpage == $num_pages ) {
            $T->set_var('page_str',$num_pages);
            $T->set_var('page_link','#');
            $T->set_var('active',true);
        } else {
            $T->set_var('page_str',$num_pages);
            $pg = $sep . $page_str . $num_pages;
            $T->set_var('page_link',$base_url . $pg . $suffix);
        }
        $T->parse('datavar', 'datarow',true);
    } else {
        for( $pgcount = ( $curpage - 10 ); ( $pgcount <= ( $curpage + 9 )) AND ( $pgcount <= $num_pages ); $pgcount++ ) {
            if ( $pgcount <= 0 ) {
                $pgcount = 2;
            }
            if ( $pgcount == $curpage ) {
                $T->set_var('active',true);
                $T->set_var('page_str',$curpage);
            } else {
                $T->unset_var('active');
                $T->set_var('page_str',$pgcount);
                $pg = $sep . $page_str . $pgcount;
                $T->set_var('page_link',$base_url . $pg . $suffix);
            }
            $T->parse('datavar', 'datarow',true);
        }
    }
    if ( !empty( $open_ended )) {
        $T->set_var('open_ended',true);
    } else if ( $curpage == $num_pages ) {
        $T->unset_var('open_ended');
        $T->unset_var('next');
        $T->unset_var('last');
        $T->unset_var('next_link');
        $T->unset_var('last_link');
    } else {
        $T->set_var('next',true);
        $T->set_var('next_link',$base_url . $sep.$page_str . ($curpage + 1) . $suffix);
        $T->set_var('last',true);
        $T->set_var('last_link',$base_url . $sep.$page_str . $num_pages . $suffix);
        $output->addLink('next', urldecode($base_url . $sep. $page_str . ($curpage + 1) . $suffix));
    }
    if (!empty($msg) ) {
        $T->set_var('msg',$msg);
    }

    $retval = $T->finish ($T->parse('output','pagination'));
    return $retval;
}
?>

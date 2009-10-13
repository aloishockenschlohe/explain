<?php
    if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/** Explain Terms and Definitions
 
    It works like acronym.conf, but for any term (even with more than
    one word).
 
    Evaluates conf/explain.conf which is in the following syntax:
 
      [WHITESPACE]term TAB explanation TAB link [ TAB link ]
 
    WHITESPACE:  If term starts with a whitespace character (Tab, Space, …),
                 it is considered case-sensitive
    term:        regular expression of the term to explain
    explanation: a short description of the term
    link:        link as URL or wiki syntax (A:B:C) to the definition
 
    License: GPL
    */
class syntax_plugin_explain extends DokuWiki_Syntax_Plugin {

  function getInfo() {
    return array('author' => 'Marc Wäckerlin',
                 'email'  => 'marc [at] waeckerlin [dot-org]',
                 'name'   => 'Explain',
                 'desc'   => 'Explain terms',
                 'url'    => 'http://marc.waeckerlin.org');
  }

  function getType() {
    return 'substition';
  }

  function getSort() {
    return 239; // before 'acronym'
  }
 
  function syntax_plugin_explain() {
    // "static" not allowed in PHP4?!?
    //if (isset($keys[0]) return; // evaluate at most once
    $lines = @file(DOKU_CONF.'explain.conf');
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
      $i = (trim(mb_substr($line, 0, 1)) !== '');
      $line = trim($line);
      if (empty($line)) continue;
      $parts = explode("\t", $line);
      if ($i) $parts[0] = utf8_strtolower($parts[0]);
      $this->map[$parts[0]] = array('desc'   => $parts[1],
                                    'target' => $this->link(array_slice($parts, 2)),
                                    'i'      => $i);
    }
  }
 
  function link($targets) {
    foreach($targets as $target) {
	  $_ret = $this->_link($target);
      if ($_ret !== '') {
        break;
      }
    }
    return $_ret;
  }

  function _link($target) {
    /* Match an URL. */
    static $url = '^https?://';
    // '^(http://)?[-_[:alnum:]]+[-_.[:alnum:]]*\.[a-z]{2}'
    // '(/[-_./[:alnum:]&%?=#]*)?';
    if (ereg($url, $target))
      return $target;

    /* Match an internal link. */
    list($id, $hash) = split('#', $target, 2);
    global $ID;

	$_ret = '';
    if($ID != $id) {
      $_ret .= wl($id);
    }
    if($hash != '') {
      $_ret .= '#'.$hash;
    }
    return $_ret;
  }
 
  function connectTo($mode) {
    if (count($this->map) === 0)
      return;

    $re = '(?<=^|\W)(?i:'.
          join('|', array_map('preg_quote_cb', array_keys($this->map))).
          ')(?=\W|$)';

    $this->Lexer->addSpecialPattern($re, $mode, 'plugin_explain');
  }
 
  function handle($match, $state, $pos, &$handler) {
    /* Supply the matched text in any case. */
    $data = array('content' => $match);
    foreach (array_keys($this->map) as $rxmatch) {
      if ($match === $rxmatch ||
          ($this->map[$rxmatch]['i'] && utf8_strtolower($match) === $rxmatch)) {
        $data += $this->map[$rxmatch];
	    /* Handle only the first occurrence. */
	    unset($this->map[$rxmatch]['desc']);
        break;
      }
    }
	return $data;
  }
 
  function render($format, &$renderer, $data) {
    if(is_null($data['desc'])) {
      $renderer->doc .= hsc($data['content']);
      return true;
    }
    $renderer->doc .= '<a class="explain"';
    if(($data['target']) !== '') {
      $renderer->doc .= ' href="' . hsc($data['target']) . '"';
    }
    $renderer->doc .= '>' . hsc($data['content']);
    if ($data['desc'] !== '') {
      $renderer->doc .= '<span class="tooltip">'.hsc($data['desc']).'</span>';
    }
    $renderer->doc .= '</a>';
    return true;
  }
}

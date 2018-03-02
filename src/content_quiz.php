<?php
/**
* Quiz Content Page
*
* @copyright 2002-2007 by papaya Software GmbH - All rights reserved.
* @link http://www.papaya-cms.com/
* @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License, version 2
*
* You can redistribute and/or modify this script under the terms of the GNU General Public
* License (GPL) version 2, provided that the copyright and license notes, including these
* lines, remain unmodified. papaya is distributed in the hope that it will be useful, but
* WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
* FOR A PARTICULAR PURPOSE.
*
* @package Papaya-Modules
* @subpackage Free-Quiz
* @version $Id: content_quiz.php 39562 2014-03-14 15:45:29Z weinert $
*/

/**
* Quiz Content Page
*
* @package Papaya-Modules
* @subpackage Free-Quiz
*/
class content_quiz extends base_content {
  /**
  * edit fields
  * @var array $editFields
  */
  var $editFields = array(
    'nl2br' => array('Automatic linebreak', 'isNum', FALSE, 'translatedcombo',
      array(0 => 'Yes', 1 => 'No'),
      'Papaya will apply your linebreaks to the output page.',
      0),
    'title' => array('Title', 'isNoHTML', TRUE, 'input', 255, '', ''),
    'teaser' => array('Teaser', 'isSomeText', FALSE, 'simplerichtext', 5, '', ''),
    'quiz' => array('Quiz', 'isNoHTML', TRUE, 'function', 'getQuizCombo', '', ''),
  );

  /**
   * option fields
   * @var array $pluginOptionFields
   */
  var $pluginOptionFields = array(
    'storage_mode' => array(
      'Storage mode',
      'isNum',
      FALSE,
      'translatedcombo',
      array(0 => 'Session', 1 => 'Hidden Field'),
      '',
      0
    )
  );

  /**
   * @var base_quiz
   */
  public $quizObject;

  /**
  * Get parsed data
  *
  * @access public
  * @return string $result xml
  */
  public function getParsedData($parseParams = NULL) {
    $this->setDefaultData();
    $this->quizObject = new base_quiz();
    $this->quizObject->module = $this;
    $this->quizObject->initialize();
    $result = sprintf(
      '<title encoded="%s">%s</title>'.LF,
      rawurlencode($this->data['title']),
      papaya_strings::escapeHTMLChars($this->data['title'])
    );
    $result .= sprintf(
      '<teaser>%s</teaser>'.LF,
      $this->getXHTMLString($this->data['teaser'], !((bool)@$this->data['nl2br']))
    );
    $result .= $this->quizObject->getContentOutput($this, $this->data);
    return $result;
  }

  /**
  * Get parsed teaser
  *
  * @access public
  * @return string $result or ''
  */
  public function getParsedTeaser() {
    $this->setDefaultData();
    if (@trim($this->data['teaser']) != '') {
      $result = sprintf(
        '<title>%s</title>'.LF,
        papaya_strings::escapeHTMLChars($this->data['title'])
      );
      $result .= sprintf(
        '<text>%s</text>'.LF,
        $this->getXHTMLString($this->data['teaser'], !((bool)$this->data['nl2br']))
      );
      return $result;
    }
    return '';
  }

  /**
  * Get quiz combo for content selection
  *
  * @param string $name
  * @param array $field
  * @param array $data
  * @access public
  * @return string $result xml
  */
  public function getQuizCombo($name, $field, $data) {
    $lngId = $this->papaya()->administrationLanguage->id;
    $this->quizObject = new base_quiz('cqz'); // content quiz
    $this->quizObject->loadGroupTree($lngId);
    $result = sprintf(
      '<select name="%s[%s]" class="dialogSelect dialogScale">'.LF,
      papaya_strings::escapeHTMLChars($this->paramName),
      papaya_strings::escapeHTMLChars($name)
    );
    if (isset($this->quizObject->groups) && is_array($this->quizObject->groups)) {
      $result .= $this->getQuizComboSubTree(0, 0, $data);
    }
    $result .= '</select>'.LF;
    return $result;
  }

  /**
  * Get quiz combo sub tree
  *
  * @param integer $parent
  * @param integer $indent
  * @param array $data
  * @access public
  * @return string $result xml
  */
  public function getQuizComboSubTree($parent, $indent, $data) {
    $result = '';
    if (isset($this->quizObject->groupTree[$parent]) &&
        is_array($this->quizObject->groupTree[$parent])) {
      foreach ($this->quizObject->groupTree[$parent] as $id) {
        $result .= $this->getQuizComboEntry($id, $indent, $data);
      }
    }
    return $result;
  }

  /**
  * Get quiz combo entry
  *
  * @param integer $id
  * @param integer $indent
  * @param array $data
  * @access public
  * @return string $result xml
  */
  public function getQuizComboEntry($id, $indent, $data) {
    $result = '';
    if (isset($this->quizObject->groups[$id]) && is_array($this->quizObject->groups[$id])) {
      $group = $this->quizObject->groups[$id];
      if ($indent > 0) {
        $indentString = "'".str_repeat('-', $indent).'->';
      } else {
        $indentString = '';
      }
      $title = $group['groupdetail_title'];
      if (empty($title)) {
        $title = '#'.$id.': '.(new PapayaUiStringTranslated('No title'));
      }
      $selected = ($data == $id) ? ' selected="selected"' : '';
      $result .= sprintf(
        '<option value="%d" %s>%s %s</option>'.LF,
        $id,
        $selected,
        papaya_strings::escapeHTMLChars($indentString),
        papaya_strings::escapeHTMLChars($title)
      );
      $result .= $this->getQuizComboSubTree($id, $indent + 1, $data);
    }
    return $result;
  }

  /**
  * Always prevent URL change as the redirect would delete POST data
  *
  * @param string $currentFilename
  * @param string $outputMode
  * @return boolean FALSE
  */
  public function checkURLFilename($currentFileName, $outputMode) {
    return FALSE;
  }
}


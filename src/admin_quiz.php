<?php
/**
* group administration
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
* @version $Id: admin_quiz.php 39818 2014-05-13 13:15:13Z weinert $
*/
use Papaya\UI;

/**
* group administration
*
* @package Papaya-Modules
* @subpackage Free-Quiz
*/
class admin_quiz extends base_quiz {

  private $assessment;
  private $assessments;
  private $dialogAssessment;

  /**
  * Topic parameter name
  * @var string $topicParamName
  */
  var $topicParamName = 'quiz';

  /**
  * Clipboard array
  * @var array $clipboard
  */
  var $clipboard = NULL;

  /**
   * @var base_module
   */
  public $module;

  /**
   * @var PapayaTemplate
   */
  public $layout;

  /**
   * @var array|PapayaUiImages
   */
  private $localImages = array();

  /**
   * @var base_dialog
   */
  private $dialogGroup;

  /**
   * @var base_dialog
   */
  private $dialogQuestion;

  /**
   * @var base_dialog
   */
  private $dialogAnswer;

  /**
  * Initial funktion for module
  *
  * @access public
  */
  function initialize() {
    $this->initializeParams();
    $this->sessionParams = $this->getSessionValue($this->sessionParamName);
    $this->initializeSessionParam('group_id', array('question_id', 'answer_id'));
    $this->initializeSessionParam('question_id', array('answer_id'));
    $this->initializeSessionParam('answer_id');
    $imagePath = 'module:'.$this->module->guid;
    $this->localImages = array(
      'question' => $imagePath."/question.svg",
      'question-add' => $imagePath."/question-add.svg",
      'question-delete' => $imagePath."/question-delete.svg",
      'answer' => $imagePath."/answer.svg",
      'answer-wrong' => $imagePath."/answer-wrong.svg",
      'answer-right' => $imagePath."/answer-right.svg",
      'answer-add' => $imagePath."/answer-add.svg",
      'answer-delete' => $imagePath."/answer-delete.svg"
    );
  }

  /**
  * Execute - basic function for handling parameters
  *
  * @access public
  */
  function execute() {
    if (isset($this->sessionParams['groupsopen']) &&
        is_array($this->sessionParams['groupsopen'])) {
      $this->groupsOpen = $this->sessionParams['groupsopen'];
    } else {
      $this->groupsOpen = array();
    }
    switch (@$this->params['cmd']) {

    case 'open':
      $this->groupsOpen[(int)$this->params['group_id']] = TRUE;
      break;

    case 'close':
      unset($this->groupsOpen[(int)$this->params['group_id']]);
      break;

    case 'add_group':
      if ($newId = $this->addGroup(@(int)$this->params['group_id'])) {
        $this->addMsg(MSG_INFO, $this->_gt('Group added.'));
        $this->groupsOpen[$this->params['group_id']] = TRUE;
        $this->params['group_id'] = $newId;
      } else {
        $this->addMsg(
          MSG_ERROR,
          $this->_gt('Database error! Changes not saved.')
        );
      }
      break;

    case 'create_group_detail':
      if (isset($this->params['group_id']) && $this->params['group_id'] > 0 &&
          $this->loadGroup($this->params['group_id'])) {
        $this->initializeGroupEditForm();
        if ($this->dialogGroup->modified()) {
          if ($this->dialogGroup->checkDialogInput()) {
            if ($this->createGroupDetail()) {
              unset($this->dialogGroup);
              $this->loadGroupDetail(
                $this->params['group_id'],
                $this->papaya()->administrationLanguage->id
              );
              $this->addMsg(MSG_INFO, $this->_gt('Group created.'));
            }
          }
        } else {
          $this->addMsg(MSG_INFO, sprintf($this->_gt('Nothing modified.')));
        }
      }
      break;

    case 'edit_group_detail':
      if (
          isset($this->params['group_id']) && $this->params['group_id'] > 0 &&
          $this->loadGroup($this->params['group_id']) &&
          $this->loadGroupDetail(
            $this->params['group_id'], $this->papaya()->administrationLanguage->id
          )
         ) {
        $this->initializeGroupEditForm();
        if ($this->dialogGroup->modified()) {
          if ($this->dialogGroup->checkDialogInput()) {
            if ($this->saveGroupDetail()) {
              unset($this->dialogGroup);
              $this->loadGroupDetail(
                $this->params['group_id'], $this->papaya()->administrationLanguage->id
              );
              $this->addMsg(MSG_INFO, $this->_gt('Group modified.'));
            }
          }
        } else {
          $this->addMsg(MSG_INFO, $this->_gt('Nothing modified.'));
        }
      }
      break;

    case 'del_group':
      $this->loadGroup($this->params['group_id']);
      if (isset($this->params['confirm_delete']) &&
          $this->params['confirm_delete']) {
        if ($this->groupExist($this->params['group_id'])) {
          if ($this->groupIsEmpty($this->params['group_id'])) {
            if ($this->deleteGroup($this->params['group_id'])) {
              $this->addMsg(MSG_INFO, $this->_gt('Group deleted.'));
              if ($this->groupExist($this->group['group_parent'])) {
                $this->params['group_id'] = $this->group['group_parent'];
                $this->loadGroup($this->params['group_id']);
                $this->loadGroupDetail(
                  $this->params['group_id'], $this->papaya()->administrationLanguage->id
                );
              } else {
                $this->params['group_id'] = 0;
                unset($this->group);
                unset($this->groupDetail);
              }
              $this->params['cmd'] = NULL;
            } else {
              $this->addMsg(
                MSG_ERROR,
                $this->_gt('Database error! Changes not saved.')
              );
            }
          } else {
            $this->addMsg(MSG_WARNING, $this->_gt('Group is not empty.'));
            $this->params['cmd'] = '';
          }
        } else {
          $this->addMsg(MSG_WARNING, $this->_gt('Group not found.'));
        }
      }
      break;

    case 'create_question':
      if (isset($this->params['group_id']) && $this->params['group_id'] > 0) {
        $this->initializeQuestionEditForm();
        if ($this->dialogQuestion->modified()) {
          if ($this->dialogQuestion->checkDialogInput()) {
            $check = $this->createQuestion();
            if ($check != FALSE) {
              unset($this->dialogQuestion);
              $this->params['question_id'] = $check;
              $this->loadQuestion($this->params['question_id']);
              $this->addMsg(MSG_INFO, $this->_gt('Question created.'));
            }
          }
        } else {
          $this->addMsg(MSG_INFO, $this->_gt('Nothing modified.'));
        }
      }
      break;

    case 'create_answer':
      if (isset($this->params['question_id']) &&
          $this->params['question_id'] > 0) {
        $this->initializeAnswerEditForm();
        if ($this->dialogAnswer->modified()) {
          if ($this->dialogAnswer->checkDialogInput()) {
            $check = $this->createAnswer();
            if ($check != FALSE) {
              unset($this->dialogAnswer);
              $this->params['answer_id'] = $check;
              $this->loadAnswer($this->params['answer_id']);
              $this->addMsg(MSG_INFO, $this->_gt('Answer created.'), $this->_gt('Answer'));
            }
          }
        } else {
          $this->addMsg(MSG_INFO, $this->_gt('Nothing modified.'));
        }
      }
      break;

    case 'edit_question':
      if (isset($this->params['group_id']) && $this->params['group_id'] > 0 &&
      $this->loadQuestion($this->params['question_id'])) {
        $this->initializeQuestionEditForm();
        if ($this->dialogQuestion->modified()) {
          if ($this->dialogQuestion->checkDialogInput()) {
            if ($this->saveQuestion()) {
              unset($this->dialogQuestion);
              $this->loadQuestion($this->params['question_id']);
              $this->addMsg(MSG_INFO, $this->_gt('Question modified.'));
            }
          }
        } else {
          $this->addMsg(MSG_INFO, $this->_gt('Nothing modified.'));
        }
      }
      break;

    case 'edit_answer':
      if (isset($this->params['question_id']) && $this->params['question_id'] > 0 &&
      $this->loadAnswer($this->params['answer_id'])) {
        $this->initializeAnswerEditForm();
        if ($this->dialogAnswer->modified()) {
          if ($this->dialogAnswer->checkDialogInput()) {
            if ($this->saveAnswer()) {
              unset($this->dialogAnswer);
              $this->loadAnswer($this->params['answer_id']);
              $this->addMsg(MSG_INFO, $this->_gt('Answer modified.'));
            }
          }
        } else {
          $this->addMsg(MSG_INFO, $this->_gt('Nothing modified.'));
        }
      }
      break;

    case 'del_question':
      if (isset($this->params['confirm_delete']) &&
          $this->params['confirm_delete']) {
        if ($this->deleteQuestion($this->params['question_id'])) {
          $this->addMsg(MSG_INFO, $this->_gt('Question deleted.'));
          unset($this->params['question_id']);
          unset($this->question);
          $this->params['cmd'] = NULL;
        } else {
          $this->addMsg(
            MSG_ERROR,
            $this->_gt('Database error! Changes not saved.')
          );
        }
      }
      break;

    case 'del_answer':
      if (isset($this->params['confirm_delete']) &&
          $this->params['confirm_delete']) {
        if ($this->deleteAnswer($this->params['answer_id'])) {
          $this->addMsg(MSG_INFO, $this->_gt('Answer deleted.'));
          unset($this->params['answer_id']);
          unset($this->answer);
          $this->params['cmd'] = NULL;
        } else {
          $this->addMsg(
            MSG_ERROR,
            $this->_gt('Database error! Changes not saved.')
          );
        }
      }
      break;

    case 'move_up_q':
      $this->loadQuestion($this->params['question_id']);
      $this->moveQ($this->getNextSmallerQ($this->question['question_number']));
      break;

    case 'move_down_q':
      $this->loadQuestion($this->params['question_id']);
      $this->moveQ($this->getNextBiggerQ($this->question['question_number']));
      break;

    case 'move_up_a':
      $this->loadAnswer($this->params['answer_id']);
      $this->moveA($this->getNextSmallerA($this->answer['answer_number']));
      break;

    case 'move_down_a':
      $this->loadAnswer($this->params['answer_id']);
      $this->moveA($this->getNextBiggerA($this->answer['answer_number']));
      break;

    case 'add_assessment' :
      if (isset($this->params['group_id']) && $this->params['group_id'] > 0) {
        $dialog = $this->getAssessmentEditForm();
        if (
          $dialog->execute() &&
          ($assessmentId = $this->createAssessment($dialog->data))
        ) {
          $this->papaya()->messages->displayInfo('Assessment added.');
          $this->assessment = $this->loadAssessment($assessmentId);
          $this->params['cmd'] = 'edit_assessment';
          $this->params['assessment_id'] = $assessmentId;
          $this->dialogAssessment = NULL;
        }
      }
      break;
    case 'edit_assessment' :
      if (
        isset($this->params['assessment_id']) &&
        $this->params['assessment_id'] > 0 &&
        ($this->assessment = $this->loadAssessment($this->params['assessment_id']))
      ) {
        $dialog = $this->getAssessmentEditForm();
        if (
          $dialog->execute() &&
          ($assessmentId = $this->saveAssessment($dialog->data))
        ) {
          $this->papaya()->messages->displayInfo('Assessment saved.');
        }
      };
      break;
    case 'delete_assessment' :
      if (
        isset($this->params['assessment_id']) &&
        $this->params['assessment_id'] > 0 &&
        ($this->assessment = $this->loadAssessment($this->params['assessment_id']))
      ) {
        $dialog = $this->getAssessmentDeleteForm();
        if ($dialog->execute() && $this->deleteAssessment($dialog->data['assessment_id'])) {
          $this->papaya()->messages->displayInfo('Assessment deleted.');
          $this->params['cmd'] = 'edit_group';
        }
      }
      break;
    }

    $this->sessionParams['groupsopen'] = $this->groupsOpen;
    $this->setSessionValue($this->sessionParamName, $this->sessionParams);
    $this->loadGroups($this->papaya()->administrationLanguage->id);
    if (isset($this->params['group_id']) && $this->params['group_id'] > 0) {
      $this->loadGroup($this->params['group_id']);

      $this->loadGroupDetail(
        $this->params['group_id'], $this->papaya()->administrationLanguage->id
      );

      $this->loadQuestionList(
        $this->params['group_id'], $this->papaya()->administrationLanguage->id
      );

      if (isset($this->params) && isset($this->params['question_id']) &&
      $this->params['question_id'] != 0) {

        $this->loadQuestion($this->params['question_id']);

        $this->loadAnswerList(
          $this->params['question_id'], $this->papaya()->administrationLanguage->id
        );

        if (isset($this->params) && isset($this->params['answer_id']) &&
        $this->params['answer_id'] != 0) {
          $this->loadAnswer($this->params['answer_id']);
        }
      }
      if (isset($this->params['assessment_id']) && $this->params['assessment_id'] > 0) {
        $this->assessment = $this->loadAssessment($this->params['assessment_id']);
      }
      $this->assessments = $this->loadAssessmentList($this->params['group_id'], $this->papaya()->administrationLanguage->id);
    }
  }

  /**
  * Get XML - XML for admin page
  *
  * @access public
  */
  function getXML() {
    if (is_object($this->layout)) {
      $this->getXMLButtons();
      $this->getXMLgroupTree();
      switch (@$this->params['cmd']) {
      case 'del_group':
        $this->getXMLDelGroupForm();
        $this->getXMLQuestionTree();
        $this->getXMLAssessmentTree();
        break;
      case 'add_question':
        $this->getXMLQuestionEditForm();
        $this->getXMLQuestionTree();
        $this->getXMLAssessmentTree();
        break;
      case 'del_question':
        $this->getXMLDelQuestionForm();
        $this->getXMLQuestionTree();
        $this->getXMLAnswerTree();
        $this->getXMLAssessmentTree();
        break;
      case 'add_answer':
        $this->getXMLAnswerEditForm();
        $this->getXMLQuestionTree();
        $this->getXMLAnswerTree();
        $this->getXMLAssessmentTree();
        break;
      case 'del_answer':
        $this->getXMLDelAnswerForm();
        $this->getXMLQuestionTree();
        $this->getXMLAnswerTree();
        $this->getXMLAssessmentTree();
        break;
      case 'add_assessment':
        $this->getXMLAssessmentEditForm();
        $this->getXMLQuestionTree();
        $this->getXMLAnswerTree();
        $this->getXMLAssessmentTree();
        break;
      case 'delete_assessment':
        $this->layout->add($this->getAssessmentDeleteForm()->getXML());
        $this->getXMLQuestionTree();
        $this->getXMLAnswerTree();
        $this->getXMLAssessmentTree();
        break;
      default:
        if (isset($this->params['assessment_id']) && $this->params['assessment_id'] > 0) {
          $this->getXMLAssessmentEditForm();
          $this->getXMLQuestionTree();
          $this->getXMLAnswerTree();
          $this->getXMLAssessmentTree();
        } elseif (isset($this->params['answer_id']) && $this->params['answer_id'] > 0) {
          $this->getXMLAnswerEditForm();
          $this->getXMLQuestionTree();
          $this->getXMLAnswerTree();
          $this->getXMLAssessmentTree();
        } elseif (isset($this->params['question_id']) && $this->params['question_id'] > 0) {
          $this->getXMLQuestionEditForm();
          $this->getXMLQuestionTree();
          $this->getXMLAnswerTree();
          $this->getXMLAssessmentTree();
        } elseif (isset($this->params['group_id']) && $this->params['group_id'] > 0) {
          $this->getXMLGroupEditForm();
          $this->getXMLQuestionTree();
          $this->getXMLAssessmentTree();
        }
      }
    }
  }

  /**
  * trade question sort-id
  *
  * @param integer $num
  * @access public
  * @return boolean
  */
  function moveQ($num) {
    $this->loadQuestion($this->params['question_id']);
    $dataTrans = array(
      'question_number' => $this->question['question_number']
    );
    $filter = array(
      'question_number' => $num
    );
    if (FALSE !== $this->databaseUpdateRecord($this->tableQuestion, $dataTrans, $filter)) {
      $dataTrans = array(
        'question_number' => $num
      );
      $filter = array(
        'question_id' => (int)$this->question['question_id']
      );
      if (FALSE !== $this->databaseUpdateRecord($this->tableQuestion, $dataTrans, $filter)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * Move answer sort-id
  *
  * @param integer $num
  * @access public
  * @return boolean
  */
  function moveA($num) {
    $this->loadQuestion($this->params['answer_id']);
    $dataTrans = array(
      'answer_number' => $this->answer['answer_number']
    );
    $filter = array(
      'answer_number' => $num
    );
    if (FALSE !== $this->databaseUpdateRecord($this->tableAnswer, $dataTrans, $filter)) {
      $dataTrans = array(
        'answer_number' => $num
      );
      $filter = array(
        'answer_id' => (int)$this->answer['answer_id']
      );
      if (FALSE !== $this->databaseUpdateRecord($this->tableAnswer, $dataTrans, $filter)) {
        return TRUE;
      }
    }
    return FALSE;
  }


  /**
  * Delete Group
  *
  * @param integer $groupId
  * @access public
  * @return boolean
  */
  function deleteGroup($groupId) {
    $questions = NULL;
    $sql = "SELECT DISTINCT question_id
              FROM %s
             WHERE group_id = '%d'";
    $params = array($this->tableQuestion, $groupId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      $questions[] = (int)$res->fetchField();
    }
    if (FALSE !== $this->databaseDeleteRecord($this->tableAnswer, 'question_id', $questions) &&
        FALSE !== $this->databaseDeleteRecord($this->tableQuestion, 'group_id', $groupId) &&
        FALSE !== $this->databaseDeleteRecord($this->tableGroupTrans, 'group_id', $groupId) &&
        FALSE !== $this->databaseDeleteRecord($this->tableGroup, 'group_id', $groupId) ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Delete Question
   *
   * @param int $questionId
   * @access public
   * @return boolean
   */
  function deleteQuestion($questionId) {
    if (FALSE !== $this->databaseDeleteRecord($this->tableAnswer, 'question_id', $questionId) &&
        FALSE !== $this->databaseDeleteRecord($this->tableQuestion, 'question_id', $questionId)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Delete answer
  *
  * @param integer $answerId
  * @access public
  * @return boolean
  */
  function deleteAnswer($answerId) {
    if (FALSE !== $this->databaseDeleteRecord($this->tableAnswer, 'answer_id', $answerId)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Delete group form
  *
  * @access public
  */
  function getXMLDelGroupForm() {
    if (isset($this->group) && is_array($this->group)) {
      $hidden = array(
        'cmd' => 'del_group',
        'group_id' => $this->group['group_id'],
        'confirm_delete' => 1
      );

      if (isset($this->groupDetail) && is_array($this->groupDetail)) {
        $title = $this->groupDetail['groupdetail_title'];
      } else {
        $title = $this->_gt('No title');
      }

      $msg = sprintf(
        $this->_gt('Delete group "%s" (%s)?'),
        $title,
        (int)$this->group['group_id']
      );

      $dialog = new base_msgdialog(
        $this, $this->paramName, $hidden, $msg, 'question'
      );
      $dialog->baseLink = $this->baseLink;
      $dialog->buttonTitle = 'Delete';
      $this->layout->add($dialog->getMsgDialog());
    }
  }

  /**
  * Delete question form
  *
  * @access public
  */
  function getXMLDelQuestionForm() {
    $hidden = array(
      'cmd' => 'del_question',
      'question_id' => $this->params['question_id'],
      'confirm_delete' => 1
    );

    $msg = sprintf(
      $this->_gt('Delete question "%s" (%s)?'),
      $this->question['question_title'],
      (int)$this->params['question_id']
    );

    $dialog = new base_msgdialog(
      $this, $this->paramName, $hidden, $msg, 'question'
    );
    $dialog->baseLink = $this->baseLink;
    $dialog->buttonTitle = 'Delete';
    $this->layout->add($dialog->getMsgDialog());
  }

  /**
  * generate form for delete question confirmation
  */
  function getXMLDelAnswerForm() {
    $hidden = array(
      'cmd' => 'del_answer',
      'answer_id' => $this->params['answer_id'],
      'confirm_delete' => 1
    );

    $this->loadAnswer($this->params['answer_id']);
    $msg = sprintf(
      $this->_gt('Delete answer "%s" (%s)?'),
      substr(strip_tags($this->answer['answer_text']), 0, 10),
      (int)$this->params['answer_id']
    );

    $dialog = new base_msgdialog(
      $this, $this->paramName, $hidden, $msg, 'question'
    );
    $dialog->baseLink = $this->baseLink;
    $dialog->buttonTitle = 'Delete';
    $this->layout->add($dialog->getMsgDialog());
  }

  /**
  * Add group
  *
  * @param integer $parent
  * @access public
  * @return mixed integer or boolean
  */
  function addGroup($parent) {
    $this->loadGroup($this->params['group_id']);
    if ($this->groupExist($parent) || ($parent == 0)) {
      if (isset($this->group) && is_array($this->group)) {
        $path = $this->group['group_parent_path'].$this->group['group_id'].';';
      } else {
        $path = ';0;';
      }
      $data = array(
        'group_parent' => @(int)$parent,
        'group_parent_path' => $path
      );
      unset($this->group);
      return $this->databaseInsertRecord($this->tableGroup, 'group_id', $data);
    }
    return FALSE;
  }

  /**
  * Group tree xml
  *
  * @access public
  */
  function getXMLGroupTree() {
    if (isset($this->groups) && is_array($this->groups)) {
      $result = sprintf(
        '<listview title="%s" >'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('Groups'))
      );
      $result .= '<items>'.LF;
      if (isset($this->params) && isset($this->params['group_id'])) {
        $selected = ($this->params['group_id'] == 0) ? ' selected="selected"' : '';
      } else {
        $selected = '';
      }
      $result .= sprintf(
        '<listitem href="%s" title="%s" image="%s" %s/>'.LF,
        papaya_strings::escapeHTMLChars(
          $this->getLink(array('group_id' => 0, 'question_id' => 0, 'answer_id' => 0))
        ),
        papaya_strings::escapeHTMLChars($this->_gt('Base')),
        papaya_strings::escapeHTMLChars($this->papaya()->images['places-desktop']),
        $selected
      );
      $result .= $this->getXMLGroupSubTree(0, 0);
      $result .= '</items>'.LF;
      $result .= '</listview>'.LF;
      $this->layout->addLeft($result);
    } else {
      $result = sprintf(
        '<listview title="%s" >'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('Groups'))
      );
      $result .= '<items>'.LF;
      $result .= sprintf(
        '<listitem href="%s" title="%s" image="%s" selected="selected"/>'.LF,
        papaya_strings::escapeHTMLChars(
          $this->getLink(array('group_id' => 0))
        ),
        papaya_strings::escapeHTMLChars($this->_gt('Base')),
        papaya_strings::escapeHTMLChars($this->papaya()->images['places-desktop'])
      );
      $result .= '</items>'.LF;
      $result .= '</listview>'.LF;
      $this->layout->addLeft($result);
    }
  }

  /**
  * Question tree xml
  *
  * @access public
  */
  function getXMLQuestionTree() {
    $i = 1;
    $result = sprintf(
      '<listview title="%s" >'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Questions'))
    );
    $result .= '<items>'.LF;
    if (isset($this->questions) && is_array($this->questions)) {
      foreach ($this->questions as $id => $value) {
        if (isset($this->params) && isset($this->params['question_id'])) {
          $selected = ($this->params['question_id'] == $id) ?
            ' selected="selected"' : '';
        } else {
          $selected = '';
        }
        $result .= sprintf(
          '<listitem href="%s" title="%s" image="%s" %s>'.LF,
          papaya_strings::escapeHTMLChars(
            $this->getLink(array('question_id' => $id, 'answer_id' => 0))
          ),
          papaya_strings::escapeHTMLChars($value['question_title']),
          papaya_strings::escapeHTMLChars($this->localImages['question']),
          $selected
        );
        if ($i != 1) {
          $result .= sprintf(
            '<subitem align="right"><a href="%s"><glyph src="%s"/></a></subitem>'.LF,
            papaya_strings::escapeHTMLChars(
              $this->getLink(array('cmd' => 'move_up_q', 'question_id' => $id))
            ),
            papaya_strings::escapeHTMLChars($this->papaya()->images['actions-go-up'])
          );
        } else {
          $result .= sprintf('<subitem/>');
        }
        if ($i < count($this->questions)) {
          $result .= sprintf(
            '<subitem align="right"><a href="%s"><glyph src="%s"/></a></subitem>'.LF,
            papaya_strings::escapeHTMLChars(
              $this->getLink(array('cmd' => 'move_down_q', 'question_id' => $id))
            ),
            papaya_strings::escapeHTMLChars($this->papaya()->images['actions-go-down'])
          );
        } else {
          $result .= sprintf('<subitem/>');
        }
        $result .= '</listitem>'.LF;
        $i++;
      }
    }
    $result .= '</items>'.LF;
    $result .= '</listview>'.LF;
    $this->layout->addRight($result);
  }

  /**
  * Get answer title
  *
  * @param string $str
  * @access public
  * @return string
  */
  function getAnswerTitle($str) {
    return substr(strip_tags($str), 0, 10);
  }

  /**
  * Answer tree
  *
  * @access public
  */
  function getXMLAnswerTree() {
    $i = 1;
    $result = sprintf(
      '<listview title="%s" >'.LF,
      papaya_strings::escapeHTMLChars($this->_gt('Answers'))
    );
    $result .= '<items>'.LF;
    if (isset($this->answers) && is_array($this->answers)) {
      foreach ($this->answers as $id => $answer) {
        if (isset($this->params) && isset($this->params['answer_id'])) {
          $selected = ($this->params['answer_id'] == $id) ?
            ' selected="selected"' : '';
        } else {
          $selected = '';
        }
        $image = $this->localImages['answer'];
        if ($this->groupDetail['groupdetail_mode'] === self::MODE_BOOLEAN) {
          $image = ($answer['answer_right'] > 0)
            ? $this->localImages['answer-right'] : $this->localImages['answer-wrong'];
        }
        $result .= sprintf(
          '<listitem href="%s" title="%s" image="%s" %s>'.LF,
          papaya_strings::escapeHTMLChars($this->getLink(array('answer_id' => $id))),
          papaya_strings::escapeHTMLChars($this->getAnswerTitle($answer['answer_text'])),
          papaya_strings::escapeHTMLChars($image),
          $selected
        );
        if ($this->groupDetail['groupdetail_mode'] === self::MODE_RATED) {
          $result .= sprintf(
            '<subitem align="right">%d</subitem>'.LF,
            papaya_strings::escapeHTMLChars($answer['answer_right'])
          );
        }
        if ($i != 1) {
          $result .= sprintf(
            '<subitem align="right"><a href="%s"><glyph src="%s"/></a></subitem>'.LF,
            papaya_strings::escapeHTMLChars(
              $this->getLink(array('cmd' => 'move_up_a', 'answer_id' => $id))
            ),
            papaya_strings::escapeHTMLChars(
              $this->papaya()->images['actions-go-up']
            )
          );
        } else {
          $result .= sprintf('<subitem/>');
        }
        if ($i < count($this->questions)) {
          $result .= sprintf(
            '<subitem align="right"><a href="%s"><glyph src="%s"/></a></subitem>'.LF,
            papaya_strings::escapeHTMLChars(
              $this->getLink(array('cmd' => 'move_down_a', 'answer_id' => $id))
            ),
            papaya_strings::escapeHTMLChars($this->papaya()->images['actions-go-down'])
          );
        } else {
          $result .= sprintf('<subitem/>');
        }
        $result .= '</listitem>'.LF;
        $i++;
      }
    }
    $result .= '</items>'.LF;
    $result .= '</listview>'.LF;
    $this->layout->addRight($result);
  }

  /**
  * Group subtree
  *
  * @param integer $parent
  * @param integer $indent
  * @access public
  * @return string xml
  */
  function getXMLGroupSubTree($parent, $indent) {
    $result = '';
    if (isset($this->groupTree[$parent]) && is_array($this->groupTree[$parent]) &&
        (isset($this->groupsOpen[$parent]) || ($parent == 0))) {
      foreach ($this->groupTree[$parent] as $id) {
        $result .= $this->getXMLgroupEntry($id, $indent);
      }
    }
    return $result;
  }

  /**
  * Group edit form
  *
  * @access public
  */
  function getXMLGroupEditForm() {
    if (isset($this->group)) {
      $this->initializeGroupEditForm();
      $this->layout->add($this->dialogGroup->getDialogXML());
    }
  }

  /**
  * Initialize group edit form
  *
  * @access public
  */
  function initializeGroupEditForm() {
    if (!(isset($this->dialogGroup) && is_object($this->dialogGroup))) {
      if (isset($this->groupDetail) && is_array($this->groupDetail)) {
        $data = $this->groupDetail;
        $hidden = array(
          'cmd' => 'edit_group_detail',
          'save' => 1,
          'group_id' => $this->group['group_id']
        );
        $btnCaption = 'Edit';
      } else {
        $data = array();
        $hidden = array(
           'cmd' => 'create_group_detail',
           'save' => 1,
           'group_id' => $this->group['group_id']
        );
        $btnCaption = 'Save';
      }
      $fields = array(
        'groupdetail_title' => array('Title', 'isNoHTML', TRUE, 'input', 250),
        'groupdetail_mode' => array('Mode', 'isNum', FALSE, 'translatedcombo', ['True/False', 'Rated']),
        'groupdetail_text' => array('Text', 'isSomeText', FALSE,
          'simplerichtext', 12)
      );
      $this->dialogGroup = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->dialogGroup->loadParams();
      $this->dialogGroup->baseLink = $this->baseLink;
      $this->dialogGroup->dialogTitle =
        papaya_strings::escapeHtmlChars($this->_gt('Properties'));
      $this->dialogGroup->buttonTitle = $btnCaption;
      $this->dialogGroup->dialogDoubleButtons = FALSE;
    }
  }

  /**
  * Question edit form
  *
  * @access public
  */
  function getXMLQuestionEditForm() {
    if (isset($this->group)) {
      $this->initializeQuestionEditForm();
      $this->layout->add($this->dialogQuestion->getDialogXML());
    }
  }

  /**
  * Answer edit form
  *
  * @access public
  */
  function getXMLAnswerEditForm() {
    if (isset($this->question)) {
      $this->initializeAnswerEditForm();
      $this->layout->add($this->dialogAnswer->getDialogXML());
    }
  }

  /**
  * Initialize Question edit forumular
  *
  * @access public
  */
  function initializeQuestionEditForm() {
    if (!(isset($this->dialogQuestion) && is_object($this->dialogQuestion))) {
      if (@$this->params['cmd'] != 'add_question') {
        $data = $this->question;
        $hidden = array(
          'cmd' => 'edit_question',
          'save' => 1,
          'group_id' => $this->params['group_id'],
          'question_id' => $this->params['question_id']
        );
        $btnCaption = 'Edit';
      } else {
        $data = array();
        $hidden = array(
           'cmd' => 'create_question',
           'save' => 1,
           'group_id' => $this->params['group_id']
        );
        $btnCaption = 'Save';
      }
      $fields = array(
        'question_title' => array('Title', 'isNoHTML', TRUE, 'input', 250),
        'question_text' => array('Text', 'isSomeText', FALSE, 'simplerichtext', 12),
        'question_link' => array('Link', 'isSomeText', FALSE, 'simplerichtext', 4)
      );
      $this->dialogQuestion = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->dialogQuestion->loadParams();
      $this->dialogQuestion->baseLink = $this->baseLink;
      $this->dialogQuestion->dialogTitle =
        papaya_strings::escapeHtmlChars($this->_gt('Properties'));
      $this->dialogQuestion->buttonTitle = $btnCaption;
      $this->dialogQuestion->dialogDoubleButtons = FALSE;
    }
  }

  /**
  * Initialize answer edit form
  *
  * @access public
  */
  function initializeAnswerEditForm() {
    if (!(isset($this->dialogAnswer) && is_object($this->dialogAnswer))) {
      if (@$this->params['cmd'] != 'add_answer') {
        $data = $this->answer;
        $hidden = array(
          'cmd' => 'edit_answer',
          'save' => 1,
          'group_id' => $this->params['group_id'],
          'question_id' => $this->params['question_id'],
          'answer_id' => $this->params['answer_id']
        );
        $btnCaption = 'Edit';
      } else {
        $data = array();
        $hidden = array(
           'cmd' => 'create_answer',
           'save' => 1,
           'question_id' => $this->params['question_id']
        );
        $btnCaption = 'Save';
      }
      if ($this->groupDetail['groupdetail_mode'] === self::MODE_RATED) {
        $fields = [
          'answer_right' => [
            'Rating points', 'isNum', TRUE, 'input', 3, 'Rating value in points for this answer'
          ]
        ];
      } else {
        $fields = [
          'answer_right' => [
            'Right/Wrong answer?', 'isNum', TRUE, 'combo', [1 => 'Right', 0 => 'Wrong'], 'Decide if right or wrong answer'
          ]
        ];
      }
      $fields = array_merge(
        $fields,
        [
          'answer_text' => [
            'Answer title', 'isSomeText', FALSE, 'input', 200
          ],
          'answer_explanation' => [
            'Explanation', 'isSomeText', FALSE, 'simplerichtext', 6
          ],
          'answer_response' => [
            'Answer response', 'isSomeText', FALSE, 'simplerichtext', 6
          ],
        ]
      );
      $this->dialogAnswer = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->dialogAnswer->loadParams();
      $this->dialogAnswer->baseLink = $this->baseLink;
      $this->dialogAnswer->dialogTitle = $this->_gt('Properties');
      $this->dialogAnswer->buttonTitle = $btnCaption;
      $this->dialogAnswer->dialogDoubleButtons = FALSE;
    }
  }

  /**
  * Save group detail
  *
  * @access public
  * @return boolean
  */
  function saveGroupDetail() {
    $dataTrans = array(
      'lng_id' => $this->papaya()->administrationLanguage->id,
      'groupdetail_title' => (string)$this->params['groupdetail_title'],
      'groupdetail_text' => (string)$this->params['groupdetail_text'],
      'groupdetail_mode' => (int)$this->params['groupdetail_mode']
    );
    $filter = array(
      'group_id' => (int)$this->params['group_id'],
      'lng_id' => $this->papaya()->administrationLanguage->id
    );
    if (FALSE !==
        $this->databaseUpdateRecord($this->tableGroupTrans, $dataTrans, $filter)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Save question
  *
  * @access public
  * @return boolean
  */
  function saveQuestion() {
    $dataTrans = array(
      'question_title' => $this->dialogQuestion->data['question_title'],
      'question_text' => $this->dialogQuestion->data['question_text'],
      'question_link' => $this->dialogQuestion->data['question_link'],
      'group_id' => (int)$this->params['group_id'],
      'lng_id' => $this->papaya()->administrationLanguage->id
    );
    $filter = array(
      'question_id' => $this->params['question_id']
    );
    if (FALSE !==
        $this->databaseUpdateRecord($this->tableQuestion, $dataTrans, $filter)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Save answer
  *
  * @access public
  * @return boolean
  */
  function saveAnswer() {
    $dataTrans = array(
      'answer_right' => $this->params['answer_right'],
      'answer_text' => $this->dialogAnswer->data['answer_text'],
      'answer_explanation' => $this->dialogAnswer->data['answer_explanation'],
      'answer_response' => $this->dialogAnswer->data['answer_response'],
      'question_id' => (int)$this->params['question_id'],
      'lng_id' => $this->papaya()->administrationLanguage->id
    );
    $filter = array(
      'answer_id' => $this->params['answer_id']
    );
    return FALSE !== $this->databaseUpdateRecord(
      $this->tableAnswer, $dataTrans, $filter
    );
  }

  /**
  * Create group detail
  *
  * @access public
  * @return boolean
  */
  function createGroupDetail() {
    $data = array(
      'group_id' => $this->params['group_id'],
      'lng_id' => $this->papaya()->administrationLanguage->id,
      'groupdetail_title' => (string)$this->params['groupdetail_title'],
      'groupdetail_text' => (string)$this->params['groupdetail_text'],
      'groupdetail_mode' => (int)$this->params['groupdetail_mode']
    );
    return FALSE !== $this->databaseInsertRecord(
      $this->tableGroupTrans, 'groupdetail_id', $data
    );
  }

  /**
  * Create question
  *
  * @access public
  * @return boolean
  */
  function createQuestion() {
    $num = 0;
    $sql = "SELECT MAX(question_number) AS max
              FROM %s ";
    $params = array($this->tableQuestion);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        if ($row['max'] != NULL) {
          $num = $row['max'];
        }
      }
    }
    $num++;
    $data = array(
      'group_id' => $this->params['group_id'],
      'lng_id' => $this->papaya()->administrationLanguage->id,
      'question_title' => $this->params['question_title'],
      'question_text' => $this->params['question_text'],
      'question_link' => $this->params['question_link'],
      'question_number' => $num
    );
    $ret = $this->databaseInsertRecord($this->tableQuestion, 'question_id', $data);
    if (FALSE !== $ret) {
      return $ret;
    } else {
      return FALSE;
    }
  }

  /**
  * Create answer
  *
  * @access public
  * @return boolean
  */
  function createAnswer() {
    $num = 0;
    $sql = "SELECT MAX(answer_number) AS max
              FROM %s ";
    $params = array($this->tableAnswer);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        if ($row['max'] != NULL) {
          $num = $row['max'];
        }
      }
    }
    $num++;
    $data = array(
      'question_id' => $this->params['question_id'],
      'lng_id' => $this->papaya()->administrationLanguage->id,
      'answer_text' => $this->dialogAnswer->data['answer_text'],
      'answer_explanation' => $this->dialogAnswer->data['answer_explanation'],
      'answer_response' => $this->dialogAnswer->data['answer_response'],
      'answer_right' => $this->params['answer_right'],
      'answer_number' => $num
    );
    return $this->databaseInsertRecord($this->tableAnswer, 'answer_id', $data);
  }

  /**
  * Group entry xml
  *
  * @param integer $id
  * @param integer $indent
  * @access public
  * @return string xml
  */
  function getXMLGroupEntry($id, $indent) {
    $result = '';
    if (isset($this->groups[$id]) && is_array($this->groups[$id])) {
      $opened = (bool)(
        isset($this->groupsOpen[$id]) && @$this->groups[$id]['CATEG_COUNT'] > 0
      );
      if (@$this->groups[$id]['CATEG_COUNT'] < 1) {
        $node = ' node="empty"';
        $imageIndex = 'items-folder';
      } elseif ($opened) {
        $nodeHref = $this->getLink(
          array(
            'cmd' => 'close',
            'group_id' => (int)$id,
            'question_id' => 0,
            'answer_id' => 0
          )
        );
        $node = sprintf(
          ' node="open" nhref="%s"',
          papaya_strings::escapeHTMLChars($nodeHref)
        );
        $imageIndex = 'status-folder-open';
      } else {
        $nodeHref = $this->getLink(
          array(
            'cmd' => 'open',
            'group_id' => (int)$id,
            'question_id' => 0, 'answer_id' => 0
          )
        );
        $node = sprintf(
          ' node="close" nhref="%s"',
          papaya_strings::escapeHTMLChars($nodeHref)
        );
        $imageIndex = 'items-folder';
      }
      if (!isset($this->groups[$id]) ||
          !isset($this->groups[$id]['groupdetail_title']) ||
          $this->groups[$id]['groupdetail_title'] == "") {
        $title = $this->_gt('No Title');
      } else {
        $title = $this->groups[$id]['groupdetail_title'];
      }

      if (isset($this->params) && isset($this->params['group_id'])) {
        $selected = ($this->params['group_id'] == $id) ? ' selected="selected"' : '';
      } else {
        $selected = '';
      }
      $result .= sprintf(
        '<listitem href="%s" title="%s" indent="%d" image="%s" %s %s/>'.LF,
        papaya_strings::escapeHTMLChars(
          $this->getLink(
            array(
              'group_id' => (int)$id,
              'question_id' => 0,
              'answer_id' => 0
            )
          )
        ),
        papaya_strings::escapeHTMLChars($title),
        (int)$indent,
        papaya_strings::escapeHTMLChars($this->papaya()->images[$imageIndex]),
        $node,
        $selected
      );
      $result .= $this->getXMLGroupSubTree($id, $indent + 1);

    }
    return $result;
  }

  /**
  * Get XML for buttons
  */
  function getXMLButtons() {
    $toolbar = new base_btnbuilder;
    $toolbar->images = $this->papaya()->images;
    $toolbar->addButton(
      'Check index',
      $this->getLink(array('cmd' => 'regenerate')),
      'actions-search'
    );

    $toolbar->addSeparator();

    $toolbar->addButton(
      'Add subgroup',
      $this->getLink(
        array(
          'cmd' => 'add_group',
          'group_id' => @(int)$this->params['group_id']
        )
      ),
      'actions-folder-child-add'
    );

    // NOTE : Side effects were caused by not checking parameters
    // to see if they were set to 0.  If the base button was clicked, then
    // a get string was generated that set group_id, question_id and answer_id
    // to 0.  This would bypass the ifs as params existed and was set, and so
    // were all the parameters, BUT NOT TO A MEANINGFUL VALUE, causing the
    // buttons for question and answer editing to be displayed.
    // SJFD 24.03.2009
    if (isset($this->params) && isset($this->params['group_id']) &&
    $this->params['group_id'] != 0 ) {
      $toolbar->addButton(
        'Add group',
        $this->getLink(
          array(
            'cmd' => 'add_group',
            'group_id' => @(int)$this->group['group_parent']
          )
        ),
        'actions-folder-add'
      );

      $toolbar->addButton(
        'Delete group',
        $this->getLink(
          array(
            'cmd' => 'del_group',
            'group_id' => @(int)$this->params['group_id']
          )
        ),
        'actions-folder-delete'
      );
      $toolbar->addSeparator();
    }

    if (isset($this->params['group_id']) && $this->params['group_id'] > 0) {
      $toolbar->addButton(
        'Add question',
        $this->getLink(
          array(
            'cmd' => 'add_question',
            'group_id' => @(int)$this->params['group_id']
          )
        ),
        $this->localImages['question-add']
      );

      if (isset($this->params['question_id']) && $this->params['question_id'] > 0) {
        $toolbar->addButton(
          'Delete question',
          $this->getLink(
            array(
              'cmd' => 'del_question',
              'question_id' => @(int)$this->params['question_id']
            )
          ),
          $this->localImages['question-delete']
        );

        $toolbar->addSeparator();

        $toolbar->addButton(
          'Add answer',
          $this->getLink(
            array(
              'cmd' => 'add_answer',
              'question_id' => @(int)$this->params['question_id']
            )
          ),
          $this->localImages['answer-add']
        );
        if (isset($this->params['answer_id']) && $this->params['answer_id'] > 0) {
          $toolbar->addButton(
            'Delete answer',
            $this->getLink(
              array(
                'cmd' => 'del_answer',
                'answer_id' => @(int)$this->params['answer_id']
              )
            ),
            $this->localImages['answer-delete']
          );
        }
      }
      $toolbar->addSeparator();
      $toolbar->addButton(
        'Add assessment',
        $this->getLink(
          array(
            'cmd' => 'add_assessment',
            'group_id' => @(int)$this->params['group_id'],
            'question_id' => 0,
            'answer_id' => 0
          )
        ),
        'icon.items.page.add'
      );
      if (isset($this->params['assessment_id']) && $this->params['assessment_id'] > 0) {
        $toolbar->addButton(
          'Delete assessment',
          $this->getLink(
            array(
              'cmd' => 'delete_assessment',
              'assessment_id' => @(int)$this->params['assessment_id'],
              'question_id' => 0,
              'answer_id' => 0
            )
          ),
          'icon.items.page.remove'
        );
      }
    }

    if ($str = $toolbar->getXML()) {
      $this->layout->addMenu(sprintf('<menu ident="edit">%s</menu>'.LF, $str));
    }
  }

  private function getXMLAssessmentTree() {
    if (is_array($this->assessments) && count($this->assessments) > 0) {
      $listView = new UI\ListView();
      $listView->caption = new UI\Text\Translated('Assessments');
      foreach ($this->assessments as $assessment) {
        $listView->items[] = $item = new UI\ListView\Item(
          'icon.items.page',
          \Papaya\Utility\Text::truncate($assessment['assessment_title'], 30),
          [
            $this->parameterGroup() => [
              'cmd' => 'edit_assessment',
              'group_id' => $assessment['group_id'],
              'assessment_id' => $assessment['assessment_id'],
              'question_id' => 0,
              'answer_id' => 0
            ]
          ]
        );
        if (isset($this->params['assessment_id']) && $this->params['assessment_id'] === $assessment['assessment_id']) {
          $item->selected = TRUE;
        }
        $item->subitems[] = $subItem = new Papaya\UI\ListView\SubItem\Text('>='.$assessment['assessment_rating']);
        $subItem->align = UI\Option\Align::RIGHT;
      }
      $this->layout->addRight($listView->getXML());
    }
  }

  private function getXMLAssessmentEditForm() {
    if (isset($this->group)) {
      $this->layout->add($this->getAssessmentEditForm()->getXML());
    }
  }

  private function getAssessmentEditForm() {
    if (!$this->dialogAssessment) {
      $this->dialogAssessment = $dialog = new UI\Dialog();
      $dialog->parameterGroup($this->parameterGroup());
      $dialog->options->topButtons = FALSE;
      if (isset($this->assessment) && is_array($this->assessment)) {
        $dialog->caption = new UI\Text\Translated('Edit assessment');
        $dialog->data->merge($this->assessment);
        $dialog->hiddenFields->merge(
          [
            'cmd' => 'edit_assessment',
            'group_id' => $this->assessment['group_id'],
            'assessment_id' => $this->assessment['assessment_id'],
            'question_id' => 0,
            'answer_id' => 0
          ]
        );
        $dialog->buttons[] = new UI\Dialog\Button\Submit(new UI\Text\Translated('Save'));
      } else {
        $dialog->caption = new UI\Text\Translated('Add assessment');
        $dialog->hiddenFields->merge(
          [
            'cmd' => 'add_assessment',
            'group_id' => $this->params['group_id'],
            'assessment_id' => 0,
            'question_id' => 0,
            'answer_id' => 0
          ]
        );
        $dialog->buttons[] = new UI\Dialog\Button\Submit(new UI\Text\Translated('Add'));
      }
      $dialog->fields[] = new UI\Dialog\Field\Input\Number(
        new UI\Text\Translated('Rating >='),
        'assessment_rating',
        0,
        TRUE,
        1,
        3
      );
      $dialog->fields[] = $field = new UI\Dialog\Field\Input(
        new UI\Text\Translated('Title'),
        'assessment_title',
        250,
        ''
      );
      $field->setMandatory(TRUE);
      $dialog->fields[] = new UI\Dialog\Field\Textarea\Richtext(
        new UI\Text\Translated('Text'),
        'assessment_text',
        10,
        '',
        new Papaya\Filter\NotEmpty()
      );
    }
    return $this->dialogAssessment;
  }

  private function createAssessment($data) {
    return $this->databaseInsertRecord(
      $this->databaseGetTableName(self::TABLE_ASSESSMENTS),
      'assessment_id',
      [
        'group_id' => $data['group_id'],
        'lng_id' => $this->papaya()->administrationLanguage->id,
        'assessment_rating' => $data['assessment_rating'],
        'assessment_title' => $data['assessment_title'],
        'assessment_text' => $data['assessment_text']
      ]
    );
  }

  private function saveAssessment($data) {
    return FALSE !== $this->databaseUpdateRecord(
      $this->databaseGetTableName(self::TABLE_ASSESSMENTS),
      [
        'assessment_rating' => $data['assessment_rating'],
        'assessment_title' => $data['assessment_title'],
        'assessment_text' => $data['assessment_text']
      ],
      [
        'assessment_id' => $data['assessment_id']
      ]
    );
  }

  private function deleteAssessment($data) {
    return FALSE !== $this->databaseDeleteRecord(
      $this->databaseGetTableName(self::TABLE_ASSESSMENTS),
      [
        'assessment_id' => $data['assessment_id']
      ]
    );
  }

  private function loadAssessment($assessmentId) {
    $statement = new Papaya\Database\Statement\Prepared(
      $this->getDatabaseAccess(),
      'SELECT 
        assessment_id, group_id, lng_id, assessment_rating, assessment_title, assessment_text
        FROM :assessments WHERE assessment_id = :id'
    );
    $statement->addTableName('assessments', self::TABLE_ASSESSMENTS);
    $statement->addInt('id', (int)$assessmentId);
    if ($result = $this->databaseQuery($statement)) {
      return $result->fetchRow(DB_FETCHMODE_ASSOC);
    };
    return FALSE;
  }

  private function loadAssessmentList($groupId, $languageId) {
    $statement = new Papaya\Database\Statement\Prepared(
      $this->getDatabaseAccess(),
      'SELECT 
        assessment_id, group_id, assessment_rating, assessment_title, assessment_text
        FROM :assessments WHERE group_id = :group_id AND lng_id = :language_id ORDER BY assessment_rating ASC'
    );
    $statement->addTableName('assessments', self::TABLE_ASSESSMENTS);
    $statement->addInt('group_id', (int)$groupId);
    $statement->addInt('language_id', (int)$languageId);
    if ($result = $this->databaseQuery($statement)) {
      return iterator_to_array($result);
    };
    return FALSE;
  }

  private function getAssessmentDeleteForm() {
    $dialog = new UI\Dialog();
    $dialog->caption = new Papaya\UI\Text\Translated('Delete assessment');
    $dialog->parameterGroup($this->parameterGroup());
    $dialog->hiddenFields->merge(
      [
        'cmd' => 'delete_assessment',
        'group_id' => $this->assessment['group_id'],
        'assessment_id' => $this->assessment['assessment_id'],
        'question_id' => 0,
        'answer_id' => 0
      ]
    );
    $dialog->buttons[] = new UI\Dialog\Button\Submit(new UI\Text\Translated('Delete'));
    $dialog->fields[] = new UI\Dialog\Field\Information(
      new UI\Text\Translated(
        'Delete assessment "%s"?',
        [$this->assessment['assessment_text']]
      ),
      'places-trash'
    );
    return $dialog;
  }
}


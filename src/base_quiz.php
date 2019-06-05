<?php
/**
* Base quiz
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
* @version $Id: base_quiz.php 39733 2014-04-08 18:10:55Z weinert $
*/

/**
* Base quiz
*
* @package Papaya-Modules
* @subpackage Free-Quiz
*/
class base_quiz extends base_db {

  const STORAGE_MODE_FIELD = 0;
  const STORAGE_MODE_URL = 1;

  const MODE_BOOLEAN = '0';
  const MODE_RATED = '1';

  const TABLE_QUESTIONS = 'quiz_question';
  const TABLE_ANSWERS = 'quiz_answer';
  const TABLE_ASSESSMENTS = 'quiz_assessment';

  /**
  * Answer
  * @var array $answer
  */
  var $answer = NULL;

  /**
  * Answers array
  * @var array $answers
  */
  var $answers = NULL;

  /**
  * Question
  * @var array $question
  */
  var $question = NULL;

  /**
  * Question array
  * @var array $questions
  */
  var $questions = NULL;

  /**
  * Group
  * @var array $group
  */
  var $group = NULL;

  /**
  * Groups array
  * @var array $groups
  */
  var $groups = NULL;

  /**
  * Group array tree
  * @var array $groupTree
  */
  var $groupTree = NULL;

  /**
   * @var array
   */
  protected $groupsOpen = array();

  /**
  * Group detail
  * @var array $groupDetail
  */
  var $groupDetail = NULL;

  /**
  * Database table answer
  * @var string $tableAnswer
  */
  var $tableAnswer = '';

  /**
  * Database table group
  * @var string $tableGroup
  */
  var $tableGroup = '';

  /**
  * Database table group trans
  * @var string $tableGroupTrans
  */
  var $tableGroupTrans = '';

  /**
  * Database table question
  * @var string $tableQuestion
  */
  var $tableQuestion = '';

  /**
   * @var base_plugin
   */
  public $module;

  /**
  * Constructor initialisize class variables
  *
  * @param string $paramName optional, default value 'quiz'
  * @access public
  */
  public function __construct($paramName = 'quiz') {
    $this->paramName = $paramName;
    $this->sessionParamName = 'PAPAYA_SESS_'.$paramName;
    $this->tableAnswer = PAPAYA_DB_TABLEPREFIX.'_quiz_answer';
    $this->tableGroup = PAPAYA_DB_TABLEPREFIX.'_quiz_group';
    $this->tableGroupTrans = PAPAYA_DB_TABLEPREFIX.'_quiz_group_trans';
    $this->tableQuestion = PAPAYA_DB_TABLEPREFIX.'_quiz_question';
  }

  /**
  * Initialisize parameters
  *
  * @access public
  */
  public function initialize() {
    $this->initializeParams();
  }

  /**
  * Load group
  *
  * @param integer $id
  * @param integer $baseId optional, default value 0
  * @access public
  * @return boolean
  */
  public function loadGroup($id, $baseId = 0) {
    unset($this->group);
    $sql = "SELECT group_id, group_parent_path, group_parent
              FROM %s
             WHERE group_id = '%d'";
    $params = array($this->tableGroup, $id);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        if ($baseId == 0 || $id == $baseId ||
            (strpos($row['catalog_parent_path'], ';'.$baseId.';') !== FALSE)) {
          $this->group = $row;
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
  * Load groups
  *
  * @param integer $lngId
  * @access public
  * @return boolean
  */
  public function loadGroups($lngId) {
    unset($this->groups);
    unset($this->groupTree);
    $ids = array(0);
    if (isset($this->groupsOpen) && is_array($this->groupsOpen)) {
      foreach ($this->groupsOpen as $groupId => $opened) {
        if ($opened) {
          $ids[] = (int)$groupId;
        }
      }
    }
    if (count($ids) > 1) {
      $filter = " IN ('".implode("', '", $ids)."') ";
    } else {
      $filter = " = '0' ";
    }
    $sql = "SELECT c.group_id, c.group_parent, ct.lng_id,
                   ct.groupdetail_title, ct.groupdetail_text, ct.groupdetail_mode
              FROM %s AS c
              LEFT OUTER JOIN %s AS ct
                ON (ct.group_id = c.group_id AND ct.lng_id = '%d')
             WHERE c.group_parent $filter
             ORDER BY ct.groupdetail_title, c.group_id DESC";
    $params = array($this->tableGroup, $this->tableGroupTrans, $lngId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->groups[(int)$row['group_id']] = $row;
        $this->groupTree[(int)$row['group_parent']][] = $row['group_id'];
      }
      $this->loadGroupCounts();
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Load group tree
  *
  * @param integer $lngId
  * @access public
  * @return boolean
  */
  public function loadGroupTree($lngId) {
    unset($this->groups);
    unset($this->groupTree);

    $ids = array(0);
    if (isset($this->groupsOpen) && is_array($this->groupsOpen)) {
      foreach ($this->groupsOpen as $groupId => $opened) {
        if ($opened) {
          $ids[] = (int)$groupId;
        }
      }
    }
    $sql = "SELECT c.group_id, c.group_parent, c.group_parent_path, ct.lng_id,
                   ct.groupdetail_title, ct.groupdetail_text, ct.groupdetail_mode
              FROM %s AS c
              LEFT OUTER JOIN %s AS ct
                ON (ct.group_id=c.group_id AND ct.lng_id = '%d')
             ORDER BY ct.groupdetail_title, c.group_id DESC";
    $params = array($this->tableGroup, $this->tableGroupTrans, $lngId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->groups[(int)$row['group_id']] = $row;
        $this->groupTree[(int)$row['group_parent']][] = $row['group_id'];
      }
      $this->loadGroupCounts();
      return TRUE;
    }
    return FALSE;
  }


  /**
  * Load group counts
  *
  * @access public
  * @return boolean
  */
  public function loadGroupCounts() {
    $filter = '';
    if (isset($this->groupsOpen) && is_array($this->groupsOpen)) {
      if (isset($this->groups) && is_array($this->groups)) {
        $ids = array_keys($this->groups);
      } else {
        $ids = array();
      }
      if (count($ids) > 1) {
        $filter = " WHERE group_parent IN ('".implode("', '", $ids)."') ";
      } else {
        $filter = " WHERE group_parent = '".@(int)$ids[0]."' ";
      }
    }
    $sql = "SELECT COUNT(*) AS subcategs, group_parent
              FROM %s
              $filter
             GROUP BY group_parent";
    if ($res = $this->databaseQueryFmt($sql, $this->tableGroup)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->groups[(int)$row['group_parent']]['CATEG_COUNT'] = $row['subcategs'];
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
  * Load group details
  *
  * @param integer $id
  * @param integer $lngId
  * @access public
  * @return boolean
  */
  public function loadGroupDetail($id, $lngId) {
    $sql = "SELECT groupdetail_title, groupdetail_text, groupdetail_mode, lng_id
              FROM %s
             WHERE group_id = '%d'
               AND lng_id = '%d'";
    $params = array($this->tableGroupTrans, $id, $lngId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->groupDetail = $row;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * Load question
  *
  * @param integer $id
  * @access public
  * @return bool
  */
  public function loadQuestion($id) {
    $sql = "SELECT question_id, question_title, question_text, question_link,
                   question_number, lng_id
              FROM %s
             WHERE question_id = '%d'";
    $params = array($this->tableQuestion, $id);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->question = $row;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * Load answer
  *
  * @param integer $id
  * @access public
  * @return bool
  */
  public function loadAnswer($id) {
    $sql = "SELECT answer_id, question_id, lng_id, answer_text, answer_explanation,
                   answer_right, answer_number, answer_response
              FROM %s
             WHERE answer_id = '%d'";
    $params = array($this->tableAnswer, $id);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->answer = $row;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
  * Get next smaller question sort-id
  *
  * @param integer $value
  * @access public
  * @return integer next smaller question sort-id
  */
  public function getNextSmallerQ($value) {
    $ret = 0;
    $sql = "SELECT MAX(question_number) AS max
              FROM %s
             WHERE question_number < '%d'";
    $params = array($this->tableQuestion, $value);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $ret = $row['max'];
      }
    }
    return $ret;
  }

  /**
  * Get next bigger question sort-id
  *
  * @param integer $value
  * @access public
  * @return integer next bigger question sort-id
  */
  public function getNextBiggerQ($value) {
    $ret = 0;
    $sql = "SELECT MIN(question_number) AS min
              FROM %s
             WHERE question_number > '%d'";
    $params = array($this->tableQuestion, $value);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $ret = $row['min'];
      }
    }
    return $ret;
  }

  /**
  * Get next smaller answer sort-id
  *
  * @param integer $value
  * @access public
  * @return integer next smaller question sort-id
  */
  public function getNextSmallerA($value) {
    $ret = 0;
    $sql = "SELECT MAX(answer_number) AS max
              FROM %s
             WHERE answer_number < '%d'";
    $params = array($this->tableAnswer, $value);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $ret = $row['max'];
      }
    }
    return $ret;
  }

  /**
  * Get next bigger answer sort-id
  *
  * @param integer $value
  * @access public
  * @return integer next bigger answer sort-id
  */
  public function getNextBiggerA($value) {
    $ret = 0;
    $sql = "SELECT MIN(answer_number) AS min
              FROM %s
             WHERE answer_number > '%d'";
    $params = array($this->tableAnswer, $value);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $ret = $row['min'];
      }
    }
    return $ret;
  }

  /**
  * Load question list
  *
  * @param integer $groupId
  * @param integer $lngId
  * @access public
  */
  public function loadQuestionList($groupId, $lngId) {
    unset($this->questions);
    $sql = "SELECT question_id, group_id, lng_id, question_title,
                   question_link, question_text, question_number
              FROM %s
             WHERE group_id = '%d'
               AND lng_id = '%d'
             ORDER BY question_number";
    $params = array($this->tableQuestion, $groupId, $lngId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->questions[$row['question_id']] = $row;
      }
    }
  }

  /**
  * Load answer list
  *
  * @param integer $questionId
  * @param integer $lngId
  * @access public
  */
  public function loadAnswerList($questionId, $lngId) {
    unset($this->answers);
    $sql = "SELECT answer_id, question_id, lng_id, answer_text, answer_explanation,
                   answer_right, answer_number, answer_response
              FROM %s
             WHERE question_id = '%d'
               AND lng_id = '%d'
             ORDER BY answer_number";
    $params = array($this->tableAnswer, $questionId, $lngId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->answers[$row['answer_id']] = $row;
      }
    }
  }

  /**
  * Load answer list
  *
  * @param integer|array $answerdIds
  * @param integer $lngId
  * @access public
  */
  public function loadAnswerByIds($answerdIds, $lngId) {
    $this->answers = [];
    $statement = new Papaya\Database\Statement\Prepared(
      $this->getDatabaseAccess(),
      'SELECT a.answer_id, a.question_id, a.lng_id, a.answer_text, a.answer_explanation,
                  a.answer_right, a.answer_number, a.answer_response
             FROM :answers AS a
             JOIN :questions AS q USING(question_id)
            WHERE a.answer_id IN :answer_ids AND a.lng_id = :language_id AND q.lng_id = :language_id
            ORDER BY q.question_number ASC, a.answer_number ASC'
    );
    $statement->addTableName('answers', self::TABLE_ANSWERS);
    $statement->addTableName('questions', self::TABLE_QUESTIONS);
    $statement->addIntList('answer_ids', $answerdIds);
    $statement->addInt('language_id', $lngId);
    if ($res = $this->databaseQuery($statement)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->answers[$row['answer_id']] = $row;
      }
    }
  }

  public function loadAssessmentByRating($quizId, $languageId, $rating) {
    $statement = new Papaya\Database\Statement\Prepared(
      $this->getDatabaseAccess(),
      'SELECT 
        assessment_id, group_id, lng_id, assessment_rating, assessment_title, assessment_text
        FROM :assessments 
       WHERE group_id = :group_id 
         AND lng_id = :language_id
         AND assessment_rating <= :rating
       ORDER BY assessment_rating DESC'
    );
    $statement->addTableName('assessments', self::TABLE_ASSESSMENTS);
    $statement->addInt('group_id', (int)$quizId);
    $statement->addInt('language_id', (int)$languageId);
    $statement->addInt('rating', (int)$rating);
    if ($result = $this->databaseQuery($statement, 1)) {
      return $result->fetchRow(DB_FETCHMODE_ASSOC);
    };
    return FALSE;
  }

  /**
  * Does group exist ?
  *
  * @param integer $id
  * @access public
  * @return boolean
  */
  public function groupExist($id) {
    if ($id == 0) {
      return TRUE;
    }
    $sql = "SELECT COUNT(*)
              FROM %s
             WHERE group_id = '%d'";
    $params = array($this->tableGroup, $id);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      return ($res->fetchField() >= 1);
    }
    return FALSE;
  }

  /**
  * Group is empty ?
  *
  * @param integer $id
  * @access public
  * @return boolean
  */
  public function groupIsEmpty($id) {
    $sql = "SELECT COUNT(*)
              FROM %s
             WHERE group_parent = '%d'";
    $params = array($this->tableGroup, $id);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      return ($res->fetchField() == 0);
    }
    return TRUE;
  }

  /**
  * Get box output xml
  *
  * @param base_actionbox $box
  * @param array $data
  * @access public
  * @return string box xml
  */
  public function getBoxOutput($box, $data) {
    $single = FALSE;
    $answer = NULL;
    $ret = '';
    $ret .= sprintf(
      '<quiz action="%s">'.LF,
      papaya_strings::escapeHTMLChars($this->getWebLink())
    );
    if (!isset($this->params['answer_id'])) {
      $this->loadQuestionList($data['quiz'], $box->parentObj->getContentLanguageId());
      $questSelect = array_keys($this->questions);
      $max = count($questSelect);
      srand((double)microtime() * 1000000);
      $randomValue = rand(0, ($max - 1));
      $questionId = $questSelect[$randomValue];
      $question = $this->questions[$questionId];
      $this->loadAnswerList($questionId, $box->parentObj->getContentLanguageId());
      $answer = $this->answers;
    } elseif (isset($this->params['answer_id'])) {
      // questions answered, output correct answer
      $this->loadAnswer($this->params['answer_id']);
      $answer = $this->answer;
      $this->loadQuestion($answer['question_id']);
      $question = $this->question;
      $single = TRUE;
    }

    if (isset($question)) {
      $ret .= sprintf(
        '<question id="%d" title="%s" fieldname="%s[question_id]">%s</question>'.LF,
        (int)$question['question_id'],
        $box->getXHTMLString($question['question_title']),
        $this->paramName,
        $box->getXHTMLString($question['question_text'])
      );
    }
    if ($answer != NULL && !$single) {
      foreach ($answer as $value) {
        $ret .= sprintf(
          '<answer href="%s" id="%d" fieldname="%s[answer_id]">%s'.
          '<explanation>%s</explanation></answer>'.LF,
          papaya_strings::escapeHTMLChars(
            $this->getWebLink(
              NULL,
              NULL,
              NULL,
              array('answer_id' => (int)$value['answer_id']),
              $this->paramName
            )
          ),
          (int)$value['answer_id'],
          papaya_strings::escapeHTMLChars($this->paramName),
          $box->getXHTMLString($value['answer_text']),
          $box->getXHTMLString($value['answer_explanation'])
        );
      }
    } else {
      $ret .= sprintf(
        '<reply right="%d">%s</reply>'.LF,
        (int)$answer['answer_right'],
        $box->getXHTMLString($answer['answer_response'])
      );
      $this->params['question_id'] = NULL;
      $this->params['answer_id'] = NULL;
    }
    $ret .= '</quiz>'.LF;
    return $ret;
  }

  /**
   * @param string $string
   * @param integer $questionId
   * @param integer $answerId
   * @param integer $languageId
   * @return string
   */
  public function saveAnswerToString($string, $questionId, $answerId, $languageId) {
    $this->loadAnswerList($questionId, $languageId);
    $answers = array_diff(
      $this->getStoredAnswersFromString($string),
      array_keys($this->answers)
    );
    $answers[] = $answerId;
    return implode(' ', $answers);
  }

  /**
   * @param string $string
   * @return array
   */
  public function getStoredAnswersFromString($string) {
    if (preg_match_all('(\d+)', $string, $matches)) {
      return $matches[0];
    }
    return [];
  }

  // --------------------------------------  CONTENT ---------------------------------------------

  /**
  * Get content output xml
  *
  * @param base_content $contentObj calling content object
  * @param array $data data from content object
  * @return string $result content xml
  */
  public function getContentOutput($contentObj, $data) {

    $result = '';
    $quizId = $data['quiz'];
    $this->loadGroupDetail($quizId, $contentObj->parentObj->getContentLanguageId());
    $this->loadQuestionList($quizId, $contentObj->parentObj->getContentLanguageId());

    if (isset($this->groupDetail) && isset($this->questions) && is_array($this->questions) && count($this->questions) > 0) {
      $questionIds = array_keys($this->questions);

      $showFollowing = FALSE;
      if (isset($this->params['question_id']) && isset($this->params['answer_id'])) {
        $this->params['answers'] = $this->saveAnswerToString(
          $this->params['answers'] ? $this->params['answers'] : '',
          $this->params['question_id'],
          $this->params['answer_id'],
          $contentObj->parentObj->getContentLanguageId()
        );
        $showFollowing = TRUE;
      }

      $selectedAnswerIds = [];
      if (isset($this->params['answers'])) {
        $selectedAnswerIds = $this->getStoredAnswersFromString($this->params['answers']);
      }

      $previousId = NULL;
      $lastId = end($questionIds);
      $currentId = isset($this->params['question_id']) && in_array($this->params['question_id'], $questionIds, FALSE)
        ? $this->params['question_id'] : reset($questionIds);
      $currentOffset = array_search($currentId, $questionIds, FALSE) ?: 0;
      $questionNumber = $currentOffset + 1;
      if ($showFollowing && $currentOffset <= count($questionIds) - 1) {
        $previousId = $currentId;
        $currentId = $questionIds[$currentOffset + 1];
        $questionNumber = $currentOffset + 2;
      } elseif ($currentOffset > 0) {
        $previousId = $questionIds[$currentOffset - 1];
      }

      $result .= sprintf(
        '<quiz action="%s" mode="%s" parameter_mode="%s" parameter_group="%s" stored_name="%s" stored_values="%s">'.LF,
        papaya_strings::escapeHTMLChars($this->getWebLink()),
        $this->groupDetail['groupdetail_mode'] === self:: MODE_RATED ? 'rated' : 'boolean',
        isset($data['storage_mode']) && (int)$data['storage_mode'] === self::STORAGE_MODE_URL ? 'get' : 'post',
        $this->paramName,
        $this->paramName.'[answers]',
        isset($this->params['answers']) ? $this->params['answers'] : ''
      );
      if (
        isset($data['show_back_link']) &&
        $data['show_back_link'] &&
        $previousId > 0
      ) {
        $result .= sprintf(
          '<link rel="back" href="%s"/>',
          papaya_strings::escapeHTMLChars(
            $this->getWebLink(
              NULL,
              NULL,
              NULL,
              [
                'answers' => isset($this->params['answers']) ? $this->params['answers'] : '',
                'question_id' => isset($this->params['question_id']) ? $previousId : ''
              ],
              $this->paramName
            )
          )
        );
      }

      if (isset($this->questions[$currentId])) {
        $question = $this->questions[$currentId];
        $result .= sprintf(
          '<question number="%d" id="%d" last="%d" title="%s"'.
          ' fieldname="%s[question_id]">%s</question>'.LF,
          $questionNumber,
          $question['question_id'],
          ($currentId == $lastId),
          papaya_strings::escapeHTMLChars($question['question_title']),
          $this->paramName,
          $contentObj->getXHTMLString($question['question_text'])
        );
        $this->params['question_id'] = NULL;
        $this->params['answer_id'] = NULL;
        $this->loadAnswerList($currentId, $contentObj->parentObj->getContentLanguageId());
        if (isset($this->answers) && is_array($this->answers)) {
          foreach ($this->answers as $value) {
            $result .= sprintf(
              '<answer id="%d" fieldname="%s[answer_id]"%s>'.
              '<text>%s</text><explanation>%s</explanation></answer>'.LF,
              (int)$value['answer_id'],
              $this->paramName,
              in_array($value['answer_id'], $selectedAnswerIds, FALSE) ? ' selected="yes"' : '',
              $contentObj->getXHTMLString($value['answer_text']),
              $contentObj->getXHTMLString($value['answer_explanation'])
            );
          }
        }
      } elseif (!isset($this->questions[$currentId])) {
        $answerIds = array();
        if (isset($this->params['answers'])) {
          $answerIds = $this->getStoredAnswersFromString($this->params['answers']);
        }

        if (isset($answerIds) && is_array($answerIds) && count($answerIds) > 0) {
          $this->loadAnswerByIds($answerIds, $contentObj->parentObj->getContentLanguageId());
          if (isset($data['show_assessment']) && $data['show_assessment']) {
            $rating = array_reduce(
              $this->answers,
              function ($carry, $answer) {
                return $carry + $answer['answer_right'];
              },
              0
            );
            $assessment = $this->loadAssessmentByRating(
              $quizId, $contentObj->parentObj->getContentLanguageId(), $rating
            );
            if ($assessment) {
              $result .= sprintf('<assessment points="%d">', $rating);
              $result .= sprintf(
                '<title>%s</title>',
                \Papaya\Utility\Text\XML::escape(
                  new \Papaya\UI\Text\Placeholders(
                    $assessment['assessment_title'],
                    ['rating' => number_format($rating, 0)]
                  )
                )
              );
              $result .= sprintf('<text>%s</text>', $contentObj->getXHTMLString($assessment['assessment_text']));
              $result .= '</assessment>';
            }
          }
          if (isset($data['show_summary']) && $data['show_summary']) {
            $result .= '<summary>'.LF;
            $j = 1;
            foreach ($answerIds as $answerId) {
              if (isset($this->answers[$answerId])) {
                $answer = $this->answers[$answerId];
                $questionId = $answer['question_id'];
                if (isset($this->questions[$questionId])) {
                  $question = $this->questions[$questionId];
                  $result .= sprintf(
                    '<question number="%d"><title>%s</title>'.
                    '<link>%s</link><text>%s</text>'.LF,
                    $j++,
                    $contentObj->getXHTMLString($question['question_title']),
                    $contentObj->getXHTMLString($question['question_link']),
                    $contentObj->getXHTMLString($question['question_text'])
                  );
                  $result .= sprintf(
                    '<given_answer>%s</given_answer>',
                    $contentObj->getXHTMLString($answer['answer_text'])
                  );
                  $result .= sprintf(
                    '<explanation>%s</explanation>',
                    $contentObj->getXHTMLString($answer['answer_explanation'])
                  );
                  $result .= sprintf(
                    '<reply value="%d" correct="%d">%s</reply>'.LF,
                    $answer['answer_right'],
                    $answer['answer_right'] > 0 ? 1 : 0,
                    $contentObj->getXHTMLString($answer['answer_response'])
                  );
                  $result .= '</question>'.LF;
                }
              }
            }
            $result .= '</summary>'.LF;
          }
        }
      }
      $result .= '</quiz>'.LF;
      return $result;
    }
    return '';
  }
}


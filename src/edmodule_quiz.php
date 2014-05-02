<?php
/**
* Quiz administration module
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
* @version $Id: edmodule_quiz.php 39562 2014-03-14 15:45:29Z weinert $
*/

/**
* Quiz administration module
*
* @package Papaya-Modules
* @subpackage Free-Quiz
*/
class edmodule_quiz extends base_module {

  /**
  * permissions
  * @var array $permissions
  */
  var $permissions = array(
    1 => 'Manage',
  );

  /**
  * Function for execute module
  *
  * @access public
  */
  function execModule() {
    if ($this->hasPerm(1, TRUE)) {
      $quiz = new admin_quiz;
      $quiz->module = $this;
      $quiz->layout = $this->layout;

      $quiz->initialize();
      $quiz->execute();
      $quiz->getXML();
    }
  }
}

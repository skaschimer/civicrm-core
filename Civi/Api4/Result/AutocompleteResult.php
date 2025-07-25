<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

namespace Civi\Api4\Result;

/**
 * Result class for autocomplete actions
 *
 * @package Civi\Api4\Result
 */
class AutocompleteResult extends \Civi\Api4\Generic\Result {

  /**
   * List of fields applicable to this autocomplete
   *
   * @var array
   */
  public $searchFields = [];

  /**
   * Current field being searched
   *
   * @var string
   */
  public $searchField = '';

}

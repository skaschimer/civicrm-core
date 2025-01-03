<?php

/**
 *  File for the TestActivityType class
 *
 *  (PHP 5)
 *
 * @package   CiviCRM
 *
 *   This file is part of CiviCRM
 *
 *   CiviCRM is free software; you can redistribute it and/or
 *   modify it under the terms of the GNU Affero General Public License
 *   as published by the Free Software Foundation; either version 3 of
 *   the License, or (at your option) any later version.
 *
 *   CiviCRM is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU Affero General Public License for more details.
 *
 *   You should have received a copy of the GNU Affero General Public
 *   License along with this program.  If not, see
 *   <http://www.gnu.org/licenses/>.
 */

/**
 *  Test APIv3 civicrm_activity_* functions
 *
 * @package CiviCRM_APIv3
 * @subpackage API_Activity
 * @group headless
 */
class api_v3_ActivityTypeTest extends CiviUnitTestCase {
  protected $_apiversion;

  public function setUp(): void {
    $this->_apiversion = 3;
    parent::setUp();
    $this->useTransaction(TRUE);
  }

  /**
   * Test civicrm_activity_type_get().
   */
  public function testActivityTypeGet(): void {
    $params = [];
    $result = $this->callAPISuccess('activity_type', 'get', $params);
    $this->assertEquals($result['values']['1'], 'Meeting');
  }

  /**
   * Test civicrm_activity_type_create().
   */
  public function testActivityTypeCreate(): void {
    $params = [
      'weight' => '2',
      'label' => 'send out letters',
      'filter' => 0,
      'is_active' => 1,
      'is_optgroup' => 1,
      'is_default' => 0,
    ];
    $result = $this->callAPISuccess('activity_type', 'create', $params);
  }

  /**
   * Test civicrm_activity_type_create - check id
   */
  public function testActivityTypecreatecheckId(): void {
    $params = [
      'label' => 'type_create',
      'weight' => '2',
    ];
    $activitycreate = $this->callAPISuccess('activity_type', 'create', $params);
    $this->assertArrayHasKey('id', $activitycreate);
    $this->assertArrayHasKey('option_group_id', $activitycreate['values'][$activitycreate['id']]);
  }

  /**
   * Test civicrm_activity_type_delete()
   */
  public function testActivityTypeDelete(): void {
    $params = [
      'label' => 'type_create_delete',
      'weight' => '2',
    ];
    $activitycreate = $this->callAPISuccess('activity_type', 'create', $params);
    $params = [
      'activity_type_id' => $activitycreate['id'],
    ];
    $result = $this->callAPISuccess('activity_type', 'delete', $params, __FUNCTION__, __FILE__);
  }

}

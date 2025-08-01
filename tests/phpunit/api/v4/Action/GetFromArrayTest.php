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

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */


namespace api\v4\Action;

use api\v4\Api4TestBase;
use Civi\Api4\MockArrayEntity;

/**
 * @group headless
 */
class GetFromArrayTest extends Api4TestBase {

  /**
   * Test get with existing dataset
   * @see \Civi\Api4\Action\MockArrayEntity\Get::getRecords
   */
  public function testArrayGetWithLimit(): void {
    $result = MockArrayEntity::get()
      ->setOffset(2)
      ->setLimit(2)
      ->execute();
    $this->assertEquals(3, $result[0]['field1']);
    $this->assertEquals(4, $result[1]['field1']);

    // The object's count() method will account for all results, ignoring limit, while the array results are limited
    $this->assertCount(2, (array) $result);
    $this->assertCount(6, $result);
  }

  /**
   * Test get with existing dataset
   * @see \Civi\Api4\Action\MockArrayEntity\Get::getRecords
   */
  public function testArrayGetWithSort(): void {
    $result = MockArrayEntity::get()
      ->addOrderBy('field1', 'DESC')
      ->execute();
    $this->assertEquals([6, 5, 4, 3, 2, 1], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addOrderBy('field5', 'DESC')
      ->addOrderBy('field2', 'ASC')
      ->execute();
    $this->assertEquals([3, 2, 5, 4, 1, 6], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addOrderBy('field3', 'ASC')
      ->addOrderBy('field2', 'ASC')
      ->execute();
    $this->assertEquals([3, 1, 2, 5, 4, 6], $result->column('field1'));
  }

  /**
   * Test get with existing dataset
   * @see \Civi\Api4\Action\MockArrayEntity\Get::getRecords
   */
  public function testArrayGetWithSelect(): void {
    $result = MockArrayEntity::get()
      ->addSelect('field1')
      ->addSelect('f*3')
      ->setLimit(4)
      ->execute();
    $this->assertEquals([
      [
        'field1' => 1,
        'field3' => NULL,
      ],
      [
        'field1' => 2,
        'field3' => 0,
      ],
      [
        'field1' => 3,
        'field3' => NULL,
      ],
      [
        'field1' => 4,
        'field3' => 1,
      ],
    ], (array) $result);
  }

  /**
   * Test where clause with existing dataset
   * @see \Civi\Api4\Action\MockArrayEntity\Get::getRecords
   */
  public function testArrayGetWithWhere(): void {
    $result = MockArrayEntity::get()
      ->addWhere('field2', '=', 'yack')
      ->execute();
    $this->assertEquals([2, 6], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field5', '!=', 'banana')
      ->addWhere('field3', 'IS NOT NULL')
      ->execute();
    $this->assertEquals([4, 5, 6], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field1', '>=', '4')
      ->execute();
    $this->assertEquals([4, 5, 6], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field1', '<', '2')
      ->execute();
    $this->assertEquals([1], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field2', 'LIKE', '%ra%')
      ->execute();
    $this->assertEquals([1, 3], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field2', 'REGEXP', '(zebra|yac[a-z]|something/else)')
      ->execute();
    $this->assertEquals([1, 2, 6], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field2', 'NOT REGEXP', '^[x|y|z]')
      ->execute();
    $this->assertEquals([4, 5], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field2', 'REGEXP BINARY', 'Yack')
      ->execute();
    $this->assertEquals([6], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field5', 'NOT REGEXP BINARY', 'Apple')
      ->execute();
    $this->assertEquals([1, 2, 3, 4, 5], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field3', 'IS NULL')
      ->execute();
    $this->assertEquals([1, 3], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field3', '=', '0')
      ->execute();
    $this->assertEquals([2], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field2', 'LIKE', '%ra')
      ->execute();
    $this->assertEquals([1], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field2', 'LIKE', 'ra')
      ->execute();
    $this->assertEquals(0, count($result));

    $result = MockArrayEntity::get()
      ->addWhere('field2', 'NOT LIKE', '%ra%')
      ->execute();
    $this->assertEquals([2, 4, 5, 6], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field6', '=', '0')
      ->execute();
    $this->assertEquals([3, 4, 6], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field6', 'IS EMPTY')
      ->execute();
    $this->assertEquals([1, 2, 3, 4, 6], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field6', 'IS NOT EMPTY')
      ->execute();
    $this->assertEquals([5], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field1', 'BETWEEN', [3, 5])
      ->execute();
    $this->assertEquals([3, 4, 5], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addWhere('field1', 'NOT BETWEEN', [3, 4])
      ->execute();
    $this->assertEquals([1, 2, 5, 6], $result->column('field1'));
  }

  /**
   * Test complex where clause with existing dataset
   * @see \Civi\Api4\Action\MockArrayEntity\Get::getRecords
   */
  public function testArrayGetWithNestedWhereClauses(): void {
    $result = MockArrayEntity::get()
      ->addClause('OR', ['field2', 'LIKE', '%ra'], ['field2', 'LIKE', 'x ray'])
      ->execute();
    $this->assertEquals([1, 3], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addClause('OR', ['field2', '=', 'zebra'], ['field2', '=', 'yack'])
      ->addClause('OR', ['field5', '!=', 'apple'], ['field3', 'IS NULL'])
      ->execute();
    $this->assertEquals([1, 2], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addClause('NOT', ['field2', '!=', 'yack'])
      ->execute();
    $this->assertEquals([2, 6], $result->column('field1'));

    $result = MockArrayEntity::get()
      ->addClause('OR', ['field1', '=', 2], ['AND', [['field5', '=', 'apple'], ['field3', '=', 1]]])
      ->execute();
    $this->assertEquals([2, 4, 5, 6], $result->column('field1'));
  }

}

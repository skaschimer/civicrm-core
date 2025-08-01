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

use Civi\Api4\RelationshipType;

/**
 * Class contains api test cases for "civicrm_relationship"
 * @group headless
 */
class api_v3_RelationshipTest extends CiviUnitTestCase {

  use CRMTraits_Custom_CustomDataTrait;

  protected $_cId_a;
  /**
   * Second individual.
   *
   * @var int
   */
  protected $_cId_a_2;
  protected $_cId_b;
  /**
   * Second organization contact.
   *
   * @var  int
   */
  protected $_cId_b2;
  protected $relationshipTypeID;
  protected $_params;

  protected $entity;

  /**
   * Set up function.
   */
  public function setUp(): void {
    parent::setUp();
    $this->_cId_a = $this->individualCreate();
    $this->_cId_a_2 = $this->individualCreate([
      'last_name' => 'c2',
      'email' => 'c@w.com',
      'contact_type' => 'Individual',
    ]);
    $this->_cId_b = $this->organizationCreate();
    $this->_cId_b2 = $this->organizationCreate(['organization_name' => ' Org 2']);
    $this->entity = 'Relationship';
    //Create a relationship type.
    $relTypeParams = [
      'name_a_b' => 'Relation 1 for delete',
      'name_b_a' => 'Relation 2 for delete',
      'description' => 'Testing relationship type',
      'contact_type_a' => 'Individual',
      'contact_type_b' => 'Organization',
      'is_reserved' => 1,
      'is_active' => 1,
    ];

    $this->relationshipTypeID = $this->relationshipTypeCreate($relTypeParams);
    $this->_params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2008-12-20',
      'is_active' => 1,
    ];

  }

  /**
   * Tear down function.
   *
   * @throws \Exception
   */
  public function tearDown(): void {
    $this->quickCleanup(['civicrm_relationship'], TRUE);
    $this->quickCleanUpFinancialEntities();
    RelationshipType::delete(FALSE)->addWhere('id', '>', ($this->relationshipTypeID - 1))->execute();
    CRM_Core_BAO_ConfigSetting::enableComponent('CiviMember');
    parent::tearDown();
  }

  /**
   * Test Current Employer is correctly set.
   */
  public function testCurrentEmployerRelationship(): void {
    CRM_Core_BAO_ConfigSetting::disableComponent('CiviMember');
    $employerRelationshipID = $this->callAPISuccessGetValue('RelationshipType', [
      'return' => 'id',
      'name_b_a' => 'Employer Of',
    ]);
    $employerRelationship = $this->callAPISuccess('Relationship', 'create', [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $employerRelationshipID,
      'is_current_employer' => 1,
    ]);

    //Check if current employer is correctly set.
    $employer = $this->callAPISuccessGetValue('Contact', [
      'return' => 'current_employer',
      'id' => $this->_cId_a,
    ]);
    $organisation = $this->callAPISuccessGetValue('Contact', [
      'return' => 'sort_name',
      'id' => $this->_cId_b,
    ]);
    $this->assertEquals($employer, $organisation);

    //Update relationship type
    $this->callAPISuccess('Relationship', 'create', [
      'id' => $employerRelationship['id'],
      'relationship_type_id' => $this->relationshipTypeID,
    ]);
    $employeeContact = $this->callAPISuccessGetSingle('Contact', [
      'return' => ['current_employer'],
      'id' => $this->_cId_a,
    ]);
    //current employer should be removed.
    $this->assertEmpty($employeeContact['current_employer']);
  }

  /**
   * Check with incorrect required fields.
   *
   * @param int $version
   *
   * @dataProvider versionThreeAndFour
   */
  public function testRelationshipCreateWithIncorrectData(int $version): void {
    $this->_apiversion = $version;

    $params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => 'Breaking Relationship',
    ];

    $this->callAPIFailure('relationship', 'create', $params);

    //contact id is not an integer
    $params = [
      'contact_id_a' => 'invalid',
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => ['d' => '10', 'M' => '1', 'Y' => '2008'],
      'is_active' => 1,
    ];
    $this->callAPIFailure('relationship', 'create', $params);

    // Contact id does not exist.
    $params['contact_id_a'] = 999;
    $this->callAPIFailure('relationship', 'create', $params);

    //invalid date
    $params['contact_id_a'] = $this->_cId_a;
    $params['start_date'] = ['d' => '1', 'M' => '1'];
    $this->callAPIFailure('relationship', 'create', $params);
  }

  /**
   * Check relationship already exists.
   */
  public function testRelationshipCreateAlreadyExists(): void {
    $this->_apiversion = 3;
    $params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2008-12-20',
      'end_date' => NULL,
      'is_active' => 1,
    ];
    $relationship = $this->callAPISuccess('relationship', 'create', $params);

    $params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2008-12-20',
      'is_active' => 1,
    ];
    $this->callAPIFailure('Relationship', 'create', $params, 'Duplicate Relationship');
  }

  /**
   * Check relationship already exists.
   *
   * @param int $version
   *
   * @dataProvider versionThreeAndFour
   */
  public function testRelationshipCreateUpdateAlreadyExists(int $version): void {
    $this->_apiversion = $version;
    $params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2008-12-20',
      'end_date' => NULL,
      'is_active' => 1,

    ];
    $relationship = $this->callAPISuccess('Relationship', 'create', $params);
    $params = [
      'id' => $relationship['id'],
      'is_active' => 0,
      'debug' => 1,
    ];
    $this->callAPISuccess('Relationship', 'create', $params);
    $this->callAPISuccess('Relationship', 'get', $params);
  }

  /**
   * Check update doesn't reset stuff badly - CRM-11789.
   *
   * @param int $version
   *
   * @dataProvider versionThreeAndFour
   */
  public function testRelationshipCreateUpdateDoesNotMangle(int $version): void {
    $this->_apiversion = $version;
    $params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2008-12-20',
      'is_active' => 1,
      'is_permission_a_b' => 1,
      'description' => 'my desc',
    ];
    $relationship = $this->callAPISuccess('relationship', 'create', $params);

    $updateParams = [
      'id' => $relationship['id'],
      'relationship_type_id' => $this->relationshipTypeID,
    ];
    $this->callAPISuccess('relationship', 'create', $updateParams);

    //make sure the orig params didn't get changed
    $this->getAndCheck($params, $relationship['id'], 'relationship');
  }

  /**
   * Ensure disabling works.
   *
   * @param int $version
   *
   * @dataProvider versionThreeAndFour
   */
  public function testRelationshipUpdate(int $version): void {
    $this->_apiversion = $version;
    $result = $this->callAPISuccess('relationship', 'create', $this->_params);
    $relID = $result['id'];
    $result = $this->callAPISuccess('relationship', 'create', ['id' => $relID, 'description' => 'blah']);
    $this->assertEquals($relID, $result['id']);

    $this->assertEquals('blah', $result['values'][$result['id']]['description']);

    $result = $this->callAPISuccess('relationship', 'create', ['id' => $relID, 'is_permission_b_a' => 1]);
    $this->assertEquals(1, $result['values'][$result['id']]['is_permission_b_a']);
    $result = $this->callAPISuccess('relationship', 'create', ['id' => $result['id'], 'is_active' => 0]);
    $result = $this->callAPISuccess('relationship', 'get', ['id' => $result['id']]);
    $this->assertEquals(0, $result['values'][$result['id']]['is_active']);
    $this->assertEquals('blah', $result['values'][$result['id']]['description']);
    $this->assertEquals(1, $result['values'][$result['id']]['is_permission_b_a']);
  }

  /**
   * Check relationship creation.
   *
   * @param int $version
   *
   * @dataProvider versionThreeAndFour
   */
  public function testRelationshipCreateEmptyEndDate(int $version): void {
    $this->_apiversion = $version;
    $params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2010-10-30',
      'end_date' => '',
      'is_active' => 1,
      'note' => 'note',
    ];

    $result = $this->callAPISuccess('relationship', 'create', $params);
    $this->assertNotNull($result['id']);
    $relationParams = [
      'id' => $result['id'],
    ];

    // assertDBState compares expected values in $result to actual values in the DB
    $this->assertDBState('CRM_Contact_DAO_Relationship', $result['id'], $relationParams);
    $result = $this->callAPISuccess('relationship', 'get', ['id' => $result['id']]);
    $values = $result['values'][$result['id']];
    foreach ($params as $key => $value) {
      if ($key === 'note') {
        continue;
      }
      if ($key === 'end_date') {
        $this->assertTrue(empty($values[$key]));
        continue;
      }
      $this->assertEquals($value, $values[$key], $key . " doesn't match " . print_r($values, TRUE) . 'in line' . __LINE__);
    }
  }

  /**
   * Check relationship creation with custom data.
   * FIXME: Api4
   */
  public function testRelationshipCreateEditWithCustomData(): void {
    $this->createCustomGroupWithFieldsOfAllTypes();
    //few custom Values for comparing
    $custom_params = [
      $this->getCustomFieldName('text') => 'Hello! this is custom data for relationship',
      $this->getCustomFieldName('select_string') => 'Y',
      $this->getCustomFieldName('select_date') => '2009-07-11 00:00:00',
      $this->getCustomFieldName('link') => 'http://example.com',
    ];

    $params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2008-12-20',
      'is_active' => 1,
    ];
    $params = array_merge($params, $custom_params);
    $result = $this->callAPISuccess('relationship', 'create', $params);

    $relationParams = ['id' => $result['id']];
    $this->assertDBState('CRM_Contact_DAO_Relationship', $result['id'], $relationParams);

    //Test Edit of custom field from the form.
    $getParams = ['id' => $result['id']];
    $updateParams = array_merge($getParams, [
      $this->getCustomFieldName('text') => 'Edited Text Value',
      'relationship_type_id' => $this->relationshipTypeID . '_b_a',
      'related_contact_id' => $this->_cId_a,
    ]);
    $reln = new CRM_Contact_Form_Relationship();
    $reln->_action = CRM_Core_Action::UPDATE;
    $reln->_relationshipId = $result['id'];
    $reln->submit($updateParams);

    $check = $this->callAPISuccess('relationship', 'get', $getParams);
    $this->assertEquals('Edited Text Value', $check['values'][$check['id']][$this->getCustomFieldName('text')]);
  }

  /**
   * Check with complete array + custom field
   * Note that the test is written on purpose without any
   * variables specific to participant so it can be replicated into other entities
   * and / or moved to the automated test suite
   * FIXME: Api4
   */
  public function testGetWithCustom(): void {
    $ids = $this->entityCustomGroupWithSingleFieldCreate(__FUNCTION__, __FILE__);

    $params = $this->_params;
    $params['custom_' . $ids['custom_field_id']] = "custom string";

    $result = $this->callAPISuccess($this->entity, 'create', $params);
    $this->assertEquals($result['id'], $result['values'][$result['id']]['id']);

    $getParams = ['id' => $result['id']];
    $check = $this->callAPISuccess($this->entity, 'get', $getParams);
    $this->assertEquals("custom string", $check['values'][$check['id']]['custom_' . $ids['custom_field_id']], ' in line ' . __LINE__);

    $this->customFieldDelete($ids['custom_field_id']);
    $this->customGroupDelete($ids['custom_group_id']);
  }

  /**
   * Check if required fields are not passed.
   */

  /**
   * Check relationship update.
   */
  public function testRelationshipCreateDuplicate() {
    $this->_apiversion = 3;
    $relParams = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '20081214',
      'end_date' => '20091214',
      'is_active' => 1,
    ];

    $result = $this->callAPISuccess('relationship', 'create', $relParams);

    $this->assertNotNull($result['id']);

    $params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '20081214',
      'end_date' => '20091214',
      'is_active' => 0,
    ];

    $this->callAPIFailure('relationship', 'create', $params, 'Duplicate Relationship');
  }

  /**
   * CRM-13725 - Two relationships of same type with same start and end date
   * should be OK if the custom field values differ.
   * FIXME: Api4
   */
  public function testRelationshipCreateDuplicateWithCustomFields(): void {
    $this->createCustomGroupWithFieldsOfAllTypes();

    $custom_params_1 = [
      $this->getCustomFieldName('text') => 'Hello! this is custom data for relationship',
      $this->getCustomFieldName('select_string') => 'Y',
      $this->getCustomFieldName('select_date') => '2009-07-11 00:00:00',
      $this->getCustomFieldName('link') => 'http://example.com',
    ];

    $custom_params_2 = [
      $this->getCustomFieldName('text') => 'Hello! this is other custom data',
      $this->getCustomFieldName('select_string') => 'Y',
      $this->getCustomFieldName('select_date') => '2009-07-11 00:00:00',
      $this->getCustomFieldName('link') => 'http://example.org',
    ];

    $params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2008-12-20',
      'is_active' => 1,
    ];

    $params_1 = array_merge($params, $custom_params_1);
    $params_2 = array_merge($params, $custom_params_2);

    $result_1 = $this->callAPISuccess('relationship', 'create', $params_1);
    $result_2 = $this->callAPISuccess('relationship', 'create', $params_2);

    $this->assertNotNull($result_2['id']);
    $this->assertEquals(0, $result_2['is_error']);
  }

  /**
   * CRM-13725 - Two relationships of same type with same start and end date
   * should be OK if the custom field values differ. In this case, the
   * existing relationship does not have custom values, but the new one
   * does.
   * FIXME: Api4
   */
  public function testRelationshipCreateDuplicateWithCustomFields2(): void {
    $this->createCustomGroupWithFieldsOfAllTypes();

    $custom_params_2 = [
      $this->getCustomFieldName('text') => 'Hello! this is other custom data',
      $this->getCustomFieldName('select_string') => 'Y',
      $this->getCustomFieldName('select_date') => '2009-07-11 00:00:00',
      $this->getCustomFieldName('link') => 'http://example.org',
    ];

    $params_1 = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2008-12-20',
      'is_active' => 1,
    ];

    $params_2 = array_merge($params_1, $custom_params_2);

    $this->callAPISuccess('Relationship', 'create', $params_1);
    $result_2 = $this->callAPISuccess('Relationship', 'create', $params_2);

    $this->assertNotNull($result_2['id']);
  }

  /**
   * CRM-13725 - Two relationships of same type with same start and end date
   * should be OK if the custom field values differ. In this case, the
   * existing relationship does have custom values, but the new one
   * does not.
   * FIXME: Api4
   */
  public function testRelationshipCreateDuplicateWithCustomFields3(): void {
    $this->createCustomGroupWithFieldsOfAllTypes();

    $custom_params_1 = [
      $this->getCustomFieldName('text') => 'Hello! this is other custom data',
      $this->getCustomFieldName('select_string') => 'Y',
      $this->getCustomFieldName('select_date') => '2009-07-11 00:00:00',
      $this->getCustomFieldName('link') => 'http://example.org',
    ];

    $params_2 = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2008-12-20',
      'is_active' => 1,
    ];

    $params_1 = array_merge($params_2, $custom_params_1);

    $this->callAPISuccess('relationship', 'create', $params_1);
    $result_2 = $this->callAPISuccess('relationship', 'create', $params_2);

    $this->assertNotNull($result_2['id']);
    $this->assertEquals(0, $result_2['is_error']);
  }

  /**
   * Check with valid params array.
   */
  public function testRelationshipsGet(): void {
    $relParams = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2011-01-01',
      'end_date' => '2013-01-01',
      'is_active' => 1,
    ];

    $this->callAPISuccess('relationship', 'create', $relParams);

    //get relationship
    $params = [
      'contact_id' => $this->_cId_b,
    ];
    $result = $this->callAPISuccess('relationship', 'get', $params);
    $this->assertEquals($result['count'], 1);
    $params = [
      'contact_id_a' => $this->_cId_a,
    ];
    $result = $this->callAPISuccess('relationship', 'get', $params);
    $this->assertEquals($result['count'], 1);
    // contact_id_a is wrong so should be no matches
    $params = [
      'contact_id_a' => $this->_cId_b,
    ];
    $result = $this->callAPISuccess('relationship', 'get', $params);
    $this->assertEquals($result['count'], 0);
  }

  /**
   * Chain Relationship.get and to Contact.get.
   * @param int $version
   * @dataProvider versionThreeAndFour
   */
  public function testRelationshipGetWithChainedCall($version) {
    $this->_apiversion = $version;
    // Create a relationship.
    $createResult = $this->callAPISuccess('relationship', 'create', $this->_params);
    $id = $createResult['id'];

    // Try to retrieve it using chaining.
    $params = [
      'relationship_type_id' => $this->relationshipTypeID,
      'id' => $id,
      'api.Contact.get' => [
        'id' => '$value.contact_id_b',
      ],
    ];

    $result = $this->callAPISuccess('relationship', 'get', $params);

    $this->assertEquals(1, $result['count']);
    $relationship = CRM_Utils_Array::first($result['values']);
    $this->assertEquals(1, $relationship['api.Contact.get']['count']);
    $contact = CRM_Utils_Array::first($relationship['api.Contact.get']['values']);
    $this->assertEquals($this->_cId_b, $contact['id']);
  }

  /**
   * Chain Contact.get to Relationship.get and again to Contact.get.
   * @param int $version
   * @dataProvider versionThreeAndFour
   */
  public function testRelationshipGetInChainedCall($version) {
    $this->_apiversion = $version;
    // Create a relationship.
    $this->callAPISuccess('relationship', 'create', $this->_params);

    // Try to retrieve it using chaining.
    $params = [
      'id' => $this->_cId_a,
      'api.Relationship.get' => [
        'relationship_type_id' => $this->relationshipTypeID,
        'contact_id_a' => '$value.id',
        'api.Contact.get' => [
          'id' => '$value.contact_id_b',
        ],
      ],
    ];

    $result = $this->callAPISuccess('contact', 'get', $params);
    $this->assertEquals(1, $result['count']);
    $contact = CRM_Utils_Array::first($result['values']);
    $this->assertEquals(1, $contact['api.Relationship.get']['count']);
    $relationship = CRM_Utils_Array::first($contact['api.Relationship.get']['values']);
    $this->assertEquals(1, $relationship['api.Contact.get']['count']);
    $contact = CRM_Utils_Array::first($relationship['api.Contact.get']['values']);
    $this->assertEquals($this->_cId_b, $contact['id']);
  }

  /**
   * Check with valid params array.
   * (The get function will behave differently without 'contact_id' passed
   * @param int $version
   * @dataProvider versionThreeAndFour
   */
  public function testRelationshipsGetGeneric($version) {
    $this->_apiversion = $version;
    $relParams = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2011-01-01',
      'end_date' => '2013-01-01',
      'is_active' => 1,
    ];

    $this->callAPISuccess('relationship', 'create', $relParams);

    //get relationship
    $params = [
      'contact_id_b' => $this->_cId_b,
    ];
    $this->callAPISuccess('relationship', 'get', $params);
  }

  /**
   * Test retrieving only current relationships.
   * @param int $version
   * @dataProvider versionThreeAndFour
   */
  public function testGetIsCurrent($version) {
    $this->_apiversion = $version;
    $rel2Params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b2,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2008-12-20',
      'is_active' => 0,
    ];
    $rel0 = $this->callAPISuccess('relationship', 'create', $rel2Params);
    $rel1 = $this->callAPISuccess('relationship', 'create', $this->_params);

    $getParams = ['filters' => ['is_current' => 1]];
    $result = $this->callAPISuccess('relationship', 'get', $getParams);
    $this->assertEquals($result['count'], 1);
    $this->AssertEquals($rel1['id'], $result['id']);

    // now try not started
    $rel2Params['is_active'] = 1;
    $rel2Params['start_date'] = 'tomorrow';
    $rel2 = $this->callAPISuccess('relationship', 'create', $rel2Params);

    // now try finished
    $rel2Params['start_date'] = 'last week';
    $rel2Params['end_date'] = 'yesterday';
    $rel3 = $this->callAPISuccess('relationship', 'create', $rel2Params);

    $result = $this->callAPISuccess('relationship', 'get', $getParams);
    $this->assertEquals($result['count'], 1);
    $this->AssertEquals($rel1['id'], $result['id']);

    foreach ([$rel0, $rel1, $rel2, $rel3] as $rel) {
      $this->callAPISuccess('Relationship', 'delete', $rel);
    }
  }

  /**
   * Test using various operators.
   * @param int $version
   * @dataProvider versionThreeAndFour
   */
  public function testGetTypeOperators($version) {
    $this->_apiversion = $version;
    $relTypeParams = [
      'name_a_b' => 'Relation 3 for delete',
      'name_b_a' => 'Relation 6 for delete',
      'description' => 'Testing relationship type 2',
      'contact_type_a' => 'Individual',
      'contact_type_b' => 'Organization',
      'is_reserved' => 1,
      'is_active' => 1,
    ];
    $relationType2 = $this->relationshipTypeCreate($relTypeParams);
    $relTypeParams = [
      'name_a_b' => 'Relation 8 for delete',
      'name_b_a' => 'Relation 9 for delete',
      'description' => 'Testing relationship type 7',
      'contact_type_a' => 'Individual',
      'contact_type_b' => 'Organization',
      'is_reserved' => 1,
      'is_active' => 1,
    ];
    $relationType3 = $this->relationshipTypeCreate($relTypeParams);

    $relTypeParams = [
      'name_a_b' => 'Relation 6 for delete',
      'name_b_a' => 'Relation 88for delete',
      'description' => 'Testing relationship type 00',
      'contact_type_a' => 'Individual',
      'contact_type_b' => 'Organization',
      'is_reserved' => 1,
      'is_active' => 1,
    ];
    $relationType4 = $this->relationshipTypeCreate($relTypeParams);

    $rel1 = $this->callAPISuccess('relationship', 'create', $this->_params);
    $rel2 = $this->callAPISuccess('relationship', 'create', array_merge($this->_params,
      ['relationship_type_id' => $relationType2]));
    $rel3 = $this->callAPISuccess('relationship', 'create', array_merge($this->_params,
      ['relationship_type_id' => $relationType3]));
    $rel4 = $this->callAPISuccess('relationship', 'create', array_merge($this->_params,
      ['relationship_type_id' => $relationType4]));

    $getParams = [
      'relationship_type_id' => ['IN' => [$relationType2, $relationType3]],
    ];

    $result = $this->callAPISuccess('relationship', 'get', $getParams);
    $this->assertEquals($result['count'], 2);
    $this->AssertEquals([$rel2['id'], $rel3['id']], array_keys($result['values']));

    $getParams = [
      'relationship_type_id' => ['NOT IN' => [$relationType2, $relationType3]],
    ];
    $result = $this->callAPISuccess('relationship', 'get', $getParams);
    $this->assertEquals($result['count'], 2);
    $this->assertEquals([$rel1['id'], $rel4['id']], array_keys($result['values']));

    $getParams = [
      'relationship_type_id' => ['BETWEEN' => [$relationType2, $relationType4]],
    ];
    $result = $this->callAPISuccess('relationship', 'get', $getParams);
    $this->assertEquals($result['count'], 3);
    $this->AssertEquals([$rel2['id'], $rel3['id'], $rel4['id']], array_keys($result['values']));

    $getParams = [
      'relationship_type_id' => ['NOT BETWEEN' => [$relationType2, $relationType4]],
    ];
    $result = $this->callAPISuccess('relationship', 'get', $getParams);
    $this->assertEquals($result['count'], 1);
    $this->assertEquals([$rel1['id']], array_keys($result['values']));

    foreach ([$relationType2, $relationType3, $relationType4] as $id) {
      $this->callAPISuccess('RelationshipType', 'delete', ['id' => $id]);
    }
  }

  /**
   * Check with invalid relationshipType Id.
   */
  public function testRelationshipTypeAddInvalidId(): void {
    $relTypeParams = [
      'id' => 'invalid',
      'name_a_b' => 'Relation 1 for delete',
      'name_b_a' => 'Relation 2 for delete',
      'contact_type_a' => 'Individual',
      'contact_type_b' => 'Organization',
    ];
    $this->callAPIFailure('relationship_type', 'create', $relTypeParams,
      'id is not a valid integer');
  }

  /**
   * Check with valid data with contact_b.
   */
  public function testGetRelationshipWithContactB(): void {
    $relParams = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2011-01-01',
      'end_date' => '2013-01-01',
      'is_active' => 1,
    ];

    $this->callAPISuccess('relationship', 'create', $relParams);
    $result = $this->callAPISuccess('Relationship', 'get', ['contact_id' => $this->_cId_a]);
    $this->assertGreaterThan(0, $result['count']);
  }

  /**
   * Check with valid data with relationshipTypes.
   */
  public function testGetRelationshipWithRelTypes(): void {
    $relParams = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
      'start_date' => '2011-01-01',
      'end_date' => '2013-01-01',
      'is_active' => 1,
    ];

    $this->callAPISuccess('relationship', 'create', $relParams);

    $contact_a = [
      'contact_id' => $this->_cId_a,
    ];
    $this->callAPISuccess('relationship', 'get', $contact_a);
  }

  /**
   * Checks that passing in 'contact_id' + a relationship type
   * will filter by relationship type (relationships go in both directions)
   * as relationship api does a reciprocal check if contact_id provided
   *
   * We should get 1 result without or with correct relationship type id & 0 with
   * an incorrect one
   */
  public function testGetRelationshipByTypeReciprocal(): void {
    $created = $this->callAPISuccess($this->entity, 'create', $this->_params);
    $result = $this->callAPISuccess($this->entity, 'get', [
      'contact_id' => $this->_cId_a,
      'relationship_type_id' => $this->relationshipTypeID,
    ]);
    $this->assertEquals(1, $result['count']);
    $result = $this->callAPISuccess($this->entity, 'get', [
      'contact_id' => $this->_cId_a,
      'relationship_type_id' => 1,
    ]);
    $this->assertEquals(0, $result['count']);
    $this->callAPISuccess($this->entity, 'delete', ['id' => $created['id']]);
  }

  /**
   * Checks that passing in 'contact_id_b' + a relationship type
   * will filter by relationship type for contact b
   *
   * We should get 1 result without or with correct relationship type id & 0 with
   * an incorrect one
   *
   * @param int $version
   *
   * @dataProvider versionThreeAndFour
   */
  public function testGetRelationshipByTypeArrayDAO(int $version): void {
    $this->_apiversion = $version;
    $this->callAPISuccess($this->entity, 'create', $this->_params);
    $org3 = $this->organizationCreate();
    // lets just assume built in ones aren't being messed with!
    $relType2 = 5;
    // lets just assume built in ones aren't being messed with!
    $relType3 = 6;

    // Relationship 2.
    $this->callAPISuccess($this->entity, 'create',
      array_merge($this->_params, [
        'relationship_type_id' => $relType2,
        'contact_id_b' => $this->_cId_b2,
      ])
    );

    // Relationship 3.
    $this->callAPISuccess($this->entity, 'create',
      array_merge($this->_params, [
        'relationship_type_id' => $relType3,
        'contact_id_b' => $org3,
      ])
    );

    $result = $this->callAPISuccess($this->entity, 'get', [
      'contact_id_a' => $this->_cId_a,
      'relationship_type_id' => ['IN' => [$this->relationshipTypeID, $relType3]],
    ]);

    $this->assertEquals(2, $result['count']);
    foreach ($result['values'] as $value) {
      $this->assertContainsEquals($value['relationship_type_id'], [$this->relationshipTypeID, $relType3]);
    }
  }

  /**
   * Checks that passing in 'contact_id_b' + a relationship type
   * will filter by relationship type for contact b
   *
   * We should get 1 result without or with correct relationship type id & 0 with
   * an incorrect one
   */
  public function testGetRelationshipByTypeArrayReciprocal(): void {
    $this->callAPISuccess($this->entity, 'create', $this->_params);
    $org3 = $this->organizationCreate();
    // lets just assume built in ones aren't being messed with!
    $relType2 = 5;
    $relType3 = 6;

    // Relationship 2.
    $this->callAPISuccess($this->entity, 'create',
      array_merge($this->_params, [
        'relationship_type_id' => $relType2,
        'contact_id_b' => $this->_cId_b2,
      ])
    );

    // Relationship 3.
    $this->callAPISuccess($this->entity, 'create',
      array_merge($this->_params, [
        'relationship_type_id' => $relType3,
        'contact_id_b' => $org3,
      ])
    );

    $result = $this->callAPISuccess($this->entity, 'get', [
      'contact_id' => $this->_cId_a,
      'relationship_type_id' => ['IN' => [$this->relationshipTypeID, $relType3]],
    ]);

    $this->assertEquals(2, $result['count']);
    foreach ($result['values'] as $key => $value) {
      $this->assertTrue(in_array($value['relationship_type_id'], [$this->relationshipTypeID, $relType3]));
    }
  }

  /**
   * Test relationship get by membership type.
   *
   * Checks that passing in 'contact_id_b' + a relationship type
   * will filter by relationship type for contact b
   *
   * We should get 1 result without or with correct relationship type id & 0 with
   * an incorrect one
   *
   * @param int $version
   *
   * @dataProvider versionThreeAndFour
   * @throws \CRM_Core_Exception
   */
  public function testGetRelationshipByMembershipTypeDAO($version) {
    $this->_apiversion = $version;
    $this->callAPISuccess($this->entity, 'create', $this->_params);
    $org3 = $this->organizationCreate();

    // lets just assume built in ones aren't being messed with!
    $relType2 = 5;
    // lets just assume built in ones aren't being messed with!
    $relType3 = 6;
    $relType1 = 1;
    $memberType = $this->membershipTypeCreate([
      'relationship_type_id' => [$relType1, $relType3],
      'relationship_direction' => ['a_b', 'b_a'],
    ]);

    // Relationship 2.
    $this->callAPISuccess($this->entity, 'create',
      array_merge($this->_params, [
        'relationship_type_id' => $relType2,
        'contact_id_b' => $this->_cId_b2,
      ])
    );

    // Relationship 3.
    $this->callAPISuccess($this->entity, 'create',
      array_merge($this->_params, [
        'relationship_type_id' => $relType3,
        'contact_id_b' => $org3,
      ])
    );

    // Relationship 4 with reversal.
    $this->callAPISuccess($this->entity, 'create',
      array_merge($this->_params, [
        'relationship_type_id' => $relType1,
        'contact_id_a' => $this->_cId_a,
        'contact_id_b' => $this->_cId_a_2,
      ])
    );

    $result = $this->callAPISuccess($this->entity, 'get', [
      'contact_id_a' => $this->_cId_a,
      'membership_type_id' => $memberType,
      // Pass version here as there is no intention to replicate support for passing in membership_type_id
      'version' => 3,
    ]);
    // although our contact has more than one relationship we have passed them in as contact_id_a & can't get reciprocal
    $this->assertEquals(1, $result['count']);
    foreach ($result['values'] as $key => $value) {
      $this->assertContainsEquals($value['relationship_type_id'], [$relType1]);
    }
  }

  /**
   * Checks that passing in 'contact_id_b' + a relationship type
   * will filter by relationship type for contact b
   *
   * We should get 1 result without or with correct relationship type id & 0 with
   * an incorrect one
   *
   * @param int $version
   *
   * @dataProvider versionThreeAndFour
   * @throws \CRM_Core_Exception
   */
  public function testGetRelationshipByMembershipTypeReciprocal($version) {
    $this->_apiversion = $version;
    $this->callAPISuccess($this->entity, 'create', $this->_params);
    $org3 = $this->organizationCreate();

    // Let's just assume built in ones aren't being messed with!
    $relType2 = 5;
    $relType3 = 6;
    $relType1 = 1;
    $memberType = $this->membershipTypeCreate([
      'relationship_type_id' => [$relType1, $relType3],
      'relationship_direction' => ['a_b', 'b_a'],
    ]);

    // Relationship 2.
    $this->callAPISuccess($this->entity, 'create',
      array_merge($this->_params, [
        'relationship_type_id' => $relType2,
        'contact_id_b' => $this->_cId_b2,
      ])
    );

    // Relationship 4.
    $this->callAPISuccess($this->entity, 'create',
      array_merge($this->_params, [
        'relationship_type_id' => $relType3,
        'contact_id_b' => $org3,
      ])
    );

    // Relationship 4 with reversal.
    $this->callAPISuccess($this->entity, 'create',
      array_merge($this->_params, [
        'relationship_type_id' => $relType1,
        'contact_id_a' => $this->_cId_a,
        'contact_id_b' => $this->_cId_a_2,
      ])
    );

    $result = $this->callAPISuccess($this->entity, 'get', [
      'contact_id' => $this->_cId_a,
      'membership_type_id' => $memberType,
      // There is no intention to replicate support for passing in membership_type_id
      // in apiv4 so pass version as 3
      'version' => 3,
    ]);
    // Although our contact has more than one relationship we have passed them in as contact_id_a & can't get reciprocal
    $this->assertEquals(2, $result['count']);

    foreach ($result['values'] as $key => $value) {
      $this->assertTrue(in_array($value['relationship_type_id'], [$relType1, $relType3]));
    }
  }

  /**
   * Check for e-notices on enable & disable as reported in CRM-14350
   *
   * @param int $version
   *
   * @dataProvider versionThreeAndFour
   *
   * @throws \CRM_Core_Exception
   */
  public function testSetActive($version) {
    $this->_apiversion = $version;
    $relationship = $this->callAPISuccess($this->entity, 'create', $this->_params);
    $this->callAPISuccess($this->entity, 'create', ['id' => $relationship['id'], 'is_active' => 0]);
    $this->callAPISuccess($this->entity, 'create', ['id' => $relationship['id'], 'is_active' => 1]);
  }

  /**
   * Test creating related memberships.
   *
   * @param int $version
   *
   * @dataProvider versionThreeAndFour
   */
  public function testCreateRelatedMembership(int $version): void {
    $this->_apiversion = $version;
    $mainContactID = $this->organizationCreate();
    $relatedMembershipType = $this->callAPISuccess('MembershipType', 'create', [
      'name' => 'Membership with Related',
      'member_of_contact_id' => 1,
      'financial_type_id' => 1,
      'minimum_fee' => 0.00,
      'duration_unit' => 'year',
      'duration_interval' => 1,
      'period_type' => 'rolling',
      'relationship_type_id' => $this->relationshipTypeID,
      'relationship_direction' => 'b_a',
      'visibility' => 'Public',
      'auto_renew' => 0,
      'is_active' => 1,
      'domain_id' => CRM_Core_Config::domainID(),
    ]);
    $originalMembership = $this->callAPISuccess('Membership', 'create', [
      'membership_type_id' => $relatedMembershipType['id'],
      'contact_id' => $mainContactID,
    ]);
    $this->callAPISuccess('Relationship', 'create', [
      'relationship_type_id' => $this->relationshipTypeID,
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $mainContactID,
    ]);
    $contactAMembership = $this->callAPISuccessGetSingle('membership', ['contact_id' => $this->_cId_a]);
    $this->assertEquals($originalMembership['id'], $contactAMembership['owner_membership_id']);

    // Adding a relationship with a future start date should NOT create a membership
    $this->callAPISuccess('Relationship', 'create', [
      'relationship_type_id' => $this->relationshipTypeID,
      'contact_id_a' => $this->_cId_a_2,
      'contact_id_b' => $mainContactID,
      'start_date' => 'now + 1 week',
    ]);
    $this->callAPISuccessGetCount('membership', ['contact_id' => $this->_cId_a_2], 0);

    // Deleting the organization should cause the related membership to be deleted.
    $this->callAPISuccess('Contact', 'delete', ['id' => $mainContactID]);
    $this->callAPISuccessGetCount('Membership', ['contact_id' => $this->_cId_a], 0);
  }

  /**
   * Test api respects is_current_employer.
   *
   * @throws \CRM_Core_Exception
   */
  public function testRelationshipCreateWithEmployerData(): void {
    // CASE A: Create a current employee relationship without setting end date, ensure that employer field is set
    $params = [
      'relationship_type_id' => '5_a_b',
      'related_contact_id' => $this->_cId_b,
      'start_date' => '2008-12-20',
      'end_date' => NULL,
      'is_active' => 1,
      'is_current_employer' => 1,
      'is_permission_a_b' => 0,
      'is_permission_b_a' => 0,
    ];
    $reln = new CRM_Contact_Form_Relationship();
    $reln->_action = CRM_Core_Action::ADD;
    $reln->_contactId = $this->_cId_a;
    [$params, $relationshipIds] = $reln->submit($params);
    $this->assertEquals(
      $this->_cId_b,
      $this->callAPISuccess('Contact', 'getvalue', [
        'id' => $this->_cId_a,
        'return' => 'current_employer_id',
      ]));
    // CASE B: Create a past employee relationship by setting end date of past, ensure that employer field is cleared
    $params = [
      'relationship_type_id' => '5_a_b',
      'related_contact_id' => $this->_cId_b,
      // set date to past date
      'end_date' => '2010-12-20',
    ];
    $reln->_action = CRM_Core_Action::UPDATE;
    $reln->_relationshipId = $relationshipIds[0];
    [$params, $relationshipIds] = $reln->submit($params);
    $this->assertEmpty($this->callAPISuccess('Contact', 'getvalue', [
      'id' => $this->_cId_a,
      'return' => 'current_employer_id',
    ]));
    $this->callAPISuccess('relationship', 'delete', ['id' => $relationshipIds[0]]);
  }

  /**
   * Test disabling an expired relationship does not incorrectly clear employer_id.
   *
   * See https://lab.civicrm.org/dev/core/issues/470
   *
   * @throws \CRM_Core_Exception
   */
  public function testDisableExpiredRelationships(): void {
    // Step 1: Create a current employer relationship with Org A
    $params = [
      'relationship_type_id' => '5',
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'start_date' => '2008-12-20',
      'end_date' => NULL,
      'is_active' => 1,
      'is_current_employer' => 1,
      'is_permission_a_b' => 0,
      'is_permission_b_a' => 0,
    ];
    $this->callAPISuccess('Relationship', 'create', $params);

    // ensure that the employer_id field is sucessfully set
    $this->assertEquals(
      $this->_cId_b,
      $this->callAPISuccess('Contact', 'getvalue', [
        'id' => $this->_cId_a,
        'return' => 'current_employer_id',
      ]));
    // Step 2: Create a PAST employer relationship with Org B, and setting is_current_employer = FALSE
    $orgID2 = $this->organizationCreate();
    $params = [
      'relationship_type_id' => '5',
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $orgID2,
      'start_date' => '2008-12-20',
      'end_date' => '2008-12-22',
      'is_active' => 1,
      'is_current_employer' => 0,
      'is_permission_a_b' => 0,
      'is_permission_b_a' => 0,
    ];

    $relationshipB = $this->callAPISuccess('Relationship', 'create', $params);
    // ensure that the employer_id field is still set to contact b
    $this->assertEquals(
      $this->_cId_b,
      $this->callAPISuccess('Contact', 'getvalue', [
        'id' => $this->_cId_a,
        'return' => 'current_employer_id',
      ]));

    // Step 3: Call schedule job disable_expired_relationships
    CRM_Contact_BAO_Relationship::disableExpiredRelationships();

    // Result A: Ensure that employer field is not cleared
    $this->assertEquals(
      $this->_cId_b,
      $this->callAPISuccess('Contact', 'getvalue', [
        'id' => $this->_cId_a,
        'return' => 'current_employer_id',
      ]));
    // Result B: Ensure that the previous employer relationship with Org B is successfully disabled
    $this->assertEquals(
      FALSE,
      (bool) $this->callAPISuccess('Relationship', 'getvalue', [
        'id' => $relationshipB['id'],
        'return' => 'is_active',
      ]));
  }

  /**
   * This is no longer guarding against the original issue, but is still a test
   * of something. It's now mostly testing a different variation of
   * relationship + the default in api3 being to not check permissions.
   */
  public function testCreateWithLesserPermissions(): void {
    CRM_Core_Config::singleton()->userPermissionClass->permissions = [];
    $params = [
      'contact_id_a' => $this->_cId_a,
      'contact_id_b' => $this->_cId_b,
      'relationship_type_id' => $this->relationshipTypeID,
    ];
    $id = $this->callAPISuccess('Relationship', 'create', $params)['id'];
    $relationship = $this->callAPISuccess('Relationship', 'getsingle', ['id' => $id]);
    $this->assertEquals($params, array_intersect_key($relationship, $params));
  }

}

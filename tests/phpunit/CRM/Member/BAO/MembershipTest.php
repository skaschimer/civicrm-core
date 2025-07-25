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

use Civi\Api4\Membership;
use Civi\Api4\MembershipLog;
use Civi\Api4\MembershipStatus;
use Civi\Test\ContributionPageTestTrait;

/**
 * Class CRM_Member_BAO_MembershipTest
 * @group headless
 */
class CRM_Member_BAO_MembershipTest extends CiviUnitTestCase {
  use ContributionPageTestTrait;

  private $_membershipStatusID;

  /**
   */
  public function setUp(): void {
    parent::setUp();
    $this->membershipTypeCreate(['minimum_fee' => 100], 'General');
    // add a random number to avoid silly conflicts with old data
    $this->_membershipStatusID = $this->membershipStatusCreate('test status');
  }

  /**
   * Tears down the fixture, for example, closes a network connection.
   * This method is called after a test is executed.
   */
  public function tearDown(): void {
    $this->quickCleanUpFinancialEntities();
    parent::tearDown();
  }

  /**
   * Create membership type using given organization id.
   *
   * @param int $organizationId
   * @param bool $withRelationship
   * @param int $maxRelated
   *
   * @return array
   */
  private function createMembershipType(int $organizationId, bool $withRelationship = FALSE, int $maxRelated = 0): array {
    return $this->createTestEntity('MembershipType', [
      //Default domain ID
      'domain_id' => 1,
      'member_of_contact_id' => $organizationId,
      'financial_type_id:name' => 'Member Dues',
      'duration_unit' => 'year',
      'duration_interval' => 1,
      'period_type' => 'rolling',
      'name' => 'Organization Membership Type',
      'relationship_type_id' => ($withRelationship) ? 5 : NULL,
      'relationship_direction' => ($withRelationship) ? 'b_a' : NULL,
      'max_related' => $maxRelated ?: NULL,
    ], 'organization');
  }

  /**
   * Get count of related memberships by parent membership id.
   *
   * @param int $membershipId
   *
   * @return int
   */
  private function getRelatedMembershipsCount(int $membershipId): int {
    return $this->callAPISuccess("Membership", 'getcount', [
      'owner_membership_id' => $membershipId,
    ]);
  }

  /**
   * Test to delete related membership when type of parent membership is changed which does not have relation type associated.
   */
  public function testDeleteRelatedMembershipsOnParentTypeChanged(): void {

    $contactId = $this->individualCreate();
    $membershipOrganizationId = $this->organizationCreate();
    $organizationId = $this->organizationCreate();

    // Create relationship between organization and individual contact
    $this->createTestEntity('Relationship', [
      // Employer of relationship
      'relationship_type_id:name' => 'Employee of',
      'contact_id_a' => $contactId,
      'contact_id_b' => $organizationId,
      'is_active' => 1,
    ]);

    // Create two membership types one with relationship and one without.
    $membershipTypeWithRelationship = $this->createMembershipType($membershipOrganizationId, TRUE);
    $membershipTypeWithoutRelationship = $this->createMembershipType($membershipOrganizationId);

    // Creating membership of organisation
    $membership = $this->createTestEntity('Membership', [
      'membership_type_id' => $membershipTypeWithRelationship["id"],
      'contact_id'         => $organizationId,
      'status_id:name'          => 'test status',
    ], 'first');

    // Check count of related memberships. It should be one for individual contact.
    $relatedMembershipsCount = $this->getRelatedMembershipsCount($this->ids['Membership']['first']);
    $this->assertEquals(1, $relatedMembershipsCount, 'Related membership count should be 1.');

    // Update membership by changing it's type. New membership type is without relationship.
    $membership["membership_type_id"] = $membershipTypeWithoutRelationship["id"];
    Membership::update()
      ->setValues($membership)
      ->execute();

    // Check count of related memberships again. It should be zero as we changed the membership type.
    $relatedMembershipsCount = $this->getRelatedMembershipsCount($membership["id"]);
    $this->assertEquals(0, $relatedMembershipsCount, 'Related membership count should be 0.');
  }

  /**
   */
  public function testGetValues(): void {
    //        $this->markTestSkipped( 'causes mysterious exit, needs fixing!' );
    //  Calculate membership dates based on the current date
    $now = time();
    $year_from_now = $now + (365 * 24 * 60 * 60);
    $last_month = $now - (30 * 24 * 60 * 60);
    $year_from_last_month = $last_month + (365 * 24 * 60 * 60);

    $contactId = $this->individualCreate();

    $params = [
      'contact_id' => $contactId,
      'membership_type_id' => $this->ids['MembershipType']['General'],
      'join_date' => date('Ymd'),
      'start_date' => date('Ymd'),
      'end_date' => date('Ymd', $year_from_now),
      'source' => 'Payment',
      'is_override' => 1,
      'status_id:name' => 'test status',
    ];

    $this->createTestEntity('Membership', $params);

    $membershipId1 = $this->assertDBNotNull('CRM_Member_BAO_Membership', $contactId, 'id',
      'contact_id', 'Database check for created membership.'
    );

    $params = [
      'contact_id' => $contactId,
      'membership_type_id' => $this->ids['MembershipType']['General'],
      'join_date' => date('Ymd', $last_month),
      'start_date' => date('Ymd', $last_month),
      'end_date' => date('Ymd', $year_from_last_month),
      'source' => 'Source123',
      'is_override' => 0,
      'status_id:name' => 'test status',
    ];

    $this->createTestEntity('Membership', $params);

    $membershipId2 = $this->assertDBNotNull('CRM_Member_BAO_Membership', 'source123', 'id',
      'source', 'Database check for created membership.'
    );

    $membership = ['contact_id' => $contactId];
    $membershipValues = [];
    CRM_Member_BAO_Membership::getValues($membership, $membershipValues, TRUE);

    $this->assertEquals($membershipValues[$membershipId1]['membership_id'], $membershipId1, 'Verify membership record 1 is fetched.');

    $this->assertEquals($membershipValues[$membershipId2]['membership_id'], $membershipId2, 'Verify membership record 2 is fetched.');
  }

  /**
   */
  public function testRetrieve(): void {
    [, $membershipId] = $this->setupMembership();
    $params = ['id' => $membershipId];
    $values = [];
    CRM_Member_BAO_Membership::retrieve($params, $values);
    $this->assertEquals($values['id'], $membershipId, 'Verify membership record is retrieved.');
  }

  /**
   */
  public function testActiveMembers(): void {
    $contactId = $this->individualCreate();

    $this->createTestEntity('Membership', [
      'contact_id' => $contactId,
      'membership_type_id:name' => 'General',
      'status_id:name' => 'test status',
      'join_date' => date('Ymd', strtotime('2006-01-21')),
      'start_date' => date('Ymd', strtotime('2006-01-21')),
      'end_date' => date('Ymd', strtotime('2006-12-21')),
      'source' => 'Payment',
      'is_override' => 1,
    ]);

    $membershipId1 = Membership::get()->execute()->single()['id'];

    $params = ['id' => $membershipId1];
    $values1 = [];
    CRM_Member_BAO_Membership::retrieve($params, $values1);
    $membership = [$membershipId1 => $values1];

    $this->createTestEntity('Membership', [
      'contact_id' => $contactId,
      'membership_type_id' => $this->ids['MembershipType']['General'],
      'join_date' => date('Ymd', strtotime('2006-01-21')),
      'start_date' => date('Ymd', strtotime('2006-01-21')),
      'end_date' => date('Ymd', strtotime('2006-12-21')),
      'source' => 'PaySource',
      'is_override' => 1,
      'status_id:name' => 'Current',
    ], 'override');

    $membershipId2 = Membership::get()->addWhere('source', '=', 'PaySource')->execute()->single()['id'];;

    $params = ['id' => $membershipId2];
    $values2 = [];
    CRM_Member_BAO_Membership::retrieve($params, $values2);
    $membership[$membershipId2] = $values2;

    $activeMembers = CRM_Member_BAO_Membership::activeMembers($membership);
    $inActiveMembers = CRM_Member_BAO_Membership::activeMembers($membership, 'inactive');

    $this->assertEquals($activeMembers[$membershipId1]['id'], $membership[$membershipId1]['id'], 'Verify active membership record is retrieved.');
    $this->assertEquals($activeMembers[$membershipId2]['id'], $membership[$membershipId2]['id'], 'Verify active membership record is retrieved.');

    $this->assertCount(0, $inActiveMembers, 'Verify No inactive membership record is retrieved.');
  }

  /**
   */
  public function testDeleteMembership(): void {
    [$contactId, $membershipId] = $this->setupMembership();
    CRM_Member_BAO_Membership::del($membershipId);

    $this->assertDBNull('CRM_Member_BAO_Membership', $contactId, 'id',
      'contact_id', 'Database check for deleted membership.'
    );
    $this->assertDBNull('CRM_Price_BAO_LineItem', $membershipId, 'id',
      'entity_id', 'Database check for deleted line item.'
    );
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function testGetContactMembership(): void {
    [$contactId, $membershipId] = $this->setupMembership();
    $membership = CRM_Member_BAO_Membership::getContactMembership($contactId, $this->ids['MembershipType']['General'], FALSE);
    $this->assertEquals($membership['id'], $membershipId, 'Verify membership record is retrieved.');
  }

  public function testGetAllContactMembership(): void {
    $lifetimeTypeId = $this->membershipTypeCreate([
      'name' => 'Lifetime',
      'duration_unit' => 'lifetime',
    ]);

    // Contact 1 tests the "lifetimeOnly" code path.
    $contactId = $this->individualCreate();

    $params = [
      'contact_id' => $contactId,
      'membership_type_id:name' => 'General',
      'source' => 'Payment',
      'is_override' => 1,
      'status_id:name' => 'Pending',
    ];

    $this->createTestEntity('Membership', $params, 'pending');
    $memberships = CRM_Member_BAO_Membership::getAllContactMembership($contactId, FALSE, TRUE);
    $this->assertEmpty($memberships, 'Verify pending membership is NOT retrieved.');
    $this->membershipDelete($this->ids['Membership']['pending']);

    $params['status_id:name'] = 'Cancelled';
    $this->createTestEntity('Membership', $params, 'cancelled');
    $memberships = CRM_Member_BAO_Membership::getAllContactMembership($contactId, FALSE, TRUE);
    $this->assertEmpty($memberships, 'Verify cancelled membership is NOT retrieved.');
    $this->membershipDelete($this->ids['Membership']['cancelled']);

    // Lifetime membership.
    $params['membership_type_id:name'] = 'Lifetime';
    $params['status_id:name'] = 'Current';
    $this->createTestEntity('Membership', $params, 'current');
    $membershipId = $this->assertDBNotNull('CRM_Member_BAO_Membership', $contactId, 'id', 'contact_id', 'Database check for created membership.');
    $memberships = CRM_Member_BAO_Membership::getAllContactMembership($contactId, FALSE, TRUE);
    $this->assertEquals($membershipId, $memberships[$lifetimeTypeId]['id'], 'Verify current (lifetime) membership IS retrieved.');
  }

  /**
   * Get the contribution.
   * page id from the membership record
   */
  public function testGetContributionPageId(): void {
    CRM_Member_BAO_Membership::getContributionPageId($this->setupMembership()[1]);
  }

  /**
   * Get membership joins/renewals
   * for a specified membership
   * type.
   */
  public function testGetMembershipStarts(): void {
    $this->setupMembership();
    $yearStart = date('Y') . '0101';
    $currentDate = date('Ymd');
    CRM_Member_BAO_Membership::getMembershipStarts($this->ids['MembershipType']['General'], $yearStart, $currentDate);
  }

  /**
   * Get a count of membership for a specified membership type,
   * optionally for a specified date.
   *
   * @throws \CRM_Core_Exception
   */
  public function testGetMembershipCount(): void {
    $this->setupMembership();
    $currentDate = date('Ymd');
    $test = 0;
    CRM_Member_BAO_Membership::getMembershipCount($this->ids['MembershipType']['General'], $currentDate, $test);
  }

  /**
   * Checkup sort name function.
   */
  public function testSortName(): void {
    $contactId = $this->individualCreate();

    $params = [
      'contact_id' => $contactId,
      'membership_type_id' => $this->ids['MembershipType']['General'],
      'join_date' => '2006-01-21',
      'start_date' => '2006-01-21',
      'end_date' => '2006-12-21',
      'source' => 'Payment',
      'is_override' => 1,
      'status_id' => $this->_membershipStatusID,
    ];

    $membership = $this->callAPISuccess('Membership', 'create', $params);

    $this->assertEquals('Anderson, Anthony II', CRM_Member_BAO_Membership::sortName($membership['id']));
  }

  /**
   * Delete related memberships.
   */
  public function testDeleteRelatedMemberships(): void {
    [, $membershipId] = $this->setupMembership();
    CRM_Member_BAO_Membership::deleteRelatedMemberships($membershipId);
  }

  /**
   * Renew membership with change in membership type.
   *
   * @throws \CRM_Core_Exception
   */
  public function testRenewMembership(): void {
    $this->individualCreate();
    $joinDate = $startDate = date("Ymd", strtotime(date("Ymd") . " -6 month"));
    $endDate = date("Ymd", strtotime($joinDate . " +1 year -1 day"));
    $params = [
      'contact_id' => $this->ids['Contact']['individual_0'],
      'membership_type_id:name' => 'General',
      'join_date' => $joinDate,
      'start_date' => $startDate,
      'end_date' => $endDate,
      'source' => 'Payment',
      'is_override' => 1,
      'status_id:name' => 'Current',
    ];

    $this->createTestEntity('Membership', $params, 'membership');

    $membership = $this->callAPISuccessGetSingle('Membership', ['contact_id' => $this->ids['Contact']['individual_0']]);
    MembershipLog::get()->addWhere('id', '>', '0')
      ->execute()->single();

    $this->contributionPageQuickConfigCreate();
    $this->submitOnlineContributionForm([
      'contact_id' => $this->ids['Contact']['individual_0'],
      'price_' . $this->ids['PriceField']['membership_amount'] => $this->ids['PriceFieldValue']['membership_general'],
    ] + $this->getBillingSubmitValues());

    $membershipRenewed = Membership::get()->addWhere('id', '=', $this->ids['Membership']['membership'])
      ->execute()->single();
    $endDate = date("Y-m-d", strtotime($membership['end_date'] . " +1 year"));
    $this->assertEquals($this->ids['MembershipType']['General'], $membershipRenewed['membership_type_id'], 'Verify membership type is changed during renewal.');
    $this->assertEquals($endDate, $membershipRenewed['end_date'], 'Verify correct end date is calculated after membership renewal');
  }

  /**
   * Renew stale membership.
   *
   * @throws \CRM_Core_Exception
   */
  public function testStaleMembership(): void {
    $joinDate = date("Ymd", strtotime(date("Ymd") . " -1 year -15 days"));

    $this->createTestEntity('Membership', [
      'contact_id' => $this->individualCreate(),
      'membership_type_id:name' => 'General',
      'join_date' => $joinDate,
      'start_date' => $joinDate,
      'end_date' => date('Ymd', strtotime($joinDate . ' +1 year -1 day')),
      'source' => 'Payment',
      'status_id:name' => 'Grace',
    ]);

    $membership = Membership::get()->addSelect('status_id:name')->execute()->single();
    $this->assertEquals('Grace', $membership['status_id:name'], 'Verify correct status id is calculated.');

    $this->contributionPageQuickConfigCreate();
    $this->submitOnlineContributionForm([
      'contact_id' => $this->ids['Contact']['individual_0'],
      'price_' . $this->ids['PriceField']['membership_amount'] => $this->ids['PriceFieldValue']['membership_general'],
    ] + $this->getBillingSubmitValues(), NULL, ['mid' => $membership['id']]);

    $membership = Membership::get()->addSelect('status_id:name')->execute()->single();
    $this->assertEquals('Current', $membership['status_id:name'], 'Verify correct status id is calculated.');
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function testUpdateAllMembershipStatusConvertExpiredOverriddenStatusToNormal(): void {
    $params = [
      'contact_id' => $this->individualCreate(),
      'membership_type_id' => $this->ids['MembershipType']['General'],
      'join_date' => date('Ymd'),
      'start_date' => date('Ymd'),
      'end_date' => date('Ymd', strtotime('+1 year')),
      'source' => 'Payment',
      'is_override' => 1,
      'status_override_end_date' => date('Ymd', strtotime('-1 day')),
      'status_id:name' => 'test status',
    ];

    $createdMembershipID = $this->createTestEntity('Membership', $params, 'override')['id'];

    civicrm_api3('Job', 'process_membership');

    $membershipAfterProcess = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'id' => $createdMembershipID,
      'return' => ['id', 'is_override', 'status_override_end_date'],
    ])['values'][0];

    $this->assertEquals($createdMembershipID, $membershipAfterProcess['id']);
    $this->assertArrayNotHasKey('status_override_end_date', $membershipAfterProcess);

    // Check that MembershipLog was created and is correct
    $latestMembershipLog = MembershipLog::get(FALSE)
      ->addWhere('membership_id', '=', $createdMembershipID)
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();
    $newMembershipStatus = MembershipStatus::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'New')
      ->execute()
      ->first();
    $this->assertEquals($newMembershipStatus['id'], $latestMembershipLog['status_id']);
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function testUpdateAllMembershipStatusHandleOverriddenWithEndOverrideDateEqualTodayAsExpired(): void {
    $params = [
      'contact_id' => $this->individualCreate(),
      'membership_type_id' => $this->ids['MembershipType']['General'],
      'join_date' => date('Ymd'),
      'start_date' => date('Ymd'),
      'end_date' => date('Ymd', strtotime('+1 year')),
      'source' => 'Payment',
      'is_override' => 1,
      'status_override_end_date' => date('Ymd'),
      'status_id' => $this->_membershipStatusID,
    ];

    $createdMembershipID = $this->callAPISuccess('Membership', 'create', $params)['id'];

    civicrm_api3('Job', 'process_membership');

    $membershipAfterProcess = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'id' => $createdMembershipID,
      'return' => ['id', 'is_override', 'status_override_end_date'],
    ])['values'][0];

    $this->assertEquals($createdMembershipID, $membershipAfterProcess['id']);
    $this->assertArrayNotHasKey('status_override_end_date', $membershipAfterProcess);

    // Check that MembershipLog was created and is correct
    $latestMembershipLog = MembershipLog::get(FALSE)
      ->addWhere('membership_id', '=', $createdMembershipID)
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();
    $newMembershipStatus = MembershipStatus::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'New')
      ->execute()
      ->first();
    $this->assertEquals($newMembershipStatus['id'], $latestMembershipLog['status_id']);
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function testUpdateAllMembershipStatusDoesNotConvertOverriddenMembershipWithoutEndOverrideDateToNormal(): void {
    $params = [
      'contact_id' => $this->individualCreate(),
      'membership_type_id' => $this->ids['MembershipType']['General'],
      'join_date' => date('Ymd'),
      'start_date' => date('Ymd'),
      'end_date' => date('Ymd', strtotime('+1 year')),
      'source' => 'Payment',
      'is_override' => 1,
      'status_id' => $this->_membershipStatusID,
    ];

    $createdMembershipID = $this->callAPISuccess('Membership', 'create', $params)['id'];

    $this->callAPISuccess('Job', 'process_membership');

    $membershipAfterProcess = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'id' => $createdMembershipID,
      'return' => ['id', 'is_override', 'status_override_end_date'],
    ])['values'][0];

    $this->assertEquals($createdMembershipID, $membershipAfterProcess['id']);
    $this->assertEquals(1, $membershipAfterProcess['is_override']);

    // Check that MembershipLog was created and is correct
    $latestMembershipLog = MembershipLog::get(FALSE)
      ->addWhere('membership_id', '=', $createdMembershipID)
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();
    $this->assertEquals($this->_membershipStatusID, $latestMembershipLog['status_id']);
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function testMembershipPaymentForSingleContributionMultipleMembership(): void {
    $membershipTypeID1 = $this->membershipTypeCreate(['name' => 'Parent']);
    $membershipTypeID2 = $this->membershipTypeCreate(['name' => 'Child']);
    $financialTypeId = $this->getFinancialTypeID('Member Dues');
    $priceSet = $this->callAPISuccess('price_set', 'create', [
      'is_quick_config' => 0,
      'extends' => 'CiviMember',
      'financial_type_id' => $financialTypeId,
      'title' => 'Family Membership',
    ]);
    $priceSetID = $priceSet['id'];
    $priceField = $this->callAPISuccess('price_field', 'create', [
      'price_set_id' => $priceSetID,
      'label' => 'Memberships',
      'html_type' => 'Radio',
    ]);
    $priceFieldValue = $this->callAPISuccess('price_field_value', 'create', [
      'price_set_id' => $priceSetID,
      'price_field_id' => $priceField['id'],
      'label' => 'Parent',
      'amount' => 100,
      'financial_type_id' => $financialTypeId,
      'membership_type_id' => $membershipTypeID1,
      'membership_num_terms' => 1,
    ]);
    $priceFieldValueId = [1 => $priceFieldValue['id']];
    $priceFieldValue = $this->callAPISuccess('price_field_value', 'create', [
      'price_set_id' => $priceSetID,
      'price_field_id' => $priceField['id'],
      'label' => 'Child',
      'amount' => 50,
      'financial_type_id' => $financialTypeId,
      'membership_type_id' => $membershipTypeID2,
      'membership_num_terms' => 1,
    ]);
    $priceFieldValueId[2] = $priceFieldValue['id'];
    $parentContactId = $this->individualCreate();
    $contributionRecur = $this->callAPISuccess('contribution_recur', 'create', [
      'contact_id' => $parentContactId,
      'amount' => 150,
      'frequency_unit' => 'day',
      'frequency_interval' => 1,
      'installments' => 2,
      'start_date' => 'yesterday',
      'create_date' => 'yesterday',
      'modified_date' => 'yesterday',
      'cancel_date' => NULL,
      'end_date' => '+ 2 weeks',
      'processor_id' => '643411460836',
      'trxn_id' => 'e0d0808e26f3e661c6c18eb7c039d363',
      'invoice_id' => 'e0d0808e26f3e661c6c18eb7c039d363',
      'contribution_status_id' => 'In Progress',
      'cycle_day' => 1,
      'next_sched_contribution_date' => '+ 1 week',
      'auto_renew' => 0,
      'currency' => 'USD',
      'payment_processor_id' => $this->paymentProcessorCreate(),
      'financial_type_id' => $financialTypeId,
      'payment_instrument_id' => 'Credit Card',
    ]);

    $contribution = $this->callAPISuccess('Order', 'create', [
      'total_amount' => 150,
      'contribution_recur_id' => $contributionRecur['id'],
      'currency' => 'USD',
      'contact_id' => $parentContactId,
      'financial_type_id' => $financialTypeId,
      'contribution_status_id' => 'Pending',
      'is_recur' => TRUE,
      'api.Payment.create' => ['total_amount' => 150],
      'line_items' => [
        [
          'line_item' => [
            [
              'price_field_id' => $priceField['id'],
              'price_field_value_id' => $priceFieldValueId[1],
              'label' => 'Parent',
              'membership_type_id' => $membershipTypeID1,
              'qty' => 1,
              'unit_price' => 100,
              'line_total' => 100,
              'financial_type_id' => $financialTypeId,
              'entity_table' => 'civicrm_membership',
            ],
          ],
          'params' => [
            'contact_id' => $this->individualCreate(),
            'membership_type_id' => $membershipTypeID2,
            'contribution_recur_id' => $contributionRecur['id'],
            'join_date' => date('Ymd'),
            'start_date' => date('Ymd'),
            'end_date' => date('Ymd', strtotime('+1 year')),
            'source' => 'Payment',
          ],
        ],
        [
          'line_item' => [
            [
              'price_field_id' => $priceField['id'],
              'price_field_value_id' => $priceFieldValueId[2],
              'label' => 'Child',
              'qty' => 1,
              'unit_price' => 50,
              'line_total' => 50,
              'membership_type_id' => $membershipTypeID2,
              'financial_type_id' => $financialTypeId,
              'entity_table' => 'civicrm_membership',
            ],
          ],
          'params' => [
            'contact_id' => $this->individualCreate(),
            'membership_type_id' => $membershipTypeID2,
            'contribution_recur_id' => $contributionRecur['id'],
            'join_date' => date('Ymd'),
            'start_date' => date('Ymd'),
            'end_date' => date('Ymd', strtotime('+1 year')),
            'source' => 'Payment',
          ],
        ],
      ],
    ]);

    $this->callAPISuccess('Contribution', 'repeattransaction', [
      'original_contribution_id' => $contribution['id'],
      'contribution_status_id' => 'Completed',
    ]);
    $contributions = $this->callAPISuccess('Contribution', 'get', ['sequential' => 1])['values'];
    $this->assertCount(2, $contributions);
    $this->assertEquals('Debit Card', CRM_Core_PseudoConstant::getName('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', $contributions[1]['payment_instrument_id']));
    $this->validateAllPayments();
    $this->validateAllContributions();
  }

  /**
   * Test the buildMembershipTypeValues function.
   *
   * @throws \CRM_Core_Exception
   */
  public function testBuildMembershipTypeValues(): void {
    $this->restoreMembershipTypes();
    $form = new CRM_Core_Form();
    $values = CRM_Member_BAO_Membership::buildMembershipTypeValues($form);
    $this->assertEquals([
      'id' => 1,
      'minimum_fee' => 100.00,
      'name' => 'General',
      'is_active' => 1,
      'description' => 'Regular annual membership.',
      'financial_type_id' => 2,
      'auto_renew' => 0,
      'member_of_contact_id' => $values[1]['member_of_contact_id'],
      'relationship_type_id' => [7],
      'relationship_direction' => ['b_a'],
      'max_related' => NULL,
      'duration_unit' => 'year',
      'duration_interval' => 2,
      'domain_id' => 1,
      'period_type' => 'rolling',
      'visibility' => 'Public',
      'weight' => 1,
      'tax_rate' => 0.0,
      'minimum_fee_with_tax' => 100.0,
      'fixed_period_start_day' => NULL,
      'fixed_period_rollover_day' => NULL,
      'receipt_text_signup' => NULL,
      'receipt_text_renewal' => NULL,
    ], $values[1]);
  }

  /**
   * @return array
   */
  protected function setupMembership(): array {
    $contactId = $this->individualCreate();

    $params = [
      'contact_id' => $contactId,
      'membership_type_id' => $this->ids['MembershipType']['General'],
      'join_date' => date('Ymd', strtotime('2006-01-21')),
      'start_date' => date('Ymd', strtotime('2006-01-21')),
      'end_date' => date('Ymd', strtotime('2006-12-21')),
      'source' => 'Payment',
      'is_override' => 1,
      'status_id' => $this->_membershipStatusID,
    ];

    $this->callAPISuccess('Membership', 'create', $params);

    $membershipId = $this->assertDBNotNull('CRM_Member_BAO_Membership', $contactId, 'id',
      'contact_id', 'Database check for created membership.'
    );
    return [$contactId, $membershipId];
  }

  /**
   * Test done to verify bug dev/core#1854 remains fixed.
   *
   * Under certain special circumstances, updating a membership that had related
   * memberships and a maximum related value, resulted in some related
   * memberships being deleted, even though the maximum value was not reached.
   *
   * The problem presented itself when a membership is found for the nth contact
   * related to the organization, the nth+1 contact didn't have a membership,
   * and the nth+b contact does have a membership, where b > 1.
   *
   * This test builds that scenario and checks updating the status of the
   * membership does not cause the deletion of memberships.
   *
   * https://lab.civicrm.org/dev/core/-/issues/1854
   *
   * @throws \CRM_Core_Exception
   * @throws \Exception
   */
  public function testMembershipUpdateDoesNotDeleteRelatedMembershipsByMistake(): void {
    $membershipOrganizationId = $this->organizationCreate();
    $employerId = $this->organizationCreate();

    // Create membership type with relationship.
    $membershipTypeWithRelationship = $this->createMembershipType($membershipOrganizationId, TRUE, 2);

    // Creating membership for employer.
    $membership = $this->callAPISuccess("Membership", "create", [
      'membership_type_id' => $membershipTypeWithRelationship["id"],
      'contact_id'         => $employerId,
      'status_id'          => $this->_membershipStatusID,
    ]);
    $membership = $membership['values'][$membership["id"]];
    $this->assertEquals(0, $this->getRelatedMembershipsCount($membership["id"]), 'Related membership count should be 0.');

    // Create relationship between organization and individual contacts.
    $employees = $this->createContacts(5);
    foreach ($employees as $contactID) {
      $this->createTestEntity('Relationship', [
        // Employer of relationship
        'relationship_type_id:name' => 'Employee of',
        'contact_id_a'         => $contactID,
        'contact_id_b'         => $employerId,
        'is_active'            => 1,
      ]);
    }
    $this->deleteRelatedMemberships($membership["id"]);
    $this->assertEquals(0, $this->getRelatedMembershipsCount($membership["id"]), 'Related membership count should be 0.');

    // Create related memberships for first and last contact.
    $relatedMembership1 = $this->createRelatedMembershipForContact($employees[0], $membership);
    $relatedMembership2 = $this->createRelatedMembershipForContact($employees[4], $membership);
    $this->assertEquals(2, $this->getRelatedMembershipsCount($membership["id"]), 'Related membership count should be 2.');

    // Reset statics.
    unset(Civi::$statics[CRM_Member_BAO_Membership::class]['related_contacts']);

    // Update membership by changing its status.
    $otherStatusID = $this->membershipStatusCreate('another status ' . random_int(1, 1000));
    $membership["status_id"] = $otherStatusID;
    $this->callAPISuccess("Membership", "create", $membership);

    // Assert nothing has changed.
    $relatedMembershipsCount = $this->getRelatedMembershipsCount($membership["id"]);
    $this->assertEquals(2, $relatedMembershipsCount, "Related membership count should still be 2, but found $relatedMembershipsCount");
    $this->assertMembershipExists($relatedMembership1['id']);
    $this->assertMembershipExists($relatedMembership2['id']);
  }

  public function testRelatedMembershipWithContactReferenceCustomField(): void {
    $relatedContactId = $this->individualCreate();
    $customContactId = $this->individualCreate();
    $membershipOrganizationId = $this->organizationCreate();
    $organizationId = $this->organizationCreate();

    $membershipTypeWithRelationship = $this->createMembershipType($membershipOrganizationId, TRUE);
    $customGroup = $this->customGroupCreate(['extends' => 'Membership']);
    $customField = $this->customFieldCreate([
      'custom_group_id' => $customGroup['id'],
      'data_type' => 'ContactReference',
      'html_type' => 'Autocomplete-Select',
      'default_value' => '',
    ]);

    // Creating membership of organisation
    $membership = $this->callAPISuccess("Membership", "create", [
      'membership_type_id' => $membershipTypeWithRelationship["id"],
      'contact_id'         => $organizationId,
      'status_id'          => $this->_membershipStatusID,
      'custom_' . $customField['id'] => $customContactId,
    ]);

    $membership = $membership['values'][$membership["id"]];

    // Create relationship between organization and individual contact
    $this->createTestEntity('Relationship', [
      // Employer of relationship
      'relationship_type_id:name' => 'Employee of',
      'contact_id_a'         => $relatedContactId,
      'contact_id_b'         => $organizationId,
      'is_active'            => 1,
    ]);

    // Check count of related memberships. It should be one for individual contact.
    $relatedMembershipsCount = $this->getRelatedMembershipsCount($membership["id"]);
    $this->assertEquals(1, $relatedMembershipsCount, 'Related membership count should be 1.');

    $relatedMembership = $this->callAPISuccess("Membership", "getsingle", [
      'owner_membership_id' => $membership["id"],
    ]);

    $this->assertEquals($customContactId, $relatedMembership['custom_' . $customField['id'] . '_id']);

    $this->callAPISuccess('Relationship', 'delete', ['id' => $this->ids['Relationship']['default']]);
    $relatedMembershipsCount = $this->getRelatedMembershipsCount($membership["id"]);
    $this->assertEquals(0, $relatedMembershipsCount, 'Related membership count should be 0.');
  }

  /**
   * Creates the given amount of contacts.
   *
   * @param int $count
   *
   * @return array
   */
  private function createContacts(int $count): array {
    $contacts = [];
    for ($i = 0; $i < $count; $i++) {
      $contacts[] = $this->individualCreate();
    }

    return $contacts;
  }

  /**
   * Deletes related memberships for given parent membership ID.
   *
   * @param int $parentMembershipID
   *
   * @throws \CRM_Core_Exception
   */
  private function deleteRelatedMemberships(int $parentMembershipID): void {
    Membership::delete(FALSE)
      ->addWhere('owner_membership_id', '=', $parentMembershipID)
      ->execute();
  }

  /**
   * Creates a related membership for the given contact ID.
   *
   * @param int $contactID
   * @param array $parentMembership
   *
   * @return array
   */
  private function createRelatedMembershipForContact(int $contactID, array $parentMembership): array {
    return $this->createTestEntity('Membership', [
      'membership_type_id' => $parentMembership['membership_type_id'],
      'contact_id'         => $contactID,
      'status_id'          => $this->_membershipStatusID,
      'owner_membership_id' => $parentMembership['id'],
    ]);
  }

  /**
   * Checks the given membership ID can be found.
   *
   * @param int $membershipID
   */
  private function assertMembershipExists(int $membershipID): void {
    try {
      Membership::get(FALSE)
        ->addWhere('id', '=', $membershipID)
        ->execute()->single();
    }
    catch (CRM_Core_Exception $e) {
      $this->fail('assert membership failed' . $e->getMessage());
    }
  }

}

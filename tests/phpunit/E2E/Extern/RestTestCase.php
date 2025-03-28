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

use Civi\Test\HttpTestTrait;

/**
 * Verify that the REST API bindings correctly parse and authenticate requests.
 *
 * @group e2e
 */
abstract class E2E_Extern_RestTestCase extends CiviEndToEndTestCase {

  use HttpTestTrait;

  protected $url;
  protected static $api_key;
  protected $session_id;
  protected $nocms_contact_id;
  protected $old_api_keys;
  protected $adminContactId;

  /**
   * Enable testing of `civicrm/{$entity}/{$action}` from APIv3 REST?
   *
   * The APIv3 REST end-point supported two notations:
   *
   * - `rest.php?entity=ENTITY&action=ACTION`
   * - `rest.php?q=civicrm/ENTITY/ACTON`.
   *
   * The former better documentation, tooling, and compatibility. The latter is conflicted
   * is some cases.
   *
   * @return bool
   */
  abstract protected static function isOldQSupported(): bool;

  /**
   * @param $apiResult
   * @param $cmpvar
   * @param string $prefix
   */
  protected function assertAPIErrorCode($apiResult, $cmpvar, $prefix = '') {
    if (!empty($prefix)) {
      $prefix .= ': ';
    }
    $this->assertEquals($cmpvar, $apiResult['is_error'],
      $prefix . (empty($apiResult['error_message']) ? '' : $apiResult['error_message']));
    //$this->assertEquals($cmpvar, $apiResult['is_error'], $prefix . print_r($apiResult, TRUE));
  }

  protected function setUp(): void {
    parent::setUp();

    if (empty($GLOBALS['_CV']['CIVI_SITE_KEY'])) {
      $this->markTestSkipped('Missing siteKey');
    }

    $this->old_api_keys = [];
  }

  abstract protected function getRestUrl();

  protected function tearDown(): void {
    if (!empty($this->old_api_keys)) {
      foreach ($this->old_api_keys as $cid => $apiKey) {
        civicrm_api3('Contact', 'create', [
          'id' => $cid,
          'api_key' => $apiKey,
        ]);
      }
    }
    parent::tearDown();
    if (isset($this->nocms_contact_id)) {
      $deleteParams = [
        "id" => $this->nocms_contact_id,
        "skip_undelete" => 1,
      ];
      $res = civicrm_api3("Contact", "delete", $deleteParams);
      unset($this->nocms_contact_id);
    }
  }

  /**
   * Build a list of test cases. Each test case defines a set of REST query
   * parameters and an expected outcome for the REST request (eg is_error=>1 or is_error=>0).
   *
   * @return array; each item is a list of parameters for testAPICalls
   */
  public static function apiTestCases() {
    $cases = [];

    // entity,action: omit apiKey, valid entity+action
    $cases[] = [
      // query
      [
        "entity" => "Contact",
        "action" => "get",
        "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
        "json" => "1",
      ],
      // is_error
      1,
    ];

    // entity,action: valid apiKey, valid entity+action
    $cases[] = [
      // query
      [
        "entity" => "Contact",
        "action" => "get",
        "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
        "json" => "1",
        "api_key" => self::getApiKey(),
      ],
      // is_error
      0,
    ];

    // entity,action: bad apiKey, valid entity+action
    $cases[] = [
      // query
      [
        "entity" => "Contact",
        "action" => "get",
        "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
        "json" => "1",
        "api_key" => 'garbage_' . self::getApiKey(),
      ],
      // is_error
      1,
    ];

    // entity,action: valid apiKey, invalid entity+action
    $cases[] = [
      // query
      [
        "entity" => "Contactses",
        "action" => "get",
        "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
        "json" => "1",
        "api_key" => self::getApiKey(),
      ],
      // is_error
      1,
    ];

    // q=civicrm/entity/action: omit apiKey, valid entity+action
    $cases[] = [
      // query
      [
        "q" => "civicrm/contact/get",
        "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
        "json" => "1",
      ],
      // is_error
      1,
    ];

    // q=civicrm/entity/action: valid apiKey, valid entity+action
    $cases[] = [
      // query
      [
        "q" => "civicrm/contact/get",
        "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
        "json" => "1",
        "api_key" => self::getApiKey(),
      ],
      // is_error
      0,
    ];

    // q=civicrm/entity/action: invalid apiKey, valid entity+action
    $cases[] = [
      // query
      [
        "q" => "civicrm/contact/get",
        "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
        "json" => "1",
        "api_key" => 'garbage_' . self::getApiKey(),
      ],
      // is_error
      1,
    ];

    // q=civicrm/entity/action: valid apiKey, invalid entity+action
    $cases[] = [
      // query
      [
        "q" => "civicrm/contactses/get",
        "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
        "json" => "1",
        "api_key" => self::getApiKey(),
      ],
      // is_error
      1,
    ];

    // q=civicrm/entity/action: valid apiKey, invalid entity+action
    // XXX Actually Ping is valid, no?
    $cases[] = [
      // query
      [
        "q" => "civicrm/ping",
        "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
        "json" => "1",
        "api_key" => self::getApiKey(),
      ],
      // is_error
      0,
    ];

    if (!static::isOldQSupported()) {
      $cases = array_filter($cases, function($case) {
        // The 'civicrm/ajax/rest' end-point does not support '?q' inputs.
        return !isset($case[0]['q']);
      });
    }

    return $cases;
  }

  /**
   * @dataProvider apiTestCases
   * @param $query
   * @param $is_error
   */
  public function testAPICalls($query, $is_error) {
    $this->updateAdminApiKey();

    $http = $this->createGuzzle(['http_errors' => FALSE]);
    $response = $http->post($this->getRestUrl(), ['form_params' => $query]);
    $this->assertStatusCode(200, $response);
    $data = (string) $response->getBody();

    $result = json_decode($data, TRUE);
    if ($result === NULL) {
      $msg = print_r([
        'restUrl' => $this->getRestUrl(),
        'query' => $query,
        'response data' => $data,
      ], TRUE);
      $this->assertNotNull($result, $msg);
    }
    $this->assertAPIErrorCode($result, $is_error);
  }

  /**
   * Submit a request with an API key that exists but does not correspond to.
   * a real user. Submit in "?entity=X&action=X" notation
   */
  public function testNotCMSUser_entityAction(): void {
    $http = $this->createGuzzle(['http_errors' => FALSE]);

    //Create contact with api_key
    $test_key = "testing1234";
    $contactParams = [
      "api_key" => $test_key,
      "contact_type" => "Individual",
      "first_name" => "RestTester1",
    ];
    $contact = civicrm_api3("Contact", "create", $contactParams);
    $this->nocms_contact_id = $contact["id"];

    // The key associates with a real contact but not a real user
    $params = [
      "entity" => "Contact",
      "action" => "get",
      "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
      "json" => "1",
      "api_key" => $test_key,
    ];

    $response = $http->post($this->getRestUrl(), ['form_params' => $params]);
    $this->assertStatusCode(200, $response);
    $result = json_decode((string) $response->getBody(), TRUE);
    $this->assertNotNull($result);
    $this->assertAPIErrorCode($result, 1);
  }

  /**
   * Submit a request with an API key that exists but does not correspond to.
   * a real user. Submit in "?entity=X&action=X" notation
   */
  public function testGetCorrectUserBack(): void {
    $this->updateAdminApiKey();
    $http = $this->createGuzzle(['http_errors' => FALSE]);

    //Create contact with api_key
    // The key associates with a real contact but not a real user
    $params = [
      "entity" => "Contact",
      "action" => "get",
      "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
      "json" => "1",
      "api_key" => self::getApiKey(),
      "id" => "user_contact_id",
    ];
    $response = $http->post($this->getRestUrl(), ['form_params' => $params]);
    $this->assertStatusCode(200, $response);
    $result = json_decode((string) $response->getBody(), TRUE);
    $this->assertNotNull($result);
    $this->assertEquals($result['id'], $this->adminContactId);
  }

  /**
   * Submit a request with an API key that exists but does not correspond to
   * a real user. Submit in "?q=civicrm/$entity/$action" notation
   */
  public function testNotCMSUser_q(): void {
    if (!$this->isOldQSupported()) {
      $this->markTestSkipped('rest.php?q=civicrm/ENTITY/ACTION is not supported here');
    }
    $http = $this->createGuzzle(['http_errors' => FALSE]);

    //Create contact with api_key
    $test_key = "testing1234";
    $contactParams = [
      "api_key" => $test_key,
      "contact_type" => "Individual",
      "first_name" => "RestTester1",
    ];
    $contact = civicrm_api3("Contact", "create", $contactParams);
    $this->nocms_contact_id = $contact["id"];

    // The key associates with a real contact but not a real user
    $params = [
      "q" => "civicrm/contact/get",
      "key" => $GLOBALS['_CV']['CIVI_SITE_KEY'],
      "json" => "1",
      "api_key" => $test_key,
    ];
    $response = $http->post($this->getRestUrl(), ['form_params' => $params]);

    $this->assertStatusCode(200, $response);
    $result = json_decode((string) $response->getBody(), TRUE);
    $this->assertNotNull($result);
    $this->assertAPIErrorCode($result, 1);
  }

  protected function updateAdminApiKey() {
    /** @var int $adminContactId */
    $this->adminContactId = civicrm_api3('contact', 'getvalue', [
      'id' => '@user:' . $GLOBALS['_CV']['ADMIN_USER'],
      'return' => 'id',
    ]);

    $this->old_api_keys[$this->adminContactId] = CRM_Core_DAO::singleValueQuery('SELECT api_key FROM civicrm_contact WHERE id = %1', [
      1 => [$this->adminContactId, 'Positive'],
    ]);

    //$this->old_admin_api_key = civicrm_api3('Contact', 'get', array(
    //  'id' => $adminContactId,
    //  'return' => 'api_key',
    //));

    civicrm_api3('Contact', 'create', [
      'id' => $this->adminContactId,
      'api_key' => self::getApiKey(),
    ]);
  }

  protected static function getApiKey() {
    if (empty(self::$api_key)) {
      self::$api_key = mt_rand() . mt_rand();
    }
    return self::$api_key;
  }

}

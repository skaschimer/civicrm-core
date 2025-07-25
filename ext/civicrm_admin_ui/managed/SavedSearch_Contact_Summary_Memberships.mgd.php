<?php
use CRM_CivicrmAdminUi_ExtensionUtil as E;

// Temporary check can be removed when moving this file to the civi_member extension.
if (!CRM_Core_Component::isEnabled('CiviMember')) {
  return [];
}

return [
  [
    'name' => 'SavedSearch_Contact_Summary_Memberships',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Contact_Summary_Memberships',
        'label' => E::ts('Contact Summary Memberships'),
        'api_entity' => 'Membership',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'membership_type_id:label',
            'join_date',
            'start_date',
            'end_date',
            'status_id:label',
            'source',
            'Membership_ContributionRecur_contribution_recur_id_01.auto_renew',
            'IF(owner_membership_id, "(' . E::ts('by relationship') . ')", owner_membership_id) AS IF_owner_membership_id_owner_membership_id',
            'COUNT(DISTINCT Membership_Membership_owner_membership_id_01.id) AS COUNT_Membership_Membership_owner_membership_id_01_id',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [
            'id',
            'Membership_ContributionRecur_contribution_recur_id_01.id',
          ],
          'join' => [
            [
              'ContributionRecur AS Membership_ContributionRecur_contribution_recur_id_01',
              'LEFT',
              [
                'contribution_recur_id',
                '=',
                'Membership_ContributionRecur_contribution_recur_id_01.id',
              ],
            ],
            [
              'Membership AS Membership_Membership_owner_membership_id_01',
              'LEFT',
              [
                'id',
                '=',
                'Membership_Membership_owner_membership_id_01.owner_membership_id',
              ],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'SavedSearch_Contact_Summary_Memberships_SearchDisplay_Contact_Summary_Memberships_Active',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Contact_Summary_Memberships_Active',
        'label' => E::ts('Contact Summary Memberships Active'),
        'saved_search_id.name' => 'Contact_Summary_Memberships',
        'type' => 'table',
        'settings' => [
          'description' => '',
          'sort' => [
            [
              'id',
              'DESC',
            ],
          ],
          'limit' => 0,
          'pager' => FALSE,
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'membership_type_id:label',
              'label' => E::ts('Membership'),
              'sortable' => TRUE,
              'rewrite' => '[membership_type_id:label] [IF_owner_membership_id_owner_membership_id]',
            ],
            [
              'type' => 'field',
              'key' => 'join_date',
              'label' => E::ts('Member Since'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'start_date',
              'label' => E::ts('Membership Start Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'end_date',
              'label' => E::ts('Membership Expiration Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'status_id:label',
              'label' => E::ts('Status'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'source',
              'label' => E::ts('Membership Source'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'Membership_ContributionRecur_contribution_recur_id_01.auto_renew',
              'label' => E::ts('Auto Renew'),
              'sortable' => FALSE,
              'rewrite' => '[none]',
              'icons' => [
                [
                  'icon' => 'fa-exclamation-triangle',
                  'side' => 'left',
                  'if' => [
                    'Membership_ContributionRecur_contribution_recur_id_01.contribution_status_id:name',
                    '=',
                    'Cancelled',
                  ],
                ],
                [
                  'icon' => 'fa-check',
                  'side' => 'left',
                  'if' => [
                    'Membership_ContributionRecur_contribution_recur_id_01.contribution_status_id:name',
                    'IS NOT EMPTY',
                  ],
                ],
              ],
            ],
            [
              'type' => 'field',
              'key' => 'COUNT_Membership_Membership_owner_membership_id_01_id',
              'label' => E::ts('Related'),
              'sortable' => TRUE,
            ],
            [
              'text' => '',
              'style' => 'default',
              'size' => 'btn-xs',
              'icon' => 'fa-bars',
              'links' => [
                [
                  'entity' => 'Membership',
                  'action' => 'view',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('View Membership'),
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'condition' => [],
                ],
                [
                  'path' => '',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('View Primary Member'),
                  'style' => 'default',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Membership',
                  'action' => 'view',
                  'join' => 'owner_membership_id',
                  'target' => 'crm-popup',
                ],
                [
                  'entity' => 'Membership',
                  'action' => 'update',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-pencil',
                  'text' => E::ts('Update Membership'),
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'condition' => [],
                ],
                [
                  'path' => '',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('Renew Membership'),
                  'style' => 'default',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Membership',
                  'action' => 'renew',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'path' => '',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('Renew-Credit Card Membership'),
                  'style' => 'default',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Membership',
                  'action' => 'followup',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'entity' => 'Membership',
                  'action' => 'delete',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-trash',
                  'text' => E::ts('Delete Membership'),
                  'style' => 'danger',
                  'path' => '',
                  'task' => '',
                  'condition' => [],
                ],
                [
                  'path' => '',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('Cancel Auto-renewal'),
                  'style' => 'default',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Membership',
                  'action' => 'cancelrecur',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'path' => '',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('Change Billing Details'),
                  'style' => 'default',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Membership',
                  'action' => 'changebilling',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
              ],
              'type' => 'menu',
              'alignment' => 'text-right',
            ],
          ],
          'actions' => FALSE,
          'classes' => [
            'table',
            'table-striped',
          ],
          'noResultsText' => 'No memberships have been recorded for this contact.',
          'toolbar' => [
            [
              'action' => '',
              'entity' => '',
              'text' => E::ts('Add Membership'),
              'icon' => 'fa-plus-circle',
              'style' => 'primary',
              'target' => 'crm-popup',
              'join' => '',
              'path' => 'civicrm/contact/view/membership?reset=1&action=add&cid=[contact_id]&context=membership',
              'task' => '',
              'condition' => [],
            ],
            [
              'path' => 'civicrm/contact/view/membership?reset=1&action=add&cid=[contact_id]&context=membership&mode=live',
              'icon' => 'fa-credit-card',
              'text' => E::ts('Submit Credit Card Membership'),
              'style' => 'default',
              'condition' => [],
              'task' => '',
              'entity' => '',
              'action' => '',
              'join' => '',
              'target' => 'crm-popup',
            ],
          ],
        ],
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'SavedSearch_Contact_Summary_Memberships_SearchDisplay_Contact_Summary_Memberships_Inactive',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Contact_Summary_Memberships_Inactive',
        'label' => E::ts('Contact Summary Memberships Inactive'),
        'saved_search_id.name' => 'Contact_Summary_Memberships',
        'type' => 'table',
        'settings' => [
          'description' => '',
          'sort' => [
            [
              'id',
              'DESC',
            ],
          ],
          'limit' => 0,
          'pager' => FALSE,
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'membership_type_id:label',
              'label' => E::ts('Membership'),
              'sortable' => TRUE,
              'rewrite' => '[membership_type_id:label] [IF_owner_membership_id_owner_membership_id]',
            ],
            [
              'type' => 'field',
              'key' => 'join_date',
              'label' => E::ts('Member Since'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'start_date',
              'label' => E::ts('Membership Start Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'end_date',
              'label' => E::ts('Membership Expiration Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'status_id:label',
              'label' => E::ts('Status'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'source',
              'label' => E::ts('Membership Source'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'Membership_ContributionRecur_contribution_recur_id_01.auto_renew',
              'label' => E::ts('Auto Renew'),
              'sortable' => FALSE,
              'rewrite' => '[none]',
              'icons' => [
                [
                  'icon' => 'fa-exclamation-triangle',
                  'side' => 'left',
                  'if' => [
                    'Membership_ContributionRecur_contribution_recur_id_01.contribution_status_id:name',
                    '=',
                    'Cancelled',
                  ],
                ],
                [
                  'icon' => 'fa-check',
                  'side' => 'left',
                  'if' => [
                    'Membership_ContributionRecur_contribution_recur_id_01.contribution_status_id:name',
                    'IS NOT EMPTY',
                  ],
                ],
              ],
            ],
            [
              'text' => '',
              'style' => 'default',
              'size' => 'btn-xs',
              'icon' => 'fa-bars',
              'links' => [
                [
                  'entity' => 'Membership',
                  'action' => 'view',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('View Membership'),
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'condition' => [],
                ],
                [
                  'path' => '',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('View Primary Member'),
                  'style' => 'default',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Membership',
                  'action' => 'view',
                  'join' => 'owner_membership_id',
                  'target' => 'crm-popup',
                ],
                [
                  'entity' => 'Membership',
                  'action' => 'update',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-pencil',
                  'text' => E::ts('Update Membership'),
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'condition' => [],
                ],
                [
                  'path' => '',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('Renew Membership'),
                  'style' => 'default',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Membership',
                  'action' => 'renew',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'path' => '',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('Renew-Credit Card Membership'),
                  'style' => 'default',
                  'condition' => [],
                  'task' => '',
                  'entity' => 'Membership',
                  'action' => 'followup',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'entity' => 'Membership',
                  'action' => 'delete',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-trash',
                  'text' => E::ts('Delete Membership'),
                  'style' => 'danger',
                  'path' => '',
                  'task' => '',
                  'condition' => [],
                ],
                [
                  'path' => '',
                  'entity' => 'Membership',
                  'action' => 'cancelrecur',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('Cancel Auto-renewal'),
                  'style' => 'default',
                  'task' => '',
                  'condition' => [],
                ],
                [
                  'path' => '',
                  'entity' => 'Membership',
                  'action' => 'changebilling',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('Change Billing Details'),
                  'style' => 'default',
                  'task' => '',
                  'condition' => [],
                ],
              ],
              'type' => 'menu',
              'alignment' => 'text-right',
            ],
          ],
          'actions' => FALSE,
          'classes' => [
            'table',
            'table-striped',
            'disabled',
          ],
          'noResultsText' => '',
        ],
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
];

<?php

/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Groupmanagement;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Groupmanagement\Controller\GroupController;
use YesWiki\Groupmanagement\Service\GroupManagementService;

class GroupManagementAction extends YesWikiAction
{
    protected $aclService;
    protected $entryManager;
    protected $formManager;
    protected $groupController;
    protected $groupManagementService;
    protected $userManager;
    private $associatedFields ;
    private $options;

    public function formatArguments($args)
    {
        return [
            'title' => empty($args['title']) ? _t('GRPMNGT_ACTION_TITLE') : $args['title'],
            'selectentrylabel' => empty($args['selectentrylabel']) ? _t('GRPMNGT_ACTION_SELECTENTRY_DEFAULT') : $args['selectentrylabel'],
            'noentrylabel' => empty($args['noentrylabel']) ? _t('GRPMNGT_ACTION_NOENTRY_DEFAULT') : $args['noentrylabel'],
        ];
    }

    public function run()
    {
        // get Services
        $this->aclService = $this->getService(AclService::class);
        $this->entryManager = $this->getService(EntryManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->groupController = $this->getService(GroupController::class);
        $this->groupManagementService = $this->getService(GroupManagementService::class);
        $this->userManager = $this->getService(UserManager::class);

        $errorMsg = "";

        $this->options = [];
        $optionsReady = $this->getOptions();
        $isAdmin = $this->wiki->UserIsAdmin();

        if ($isAdmin && filter_input(INPUT_POST, 'view', FILTER_UNSAFE_RAW) === "options") {
            return $this->manageOptions();
        }

        $user = $this->userManager->getLoggedUser();
        if (!$user) {
            $errorMsg = _t('GRPMNGT_ACTION_NO_USER');
        } elseif (!$optionsReady) {
            $errorMsg = _t('GRPMNGT_ACTION_NO_OPTIONS');
        } else {
            $parentsWhereOwner = $this->groupManagementService->getParentsWhereOwner($user, $this->options['parentsForm']);
            $parentsWhereAdmin =
                $isAdmin
                ? $this->groupManagementService->getParentsIds($this->options['parentsForm'])
                : (
                    $this->options['allowedToWrite']
                    ? $this->groupManagementService->getParentsWhereAdminIds(
                        $parentsWhereOwner,
                        $user,
                        $this->options['groupSuffix'],
                        $this->options['parentsForm']
                    )
                    : $parentsWhereOwner
                );

            if (!empty($parentsWhereAdmin)) {
                if (count($parentsWhereAdmin) == 1) {
                    $selectedEntry = $parentsWhereAdmin[0];
                } else {
                    $selectedEntry = filter_input(INPUT_POST, 'selectedEntry', FILTER_UNSAFE_RAW);
                    $selectedEntry = in_array($selectedEntry, [false,null], true) ? $selectedEntry : htmlspecialchars(strip_tags($selectedEntry));
                }
                if (!empty($selectedEntry)) {
                    if (!$this->groupManagementService->isParent($selectedEntry, $this->options['parentsForm'])) {
                        $errorMsg = _t('GRPMNGT_ACTION_WRONG_ENTRYID', ['selectedEntryId' => $selectedEntry]);
                        $selectedEntry = "";
                    } else {
                        if (filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW) === "save" &&
                            $selectedEntry === filter_input(INPUT_POST, 'previousSelectedEntry', FILTER_UNSAFE_RAW)
                        ) {
                            $this->saveGroup($selectedEntry);
                        }
                        $accountsWithEntriesLinkedToSelectedOneWithData = $this->getAccountsWithEntriesLinkedToSelectedEntry($selectedEntry);
                        $accountsWithEntriesLinkedToSelectedOne = array_keys($accountsWithEntriesLinkedToSelectedOneWithData);
                        $accountsInGroupForSelectedEntry = $this->groupController->getAccountsInGroupForSelectedEntry($selectedEntry,$this->options);
                        $dragNDropOptions = array_combine($accountsWithEntriesLinkedToSelectedOne, $accountsWithEntriesLinkedToSelectedOne);
                        foreach ($accountsInGroupForSelectedEntry as $userName) {
                            if (!in_array($userName, $accountsWithEntriesLinkedToSelectedOne)) {
                                $dragNDropOptions[$userName] = $userName;
                            }
                        }
                        if (!in_array($user['name'], $accountsInGroupForSelectedEntry)) {
                            $dragNDropOptions[$user['name']] = $user['name'];
                        }
                        if ($isAdmin) {
                            $currentEntry = $this->groupManagementService->getParent($this->options['parentsForm'], $selectedEntry);
                            if (!empty($currentEntry['owner'])) {
                                $currentEntryOwner = $this->userManager->getOneByName($currentEntry['owner']);
                                if (!empty($currentEntryOwner) && !in_array($currentEntry['owner'], $dragNDropOptions)) {
                                    $dragNDropOptions[$currentEntry['owner']] = $currentEntry['owner'];
                                    $accountsWithEntriesLinkedToSelectedOneWithData[$currentEntry['owner']] = ['isOwner' => true];
                                }
                            }
                        }
                        $accountsWithEntriesLinkedToSelectedOneWithData[$user['name']]['isOwner'] = in_array($selectedEntry, $parentsWhereOwner);
                        $accountsWithEntriesLinkedToSelectedOneWithData[$user['name']]['isAdmin'] = $isAdmin;
                    }
                }
            }
        }

        $entriesWhereAdmin = !empty($parentsWhereAdmin) ? array_map(function ($entryId) {
            $entry = $this->groupManagementService->getParent($this->options['parentsForm'], $entryId);
            return $entry['bf_titre'] ?? $entryId;
        }, array_combine($parentsWhereAdmin, $parentsWhereAdmin)) : [];

        // sort on value
        asort($entriesWhereAdmin, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->render("@groupmanagement/actions/groupmanagement.twig", [
            'isAdmin' => $isAdmin,
            'errorMsg' => $errorMsg,
            'title' => $this->arguments['title'],
            'selectentrylabel' => $this->arguments['selectentrylabel'],
            'noentrylabel' => $this->arguments['noentrylabel'],
            'entriesWhereAdmin' => $entriesWhereAdmin,
            'selectedEntry' => $selectedEntry ?? "",
            'dragNDropOptions' => $dragNDropOptions ?? [],
            'dragNDropOptionsData' => $accountsWithEntriesLinkedToSelectedOneWithData ?? [],
            'selectedOptions' => $accountsInGroupForSelectedEntry ?? [],
            'allowedToWrite' => $this->options['allowedToWrite'] ?? false,
        ]);
    }

    private function manageOptions(): ?string
    {
        $saved = "not-needed";
        if (filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW) === "save") {
            $saved = $this->setOptions() ? "ok" : "error";
            $this->getOptions();
        }
        $forms = $this->formManager->getAll();
        $formsIds = array_values(array_map(function ($form) {
            return $form['bn_id_nature'];
        }, $forms));
        $forms = array_map(function ($form) use ($formsIds) {
            return [
                'id' => $form['bn_id_nature'],
                'label' => $form['bn_label_nature'],
                'fields' => array_filter($form['prepared'], function ($field) use ($formsIds) {
                    return $this->groupController->isRightField($this->options,$field, $formsIds);
                }),
            ];
        }, $forms);
        $childrenForms = array_filter($forms, function ($form) {
            return !empty($form['fields']);
        });
        $parentsFormsIds = [];
        foreach ($childrenForms as $form) {
            foreach ($form['fields'] as $field) {
                $parentFormId = $field->getLinkedObjectName();
                if (!isset($parentsFormsIds[$parentFormId])) {
                    $parentsFormsIds[$parentFormId] = [];
                }
                if (!in_array($form['id'], $parentsFormsIds[$parentFormId])) {
                    $parentsFormsIds[$parentFormId][] = $form['id'];
                }
            }
        }
        $parentsForms = array_filter($forms, function ($form) use ($parentsFormsIds) {
            return in_array($form['id'], array_keys($parentsFormsIds));
        });
        $parentsForms = array_map(function ($form) use ($parentsFormsIds) {
            return $form + ['availableChildren' => $parentsFormsIds[$form['id']] ];
        }, $parentsForms);
        return $this->render("@groupmanagement/actions/groupmanagement-options.twig", [
            'options' => $this->options,
            'saved' => $saved,
            'childrenForms' => $childrenForms,
            'parentsForms' => $parentsForms,
            'title' => $this->arguments['title'],
        ]);
    }

    private function getOptions(): bool
    {
        $tag = $this->wiki->getPageTag();
        if (empty($tag)) {
            return false;
        }
        $this->options = [];
        $this->associatedFields = [];
        return $this->groupController->getOptions($tag,$this->options,$this->associatedFields);
    }

    private function setOptions(): bool
    {
        $tag = $this->wiki->getPageTag();
        if (empty($tag)) {
            return false;
        }
        $options = $_POST['options'] ?? null;
        if (empty($options) || !is_array($options)) {
            return false;
        }
        return $this->groupController->setOptions($options,$tag);
    }

    private function getAccountsWithEntriesLinkedToSelectedEntry(string $selectedEntry): array
    {
        $entries = [];
        foreach ($this->associatedFields as $field) {
            $foundEntries = $this->entryManager->search(
                [
                    'formsIds' => [$this->options['childrenForm']],
                    'queries' => [
                        $field->getPropertyName() => $selectedEntry
                    ],
                ],
                true,
                true
            );
            foreach ($foundEntries as $foundEntry) {
                if (!isset($entries[$foundEntry['id_fiche']])) {
                    $entries[$foundEntry['id_fiche']] = $foundEntry;
                }
            }
        }
        $accounts = [];

        foreach ($entries as $entry) {
            if (isset($entry['owner'])) {
                $owner = trim($entry['owner']);
                if (!empty($owner)) {
                    $user = $this->userManager->getOneByName($owner);
                    if (!empty($user) && !isset($accounts[$owner])) {
                        $accounts[$owner] = [
                            'id' => $entry['id_fiche'],
                            'title' => $entry['bf_titre'] ?? $entry['id_fiche'],
                        ];
                    }
                }
            }
        }
        return $accounts;
    }

    private function saveGroup(string $selectedEntry)
    {
        $newData = $_POST['membersofgroup'] ?? null;
        if (!is_array($newData)) {
            flash(_t('GRPMNGT_ACTION_VALUES_NOT_SAVED'), "danger");
        } else {
            $this->groupController->saveGroup($selectedEntry,$newData,$this->options);

            flash(_t('GRPMNGT_ACTION_VALUES_SAVED'), "success");
        }
    }
}

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

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Field\CheckboxEntryField;
use YesWiki\Bazar\Field\RadioEntryField;
use YesWiki\Bazar\Field\SelectEntryField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;

class GroupManagementAction extends YesWikiAction
{
    public const TRIPLE_PROPERTY = "https://yeswiki.net/vocabulary/groupmanagementoptions";

    protected $aclService;
    protected $entryManager;
    protected $formManager;
    protected $tripleStore;
    protected $userManager;
    private $groupsWithSuffix ;
    private $associatedFields ;
    private $parents;
    private $authorizedParents;
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
        $this->tripleStore = $this->getService(TripleStore::class);
        $this->userManager = $this->getService(UserManager::class);

        $errorMsg = "";

        $this->options = [];
        $optionsReady = $this->getOptions();
        $isAdmin = $this->wiki->UserIsAdmin();
        $this->parents = [];
        $this->authorizedParents = null;

        if ($isAdmin && filter_input(INPUT_POST, 'view', FILTER_UNSAFE_RAW) === "options") {
            return $this->manageOptions();
        }

        $user = $this->userManager->getLoggedUser();
        if (!$user) {
            $errorMsg = _t('GRPMNGT_ACTION_NO_USER');
        } elseif (!$optionsReady) {
            $errorMsg = _t('GRPMNGT_ACTION_NO_OPTIONS');
        } else {
            $this->getGroupsWithSuffix();
            $parentsWhereOwner = $this->getParentsWhereOwner($user);
            $parentsWhereAdmin =
                $isAdmin
                ? $this->getParentsIds()
                : (
                    $this->options['allowedToWrite']
                    ? $this->getParentsWhereAdmin($parentsWhereOwner, $user)
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
                    if (!$this->isParent($selectedEntry)) {
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
                        $accountsInGroupForSelectedEntry = $this->getAccountsInGroupForSelectedEntry($selectedEntry);
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
                            $currentEntry = $this->parents[$selectedEntry] ?? [];
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

        return $this->render("@groupmanagement/actions/groupmanagement.twig", [
            'isAdmin' => $isAdmin,
            'errorMsg' => $errorMsg,
            'title' => $this->arguments['title'],
            'selectentrylabel' => $this->arguments['selectentrylabel'],
            'noentrylabel' => $this->arguments['noentrylabel'],
            'entriesWhereAdmin' => !empty($parentsWhereAdmin) ? array_map(function ($entryId) {
                $entry = $this->parents[$entryId] ?? [];
                return $entry['bf_titre'] ?? $entryId;
            }, array_combine($parentsWhereAdmin, $parentsWhereAdmin)) : [],
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
                    return $this->isRightField($field, $formsIds);
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
        $options = $this->tripleStore->getOne($tag, self::TRIPLE_PROPERTY, "", "");
        if (empty($options)) {
            return false;
        }
        $options = json_decode($options, true);
        if (!is_array($options)) {
            return false;
        }
        $this->options = $options;

        $this->associatedFields = $this->findAssociatedFields($this->options['fieldNames'] ?? "");

        return !empty($this->options['parentsForm']) &&
            !empty($this->options['childrenForm']) &&
            !empty($this->options['groupSuffix']) &&
            strlen($this->options['groupSuffix']) > 3 &&
            isset($this->options['allowedToWrite']) &&
            !empty($this->associatedFields) ;
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
        $options['allowedToWrite'] = isset($options['allowedToWrite']) && (
            $options['allowedToWrite'] === true ||
            (
                is_array($options['allowedToWrite']) && isset($options['allowedToWrite']['allowedToWrite'])
                && in_array($options['allowedToWrite']['allowedToWrite'], [1,"1",true,"true"])
            )
        );
        $options['fieldNames'] = empty($options['fieldNames'])
            ? ""
            : (
                is_string($options['fieldNames'])
                ? $options['fieldNames']
                : (
                    is_array($options['fieldNames'])
                    ? implode(',', array_keys(array_filter($options['fieldNames'], function ($value, $key) {
                        return $key != "fromForm" && in_array($value, [1,"1",true,"true"], true);
                    }, ARRAY_FILTER_USE_BOTH)))
                    : ""
                )
            );
        $options = filter_var_array($options, [
            'parentsForm' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'childrenForm' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'fieldNames' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'groupSuffix' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            'allowedToWrite' => FILTER_VALIDATE_BOOL,
            'mainGroup' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
        ]);
        $fields = $this->findAssociatedFields(!empty($options['fieldNames']) ? $options['fieldNames'] : "", $options['childrenForm'], $options['parentsForm']);
        $options['fieldNames'] = empty($fields) ? "" : implode(',', array_map(function ($field) {
            return $field->getPropertyName();
        }, $fields));

        $previousItems = $this->tripleStore->getAll($tag, self::TRIPLE_PROPERTY, "", "");
        if (!empty($previousItems)) {
            for ($i=1; $i < count($previousItems); $i++) {
                $this->tripleStore->delete($previousItems[$i]['resource'], self::TRIPLE_PROPERTY, $previousItems[$i]['value'], "", "");
            }
            return in_array($this->tripleStore->update($previousItems[0]['resource'], self::TRIPLE_PROPERTY, $previousItems[0]['value'], json_encode($options), "", ""), [0,3]);
        } else {
            return $this->tripleStore->create($tag, self::TRIPLE_PROPERTY, json_encode($options), "", "") == 0;
        }
    }

    private function getGroupsWithSuffix(): array
    {
        $suffix = $this->options['groupSuffix'];
        $res = $this->tripleStore->getMatching(
            GROUP_PREFIX . "%$suffix",
            WIKINI_VOC_ACLS_URI
        );
        $prefix_len = strlen(GROUP_PREFIX);
        $groups = [];
        foreach ($res as $line) {
            $groups[] = substr($line['resource'], $prefix_len);
        }
        $this->groupsWithSuffix = array_filter($groups, function ($group) use ($suffix) {
            return substr($group, -strlen($suffix)) == $suffix;
        });
        return $this->groupsWithSuffix;
    }

    private function getParentsWhereOwner(array $user): array
    {
        $entries = $this->entryManager->search([
            'formsIds' => [$this->options['parentsForm']],
            'user' => $user['name'],
        ]+$this->getAuthorizedParentsOptions(), true, true);
        return empty($entries) ? [] : array_values(array_map(function ($entry) {
            return $entry['id_fiche'];
        }, $entries));
    }

    private function getParentsWhereAdmin(array $parentsWhereOwner, array $user): array
    {
        $parentsWhereAdmin = $parentsWhereOwner;

        $groupsWherePresent = array_filter($this->groupsWithSuffix, function ($groupName) use ($user) {
            return $this->wiki->UserIsInGroup($groupName, $user['name'], false);
        });

        $associatedEntries = array_filter(array_map(function ($groupName) {
            return substr($groupName, 0, -strlen($this->options['groupSuffix']));
        }, $groupsWherePresent), function ($entryId) {
            return $this->isParent($entryId);
        });
        foreach ($associatedEntries as $entryId) {
            if (!in_array($entryId, $parentsWhereAdmin)) {
                $parentsWhereAdmin[] = $entryId;
            }
        }
        return $parentsWhereAdmin;
    }

    private function isParent(string $tag): bool
    {
        if (!empty($this->parents[$tag])) {
            return true;
        }
        if (!$this->entryManager->isEntry($tag)) {
            return false;
        }
        $entry = $this->entryManager->getOne($tag);
        if (empty($entry) || empty($entry['id_typeannonce'])) {
            return false;
        }
        if (strval($entry['id_typeannonce']) != strval($this->options['parentsForm'])) {
            return false;
        }
        $this->parents[$tag] = $entry;
        return true;
    }

    private function getAuthorizedParents(): array
    {
        if (is_null($this->authorizedParents)) {
            $this->authorizedParents = [];
            $config = $this->params->get('groupmanagement');
            if (!empty($config['authorizedParents']) && is_string($config['authorizedParents'])) {
                $this->authorizedParents = $config['authorizedParents'] == "*"
                    ? ['*']
                    : array_filter(array_map('trim', explode(',', $config['authorizedParents'])));
            }
        }
        return $this->authorizedParents;
    }

    private function getAuthorizedParentsOptions(): array
    {
        $authorizedParents = $this->getAuthorizedParents();
        $otherOptions = [];
        if (!empty($authorizedParents) && $authorizedParents[0] != "*") {
            $otherOptions['queries'] = [
                'id_fiche' => implode(',', $authorizedParents)
            ];
        }
        return $otherOptions;
    }

    private function getAllParents(): array
    {
        $entries = $this->entryManager->search([
            'formsIds' => [$this->options['parentsForm']],
        ]+$this->getAuthorizedParentsOptions(), false, false);
        return empty($entries) ? [] : $entries;
    }

    private function getParentsIds(): array
    {
        $this->parents = [];
        foreach ($this->getAllParents() as $entry) {
            if (!empty($entry['id_fiche'])) {
                $this->parents[$entry['id_fiche']] = $entry;
            }
        }
        return array_keys($this->parents);
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

    private function getAccountsInGroupForSelectedEntry(string $selectedEntry): array
    {
        $groupAcl = $this->wiki->GetGroupACL("{$selectedEntry}{$this->options['groupSuffix']}");
        if (empty($groupAcl)) {
            return [];
        }

        $groupmembers = array_filter(array_map('trim', explode("\n", $groupAcl)), function ($line) {
            switch ($line) {
                case '*':
                case '!*':
                case '+':
                case '!+':
                case '%':
                case '!%':
                    return false;
                default:
                    if (in_array(substr($line, 0, 1), ["@","#",'!'])) {
                        return false;
                    }
                    return !empty($line) && !empty($this->userManager->getOneByName($line));
            }
        });

        return array_values($groupmembers);
    }

    private function saveGroup(string $selectedEntry)
    {
        $newData = $_POST['membersofgroup'] ?? null;
        if (!is_array($newData)) {
            flash(_t('GRPMNGT_ACTION_VALUES_NOT_SAVED'), "danger");
        } else {
            $groupName = "{$selectedEntry}{$this->options['groupSuffix']}";
            $this->updateGroupAcl($selectedEntry, $groupName, $newData);
            $this->addGroupToMainGroup($groupName);
            $this->updateWriteAcl($selectedEntry, $groupName);
            $this->updateReadAcl($selectedEntry, $groupName);

            flash(_t('GRPMNGT_ACTION_VALUES_SAVED'), "success");
        }
    }

    private function updateGroupAcl(string $selectedEntry, string $groupName, array $newData)
    {
        $selectedUsers = [];
        foreach ($newData as $key => $value) {
            if ($key != 'fromForm' && in_array($value, [1,"1",true,"true"])) {
                $userAccount = $this->userManager->getOneByName($key);
                if (!empty($userAccount)) {
                    $selectedUsers[] = $key;
                }
            }
        }
        $groupAcl = $this->wiki->GetGroupACL($groupName);
        if (empty($groupAcl)) {
            $groupAcl = "";
        }
        $accountsCurrentlyInGroup = $this->getAccountsInGroupForSelectedEntry($selectedEntry);
        foreach ($selectedUsers as $userName) {
            if (!in_array($userName, $accountsCurrentlyInGroup)) {
                if (!empty($groupAcl) && substr($groupAcl, -strlen("\n")) != "\n") {
                    $groupAcl .= "\n";
                }
                $groupAcl .= "$userName\n";
            }
        }
        foreach ($accountsCurrentlyInGroup as $userName) {
            if (!in_array($userName, $selectedUsers)) {
                $groupAcl = preg_replace("/(?<=^|\\r|\\n)$userName(?:\\r|\\n)+/", "", $groupAcl);
            }
        }
        if (empty(trim($groupAcl))) {
            $groupAcl = "@admins\n";
        } else {
            $groupAcl = str_replace(["\r\r","\n\n"], ["\r","\n"], $groupAcl);
        }
        $this->wiki->SetGroupACL($groupName, $groupAcl);
    }

    private function addGroupToMainGroup(string $groupName)
    {
        if (!empty($this->options['mainGroup'])) {
            $mainGroupAcl = $this->wiki->GetGroupACL($this->options['mainGroup']);
            if (empty($mainGroupAcl)) {
                $mainGroupAcl = "";
            }
            $isAlreadyDefined = preg_match("/^@$groupName$/m", $mainGroupAcl);
            if (!$isAlreadyDefined) {
                $mainGroupAcl .= ((substr($mainGroupAcl, -strlen("\n")) != "\n") ? "\n" : "") . "@$groupName\n";
                $this->wiki->SetGroupACL($this->options['mainGroup'], $mainGroupAcl);
            }
        }
    }

    private function updateWriteAcl(string $selectedEntry, string $groupName)
    {
        $currentWrite = $this->aclService->load($selectedEntry, 'write', false);
        $currentWrite = empty($currentWrite['list']) ? "" : $currentWrite['list'];
        $isAlreadyDefined = preg_match("/^@$groupName$/m", $currentWrite);
        if ($this->options['allowedToWrite'] && !$isAlreadyDefined) {
            $newCurrentWrite = $currentWrite .((substr($currentWrite, -strlen("\n")) != "\n") ? "\n" : "") . "@$groupName\n";
        } elseif (!$this->options['allowedToWrite'] && $isAlreadyDefined) {
            $newCurrentWrite = (trim($currentWrite) == "@$groupName")
                ? "@admins\n"
                : preg_replace("/(?<=^|\\r|\\n)@$groupName(?:\\r|\\n)+/", "", $currentWrite);
        }
        if (isset($newCurrentWrite)) {
            $this->aclService->save($selectedEntry, 'write', $newCurrentWrite);
        }
    }

    private function updateReadAcl(string $selectedEntry, string $groupName)
    {
        $currentRead = $this->aclService->load($selectedEntry, 'read', false);
        $currentRead = empty($currentRead['list']) ? "" : $currentRead['list'];
        $isAlreadyDefined = preg_match("/^@$groupName$/m", $currentRead);
        if (!$isAlreadyDefined) {
            $currentRead .= ((substr($currentRead, -strlen("\n")) != "\n") ? "\n" : "") . "@$groupName\n";
            $this->aclService->save($selectedEntry, 'read', $currentRead);
        }
    }

    private function findAssociatedFields(string $fieldNames = "", string $newChildformId = "", string $newParentFormId = ""): array
    {
        if (empty($newChildformId) && empty($this->options['childrenForm'])) {
            return [];
        }
        if (empty($newChildformId)) {
            $newChildformId = $this->options['childrenForm'];
        }
        $fields = [];
        $newparents = empty($newParentFormId) ? [] : [$newParentFormId];
        if (!empty($fieldNames)) {
            foreach (explode(',', $fieldNames) as $newFieldName) {
                $newField = $this->formManager->findFieldFromNameOrPropertyName($newFieldName, $newChildformId);
                if (!empty($newField) && $this->isRightField($newField, $newparents) && empty(array_filter($fields, function ($field) use ($newField) {
                    return $field->getPropertyName() == $newField->getPropertyName();
                }))) {
                    $fields[] = $newField;
                }
            }
        }
        if (empty($fields)) {
            $form = $this->formManager->getOne($newChildformId);
            if (empty($form)) {
                return [];
            } else {
                foreach ($form['prepared'] as $field) {
                    if ($this->isRightField($field, $newparents)) {
                        $fields[] = $field;
                    }
                }
            }
        }

        return $fields;
    }

    private function isRightField(BazarField $field, array $rightIds = []): bool
    {
        return ($field instanceof SelectEntryField || $field instanceof RadioEntryField || $field instanceof CheckboxEntryField) &&
            (empty($rightIds) ? $field->getLinkedObjectName() == $this->options['parentsForm'] : in_array($field->getLinkedObjectName(), $rightIds));
    }
}

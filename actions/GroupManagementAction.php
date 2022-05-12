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
    private $allEntries ;
    private $allEntriesIds ;
    private $associatedField ;
    private $options;
    private $allUsers;

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
        $this->allUsers = null;
        $optionsReady = $this->getOptions();
        $isAdmin = $this->wiki->UserIsAdmin();

        if ($isAdmin && filter_input(INPUT_POST, 'view', FILTER_SANITIZE_STRING) == "options") {
            return $this->manageOptions();
        }
        
        $user = $this->userManager->getLoggedUser();
        if (!$user) {
            $errorMsg = _t('GRPMNGT_ACTION_NO_USER');
        } elseif (!$optionsReady) {
            $errorMsg = _t('GRPMNGT_ACTION_NO_OPTIONS');
        } else {
            $this->getGroupsWithSuffix();
            $this->allEntries = $this->getAllEntries();
            $this->allEntriesIds = $this->getEntriesIds($this->allEntries);
            $entriesWhereAdmin = $isAdmin ? $this->allEntriesIds : $this->getEntriesWhereOwner($user);
            if (!$isAdmin && $this->options['allowedToWrite']) {
                $this->appendEntriesWhereAllowedToWrite($entriesWhereAdmin, $user);
            }
            if (!empty($entriesWhereAdmin)) {
                $selectedEntry = filter_input(INPUT_POST, 'selectedEntry', FILTER_SANITIZE_STRING);
                if (count($entriesWhereAdmin) == 1) {
                    $selectedEntry = $entriesWhereAdmin[0];
                }
                if (!empty($selectedEntry)) {
                    if (!in_array($selectedEntry, $this->allEntriesIds)) {
                        $errorMsg = _t('GRPMNGT_ACTION_WRONG_ENTRYID', ['selectedEntryId' => $selectedEntry]);
                        $selectedEntry = "";
                    } else {
                        if (filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING) == "save" &&
                            $selectedEntry == filter_input(INPUT_POST, 'previousSelectedEntry', FILTER_SANITIZE_STRING)
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
            'entriesWhereAdmin' => !empty($entriesWhereAdmin) ? array_map(function ($entryId) {
                $entry = $this->allEntries[$entryId] ?? [];
                return $entry['bf_titre'] ?? $entryId;
            }, array_combine($entriesWhereAdmin, $entriesWhereAdmin)) : [],
            'selectedEntry' => $selectedEntry ?? "",
            'dragNDropOptions' => $dragNDropOptions ?? [],
            'dragNDropOptionsData' => $accountsWithEntriesLinkedToSelectedOneWithData ?? [],
            'selectedOptions' => $accountsInGroupForSelectedEntry ?? [],
        ]);
    }

    private function manageOptions(): ?string
    {
        $saved = "not-needed";
        if (filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING) == "save") {
            $saved = $this->setOptions() ? "ok" : "error";
            $this->getOptions();
        }
        return $this->render("@groupmanagement/actions/groupmanagement-options.twig", [
            'options' => $this->options,
            'saved' => $saved,
            'forms' => array_map(function ($form) {
                return "{$form['bn_label_nature']} ({$form['bn_id_nature']})";
            }, $this->formManager->getAll()),
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

        $this->associatedField = $this->findAssociatedField($this->options['fieldName'] ?? "");

        return !empty($this->options['parentsForm']) &&
            !empty($this->options['childrenForm']) &&
            !empty($this->options['groupSuffix']) &&
            strlen($this->options['groupSuffix']) > 3 &&
            isset($this->options['allowedToWrite']) &&
            !empty($this->associatedField) ;
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
        $options = filter_var_array($options, [
            'parentsForm' => FILTER_SANITIZE_STRING,
            'childrenForm' => FILTER_SANITIZE_STRING,
            'fieldName' => FILTER_SANITIZE_STRING,
            'groupSuffix' => FILTER_SANITIZE_STRING,
            'allowedToWrite' => FILTER_VALIDATE_BOOL,
            'mainGroup' => FILTER_SANITIZE_STRING,
        ]);
        $field = $this->findAssociatedField(!empty($options['fieldName']) ? $options['fieldName'] : "");
        $options['fieldName'] = empty($field) ? "" : (!empty($field->getName()) ? $field->getName() : $field->getPropertyName());

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
        $groups = $this->wiki->GetGroupsList();
        $suffix = $this->options['groupSuffix'];
        $this->groupsWithSuffix = array_filter($groups, function ($group) use ($suffix) {
            return substr($group, -strlen($suffix)) == $suffix;
        });
        return $this->groupsWithSuffix;
    }

    private function getEntriesWhereOwner(array $user): array
    {
        $entries = $this->entryManager->search([
            'formsIds' => [$this->options['parentsForm']],
            'user' => $user['name'],
        ], true, true);
        return empty($entries) ? [] : array_values(array_map(function ($entry) {
            return $entry['id_fiche'];
        }, $entries));
    }

    private function getAllEntries(): array
    {
        $entries = $this->entryManager->search([
            'formsIds' => [$this->options['parentsForm']],
        ], false, false);
        return empty($entries) ? [] : $entries;
    }

    private function getEntriesIds(array $entries): array
    {
        return empty($entries) ? [] : array_values(array_map(function ($entry) {
            return $entry['id_fiche'];
        }, $entries));
    }

    private function appendEntriesWhereAllowedToWrite(&$entries, $user)
    {
        $groupsWherePresent = array_filter($this->groupsWithSuffix, function ($groupName) use ($user) {
            return $this->wiki->UserIsInGroup($groupName, $user['name'], false);
        });
        $associatedEntries = array_filter(array_map(function ($groupName) {
            return substr($groupName, 0, -strlen($this->options['groupSuffix']));
        }, $groupsWherePresent), function ($entryId) {
            return in_array($entryId, $this->allEntriesIds);
        });
        foreach ($associatedEntries as $entryId) {
            if (!in_array($entryId, $entries)) {
                $entries[] = $entryId;
            }
        }
    }

    private function getAccountsWithEntriesLinkedToSelectedEntry(string $selectedEntry): array
    {
        $entries = $this->entryManager->search(
            [
                'formsIds' => [$this->options['childrenForm']],
                'queries' => [
                    $this->associatedField->getPropertyName() => $selectedEntry
                ],
            ],
            true,
            true
        );
        $accounts = [];
        
        $users = $this->getAllUsers();

        foreach ($entries as $entry) {
            if (isset($entry['owner'])) {
                $owner = trim($entry['owner']);
                if (!empty($owner) && in_array($owner, $users) && !isset($accounts[$owner])) {
                    $accounts[$owner] = [
                        'id' => $entry['id_fiche'],
                        'title' => $entry['bf_titre'] ?? $entry['id_fiche'],
                    ];
                }
            }
        }
        return $accounts;
    }

    private function getAccountsInGroupForSelectedEntry(string $selectedEntry): array
    {
        $groupAcl = $this->wiki->GetGroupACL("{$selectedEntry}{$this->options['groupSuffix']}");
        $users = $this->getAllUsers();
        
        $groupmembers = array_filter(array_map('trim', explode("\n", $groupAcl)), function ($line) use ($users) {
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
                    return in_array($line, $users);
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
            $this->addGroupToMainGroup( $groupName);
            $this->updateWriteAcl($selectedEntry, $groupName);
            $this->updateReadAcl($selectedEntry, $groupName);
            
            flash(_t('GRPMNGT_ACTION_VALUES_SAVED'), "success");
        }
    }

    private function updateGroupAcl(string $selectedEntry, string $groupName, array $newData)
    {
        $selectedUsers = [];
        $users = $this->getAllUsers();
        foreach ($newData as $key => $value) {
            if ($key != 'fromForm' && in_array($key, $users) && in_array($value, [1,"1",true,"true"])) {
                $selectedUsers[] = $key;
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
        if (!empty($this->options['mainGroup'])){
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

    private function findAssociatedField(string $fieldName = ""): ?EnumField
    {
        if (empty($this->options['childrenForm'])) {
            return null;
        }
        if (!empty($fieldName)) {
            $field = $this->formManager->findFieldFromNameOrPropertyName($fieldName, $this->options['childrenForm']);
        }
        if (empty($field) || !$this->isRightField($field)) {
            $form = $this->formManager->getOne($this->options['childrenForm']);
            if (empty($form)) {
                return null;
            } else {
                foreach ($form['prepared'] as $field) {
                    if ($this->isRightField($field)) {
                        return $field;
                    }
                }
                return null;
            }
        }

        return $field;
    }

    private function isRightField(BazarField $field): bool
    {
        return ($field instanceof SelectEntryField || $field instanceof RadioEntryField || $field instanceof CheckboxEntryField) &&
            $field->getLinkedObjectName() == $this->options['parentsForm'];
    }

    private function getAllUsers(): array
    {
        if (is_null($this->allUsers)) {
            $this->allUsers = array_map(function ($user) {
                return $user['name'];
            }, $this->userManager->getAll(['name']));
        }
        return $this->allUsers;
    }
}

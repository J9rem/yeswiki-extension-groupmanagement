<?php

namespace YesWiki\Groupmanagement;

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;

class GroupManagementAction extends YesWikiAction
{
    public const TRIPLE_PROPERTY = "https://yeswiki.net/vocabulary/groupmanagementoptions";

    protected $entryManager;
    protected $formManager;
    protected $tripleStore;
    protected $userManager;
    private $groupsWithSuffix ;
    private $allEntries ;
    private $allEntriesIds ;
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
        $this->entryManager = $this->getService(EntryManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->tripleStore = $this->getService(TripleStore::class);
        $this->userManager = $this->getService(UserManager::class);
        
        $errorMsg = "";

        $this->options = [];
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
            $selectedEntry = filter_input(INPUT_POST, 'selectedEntry', FILTER_SANITIZE_STRING);
            if (!empty($selectedEntry)) {
                if (!in_array($selectedEntry, $this->allEntriesIds)) {
                    $errorMsg = _t('GRPMNGT_ACTION_WRONG_ENTRYID', ['selectedEntryId' => $selectedEntry]);
                    $selectedEntry = "";
                } else {
                    $accountsInGroupForSelectedEntry = $this->getAccountsInGroupForSelectedEntry($selectedEntry);
                    // TODO extract list of members linked to this entry (owners of the linked entries)
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

        return !empty($this->options['parentsForm']) &&
            !empty($this->options['childrenForm']) &&
            !empty($this->options['groupSuffix']) &&
            strlen($this->options['groupSuffix']) > 3 &&
            isset($this->options['allowedToWrite']);
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
        ]);
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

    private function getAccountsInGroupForSelectedEntry(string $selectedEntry): array
    {
        $groupAcl = $this->wiki->GetGroupACL("{$selectedEntry}{$this->options['groupSuffix']}");
        $users = array_map(function ($user) {
            return $user['name'];
        }, $this->userManager->getAll(['name']));
        
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

        $groupmembers = array_map(function ($userName) {
            $entries = $this->entryManager->search(
                [
                    'formsIds' => [$this->options['childrenForm']],
                    'user' => $userName,
                    // TODO select query based on fieldName
                ],
                true,
                true
            );
            return [
                'name' => $userName,
                'hasEntry' => !empty($entries)
            ];
        }, array_values($groupmembers));

        return $groupmembers;
    }
}

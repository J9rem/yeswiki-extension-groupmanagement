<?php

/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Groupmanagement\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\CheckboxEntryField;
use YesWiki\Bazar\Field\RadioEntryField;
use YesWiki\Bazar\Field\SelectEntryField;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Entity\User;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;
use YesWiki\Zfuture43\Entity\User as Zfuture43User;

class GroupManagementService
{
    protected $aclService;
    protected $entryManager;
    protected $formManager;
    protected $pageManager;
    protected $params;
    protected $tripleStore;
    protected $userManager;
    protected $wiki;
    private $authorizedParents;
    private $groupsWithSuffix ;
    private $parents;

    public function __construct(
        AclService $aclService,
        EntryManager $entryManager,
        FormManager $formManager,
        PageManager $pageManager,
        ParameterBagInterface $params,
        UserManager $userManager,
        TripleStore $tripleStore,
        Wiki $wiki
    ) {
        $this->authorizedParents = null;
        $this->aclService = $aclService;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->groupsWithSuffix = null;
        $this->pageManager = $pageManager;
        $this->params = $params;
        $this->parents = [];
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
    }

    public function getParentsWhereOwner($user, $formId): array
    {
        if (empty($formId) ||
            !is_scalar($formId) ||
            (strval($formId) != strval(intval($formId))) ||
            empty($user) ||
            !(is_array($user) || $user instanceof User || $user instanceof Zfuture43User)||
            empty($user['name'])) {
            return [];
        }
        $entries = $this->entryManager->search([
            'formsIds' => [$formId],
            'user' => $user['name'],
        ]+$this->getAuthorizedParentsOptions(), true, true);
        if (empty($entries)) {
            return [];
        } else {
            if (!isset($this->parents[$formId])) {
                $this->parents[$formId] = [];
            }
            $result = [];
            foreach ($entries as $entry) {
                $entryId = $entry['id_fiche'] ?? "";
                if (!empty($entryId)) {
                    $result[] = $entryId;
                    $this->parents[$formId][$entryId] = $entry;
                }
            }
            return $result;
        }
    }

    public function getParentsWhereAdminIds(
        array $parentsWhereOwner,
        array $user,
        string $groupSuffix,
        string $parentsForm
    ): array {
        if (empty($groupSuffix) ||
            empty($parentsForm) ||
            empty($user) ||
            !(is_array($user) || $user instanceof User || $user instanceof Zfuture43User) ||
            empty($user['name']) ||
            count(array_filter($parentsWhereOwner, function ($item) {
                return !is_string($item);
            }))>0) {
            return [];
        }

        $parentsWhereAdmin = $parentsWhereOwner;

        $groupsWithSuffix = $this->getGroupsWithSuffix($groupSuffix);

        $groupsWherePresent = array_filter($groupsWithSuffix, function ($groupName) use ($user) {
            return $this->wiki->UserIsInGroup($groupName, $user['name'], false);
        });

        $associatedEntries = array_filter(array_map(function ($groupName) use ($groupSuffix) {
            return substr($groupName, 0, -strlen($groupSuffix));
        }, $groupsWherePresent), function ($entryId) use ($parentsForm) {
            return $this->isParent($entryId, $parentsForm);
        });
        foreach ($associatedEntries as $entryId) {
            if (!in_array($entryId, $parentsWhereAdmin)) {
                $parentsWhereAdmin[] = $entryId;
            }
        }
        return $parentsWhereAdmin;
    }

    public function getParentsIds(string $parentsForm): array
    {
        if (empty($parentsForm) || strval($parentsForm) != strval(intval($parentsForm)) || intval($parentsForm) < 0) {
            return [];
        }
        $this->parents[$parentsForm] = [];
        foreach ($this->getAllParents($parentsForm) as $entry) {
            if (!empty($entry['id_fiche'])) {
                $this->parents[$parentsForm][$entry['id_fiche']] = $entry;
            }
        }
        return array_keys($this->parents[$parentsForm]);
    }

    public function getParent(string $parentsForm, string $tag): ?array
    {
        return $this->isParent($tag, $parentsForm)
            ? $this->parents[$parentsForm][$tag]
            : [] ;
    }

    private function getAuthorizedParents(): array
    {
        if (is_null($this->authorizedParents)) {
            $this->authorizedParents = [];
            $config = $this->params->get('groupmanagement');
            if (!empty($config['authorizedParents']) && is_string($config['authorizedParents'])) {
                $this->authorizedParents = ($config['authorizedParents'] == "*")
                    ? ['*']
                    : array_filter(array_map('trim', explode(',', $config['authorizedParents'])));
            }
        }
        return $this->authorizedParents;
    }

    private function getAllParents(string $parentsForm): array
    {
        $entries = $this->entryManager->search([
            'formsIds' => [$parentsForm],
        ]+$this->getAuthorizedParentsOptions(), false, false);
        return empty($entries) ? [] : $entries;
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

    public function isParent(string $tag, string $parentsForm): bool
    {
        if (empty($tag) || empty($parentsForm) || strval($parentsForm) != strval(intval($parentsForm)) || intval($parentsForm) < 0) {
            return false;
        }
        if (!isset($this->parents[$parentsForm])) {
            $this->parents[$parentsForm] = [];
        }
        if (!empty($this->parents[$parentsForm][$tag])) {
            return true;
        } elseif (array_key_exists($tag, $this->parents[$parentsForm]) && is_null($this->parents[$parentsForm][$tag])) {
            return false;
        }
        if (!$this->entryManager->isEntry($tag)) {
            $this->parents[$parentsForm][$tag] = null;
            return false;
        }
        $entry = $this->entryManager->getOne($tag);
        if (empty($entry) || empty($entry['id_typeannonce']) || strval($entry['id_typeannonce']) != strval($parentsForm)) {
            $this->parents[$parentsForm][$tag] = null;
            return false;
        }
        $this->parents[$parentsForm][$tag] = $entry;
        return true;
    }

    private function getGroupsWithSuffix(string $suffix): array
    {
        if (empty($suffix)) {
            return [];
        }
        if (is_null($this->groupsWithSuffix)) {
            $this->groupsWithSuffix = [];
        }
        if (!isset($this->groupsWithSuffix[$suffix])) {
            $res = $this->tripleStore->getMatching(
                GROUP_PREFIX . "%$suffix",
                WIKINI_VOC_ACLS_URI
            );
            $prefix_len = strlen(GROUP_PREFIX);
            $groups = [];
            foreach ($res as $line) {
                $groups[] = substr($line['resource'], $prefix_len);
            }
            $this->groupsWithSuffix[$suffix] = array_filter($groups, function ($group) use ($suffix) {
                return substr($group, -strlen($suffix)) == $suffix;
            });
        }
        return $this->groupsWithSuffix[$suffix];
    }

    /**
     * extract EnumFields from from
     * @param array $form
     * @param string $parentFormId , if empty, return all corresponding fields
     * @return EnumField[] entry CheckboxEntryField or RadioEntryField or SelectEntryField
     */
    protected function extractEnumFieldsFromForm(array $form, string $parentFormId = ''): array
    {
        $enumEntryFields = [];
        if (!empty($form['prepared']) && is_array($form['prepared'])) {
            foreach ($form['prepared'] as $field) {
                if (
                    (
                        $field instanceof CheckboxEntryField ||
                        $field instanceof RadioEntryField ||
                        $field instanceof SelectEntryField
                    ) && (
                        empty($parentFormId) ||
                        $field->getLinkedObjectName() == $parentFormId
                    )
                ) {
                    $enumEntryFields[] = $field;
                }
            }
        }
        return $enumEntryFields;
    }

    /**
     * @param array $entry
     * @param array $fields ! should be CheckboxEntryField or RadioEntryField or SelectEntryField
     * @param string $suffix not empty
     * @param string $loggedUserName not empty
     * @param bool $extractAllIds
     * @return array $parentsIds
     */
    protected function isAdminOfParent(
        array $entry,
        array $fields,
        string $suffix,
        string $loggedUserName,
        bool $extractAllIds = false
    ): array {
        $parentsIds = [];
        foreach ($fields as $field) {
            if (
                $field instanceof CheckboxEntryField ||
                $field instanceof RadioEntryField ||
                $field instanceof SelectEntryField
            ) {
                $parentEntries = ($field instanceof CheckboxEntryField)
                    ? $field->getValues($entry)
                    : (
                        !empty($entry[$field->getPropertyName()])
                        ? [$entry[$field->getPropertyName()]]
                        : []
                    );
                $parentsForm = strval($field->getLinkedObjectName());
                foreach ($parentEntries as $parentEntry) {
                    if ($this->isParentAdmin($parentEntry, $suffix, $loggedUserName, $parentsForm) &&
                            !in_array($parentEntry, $parentsIds)) {
                        $parentsIds[] = $parentEntry;
                        if (!$extractAllIds) {
                            return $parentsIds;
                        }
                    }
                }
            }
        }
        return $parentsIds;
    }

    private function isParentAdmin(string $entryId, string $suffix, string $loggedUserName, string $parentsForm): bool
    {
        if (!$this->isParent($entryId, $parentsForm) ||
            !$this->aclService->hasAccess('read', $entryId, $loggedUserName)) {
            return false;
        } elseif ($this->wiki->UserIsAdmin($loggedUserName)) {
            return true;
        } else {
            $parentOwner = $this->pageManager->getOwner($entryId);
            $groupName = "{$entryId}$suffix";
            $groupAcl = $this->wiki->GetGroupACL($groupName);
            return ((!empty($parentOwner) && $parentOwner == $loggedUserName) ||
                (!empty($groupAcl) && $this->aclService->check($groupAcl, $loggedUserName, true)));
        }
    }

    public function getParentsWhereAdmin(string $formId, string $suffix, string $loggedUserName): array
    {
        $parentsWhereOwner = $this->getParentsWhereOwner(['name'=>$loggedUserName], $formId);
        $parentsWhereAdminIds = $this->getParentsWhereAdminIds(
            $parentsWhereOwner,
            ['name'=>$loggedUserName],
            $suffix,
            $formId
        );

        return array_filter(array_map(function ($entryId) use ($formId) {
            return empty($entryId) ? null : $this->getParent($formId, $entryId);
        }, $parentsWhereAdminIds), function ($entry) use ($loggedUserName) {
            return !empty($entry) && !empty($entry['id_fiche']) && $this->aclService->hasAccess('read', $entry['id_fiche'], $loggedUserName);
        });
    }

    /**
     * @param array $entries
     * @param bool $entriesMode
     * @param string $suffix
     * @param string $parentFormId
     * @param callable $extractExtraFields function (array &$formCache, string $formId, $user)
     * @param string $keyIntoAppendData
     * @param callable $callbackForAppendData function (array $entry, array $form, string $suffix, $user)
     * @param callable $extraCallback function (array $entry, array &$results, array $formData, $user)
     * @return array ['form'=>array,'enumEntryFields'=>array,...]
     */
    public function filterEntriesFromParents(
        array $entries,
        bool $entriesMode = true,
        string $suffix =  '',
        string $parentFormId =  '',
        $extractExtraFields = null,
        string $keyIntoAppendData = '',
        $callbackForAppendData = null,
        $extraCallback = null,
        bool $extractAllIds = false
    ): array {
        if ($this->wiki->UserIsAdmin() && $entriesMode && !$extractAllIds) {
            return $entries;
        } else {
            if (empty($suffix)) {
                return [];
            } else {
                $user = $this->userManager->getLoggedUser();
                if (empty($user['name'])) {
                    return [];
                }
                $results = [];
                $formCache = [];
                foreach ($entries as $key => $value) {
                    if ($entriesMode) {
                        $entry = $value;
                    } elseif ($this->entryManager->isEntry($value)) {
                        $entry = $this->entryManager->getOne($value);
                    }
                    if (!empty($entry['id_typeannonce'])) {
                        $formId = $entry['id_typeannonce'];
                        $formData = $this->extractFields($formId, $formCache, $user, $parentFormId, $extractExtraFields);
                        if (!empty($formData)) {
                            if (!empty($formData['enumEntryFields'])) {
                                $parentsIds = $this->isAdminOfParent(
                                    $entry,
                                    $formData['enumEntryFields'],
                                    $suffix,
                                    $user['name'],
                                    $extractAllIds
                                );
                                if (!empty($parentsIds)) {
                                    $this->appendEntryWithData(
                                        $entry,
                                        $results,
                                        $keyIntoAppendData,
                                        $parentsIds,
                                        function ($internalEntry) use ($formData, $suffix, $user, $callbackForAppendData) {
                                            return (is_callable($callbackForAppendData))
                                              ? $callbackForAppendData($internalEntry, $formData['form'], $suffix, $user)
                                              : $internalEntry;
                                        }
                                    );
                                }
                            }
                            if (is_callable($extraCallback)) {
                                $extraCallback($entry, $results, $formData, $user);
                            }
                        }
                    }
                }
                return $results;
            }
        }
    }

    /**
     * @param scalar $formId
     * @param array &$formCache
     * @param $user
     * @param string $parentFormId
     * @param callable $extractExtraFields function (array &$formCache, string $formId, $user)
     * @return array ['form'=>array,'enumEntryFields'=>array,...]
     */
    protected function extractFields($formId, array &$formCache, $user, string $parentFormId = '', $extractExtraFields = null): array
    {
        if (empty($formId) || !is_scalar($formId) || strval($formId) != strval(intval($formId)) || intval($formId)<0) {
            return [];
        } elseif (!isset($formCache[$formId])) {
            $formCache[$formId] = [];
            $formCache[$formId]['form'] = $this->formManager->getOne($formId);
            if (empty($formCache[$formId]['form']['prepared'])) {
                $formCache[$formId]['form'] = [];
            } else {
                $formCache[$formId]['enumEntryFields'] = $this->extractEnumFieldsFromForm($formCache[$formId]['form'], $parentFormId);
                if (is_callable($extractExtraFields)) {
                    $extractExtraFields($formCache, $formId, $user);
                }
            }
        }
        return $formCache[$formId];
    }

    /**
     * append data for filter to entries in results
     */
    public function appendEntryWithData(
        array $entry,
        array &$results,
        string $key,
        $ids,
        $callback
    ) {
        if (!in_array($entry['id_fiche'], array_keys($results))) {
            $results[$entry['id_fiche']] =  is_callable($callback) ? $callback($entry) : $entry;
        }
        if (!empty($key)) {
            if (!isset($results[$entry['id_fiche']][$key])) {
                $results[$entry['id_fiche']][$key] = [];
            }
            foreach ($ids as $id) {
                if (!in_array($id, $results[$entry['id_fiche']][$key])) {
                    $results[$entry['id_fiche']][$key][] = $id;
                }
            }
        }
    }
}

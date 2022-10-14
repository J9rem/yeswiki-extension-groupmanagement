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
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Entity\User;
use YesWiki\Wiki;
use YesWiki\Zfuture43\Entity\User as Zfuture43User;

class GroupManagementService
{
    protected $entryManager;
    protected $params;
    protected $wiki;
    private $authorizedParents;
    private $parents;

    public function __construct(EntryManager $entryManager, ParameterBagInterface $params, Wiki $wiki)
    {
        $this->authorizedParents = null;
        $this->entryManager = $entryManager;
        $this->params = $params;
        $this->parents = [];
        $this->wiki = $wiki;
    }

    public function getParentsWhereOwner($user, $formId): array
    {
        if (empty($formId) ||
            !is_scalar($formId) ||
            !(strval($formId) != strval(intval($formId))) ||
            empty($user) ||
            !(is_array($user) || $user instanceof User || $user instanceof Zfuture43User)||
            empty($user['name'])) {
            return [];
        }
        $entries = $this->entryManager->search([
            'formsIds' => [$formId],
            'user' => $user['name'],
        ]+$this->getAuthorizedParentsOptions(), true, true);
        return empty($entries) ? [] : array_values(array_map(function ($entry) {
            return $entry['id_fiche'];
        }, $entries));
    }

    public function getParentsWhereAdmin(array $parentsWhereOwner, array $user, string $groupSuffix, string $parentsForm): array
    {
        if (empty($groupSuffix) ||
            empty($parentsForm) ||
            empty($user) ||
            !(is_array($user) || $user instanceof User || $user instanceof Zfuture43User) ||
            empty($user['name']) ||
            count(array_filter($parentsWhereOwner, function ($item) {
                return is_string($item);
            }))>0) {
            return [];
        }

        $parentsWhereAdmin = $parentsWhereOwner;

        $groupsWherePresent = array_filter($this->groupsWithSuffix, function ($groupName) use ($user) {
            return $this->wiki->UserIsInGroup($groupName, $user['name'], false);
        });

        $associatedEntries = array_filter(array_map(function ($groupName) {
            return substr($groupName, 0, -strlen($groupSuffix));
        }, $groupsWherePresent), function ($entryId) {
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
        return (!isset($this->parents[$parentsForm]) || empty($this->parents[$parentsForm][$tag]))
            ? []
            : $this->parents[$parentsForm][$tag] ;
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
            return [];
        }
        if (isset($this->parents[$parentsForm])) {
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
}

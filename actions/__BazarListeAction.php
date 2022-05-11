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
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;

class __BazarListeAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        $keepOnlyEntriesWhereCanEdit = $this->formatBoolean($arg, false, 'keeponlyentrieswherecanedit');
        if (!$keepOnlyEntriesWhereCanEdit) {
            return [];
        } else {
            $userManager = $this->getService(UserManager::class);
            $aclService = $this->getService(AclService::class);
            $user = $userManager->getLoggedUser();
            if (empty($user)) {
                return $this->appendIdFicheInQuery($arg, "");
            }

            $ids = (isset($arg['id']) && is_array($arg['id'])) ? $ids : explode(',', (
                isset($arg['id'])
                ? (is_scalar($arg['id']) ? $arg['id'] : "")
                : (
                    isset($arg['idtypeannonce']) && is_scalar($arg['idtypeannonce'])
                    ? $arg['idtypeannonce']
                    : ""
                )
            ));
            $ids = array_filter(
                $ids,
                function ($id) {
                    return strval($id) == strval(intval($id));
                }
            );
            if (empty($ids)) {
                return $this->appendIdFicheInQuery($arg, "");
            }
            $entryManager = $this->getService(EntryManager::class);
            $entries = $entryManager->search([
                'formsIds' => $ids
            ], true, true);
            if (empty($entries)) {
                return $this->appendIdFicheInQuery($arg, "");
            }
            $entries = array_filter($entries, function ($entry) use ($user, $aclService) {
                return $aclService->hasAccess('write', $entry['id_fiche'], $user['name']);
            });
            $entriesList = implode(',', array_values(array_map(function ($entry) {
                return $entry['id_fiche'];
            }, $entries)));
            
            return $this->appendIdFicheInQuery($arg, $entriesList);
        }
    }

    public function run()
    {
    }

    private function appendIdFicheInQuery($arg, string $entriesList): array
    {
        if (!is_array($arg)) {
            $arg = [];
        }
        return [
            'query' => !empty($arg['query'])
                ? (
                    is_array($arg['query'])
                    ? array_merge($arg['query'], [
                            'id_fiche' => (!empty($arg['query']['id_fiche']) ? $arg['query']['id_fiche'] . "," : "") . $entriesList
                        ])
                    : $arg['query'] . "id_fiche=$entriesList"
                )
                : [
                    'id_fiche' => $entriesList
                ]
                
        ];
    }
}

<?php

/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Groupmanagement\Controller;

use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Bazar\Controller\ApiController as BazarApiController;
use YesWiki\Core\YesWikiController;
use YesWiki\Groupmanagement\Controller\GroupController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/entries/bazarlist", methods={"GET"}, options={"acl":{"public"}},priority=4)
     */
    public function getBazarListData()
    {
        $keepOnlyEntriesWhereCanEdit = in_array($_GET['keeponlyentrieswherecanedit'] ?? false, [1,true,"1","true"], true);
        if ($keepOnlyEntriesWhereCanEdit) {
            $formsIds = $_GET['idtypeannonce'] ?? [];
            if (!empty($formsIds) && is_array($formsIds)) {
                $formsIds = array_map(
                    function ($id) {
                        return strval($id);
                    },
                    array_filter($formsIds, function ($id) {
                        return is_scalar($id) && strval($id) == strval(intval($id)) && intval($id) > 0;
                    })
                );
                if (!empty($formsIds)) {
                    $ids = $this->getService(GroupController::class)->getWritableEntriesIds($formsIds);
                    if (empty($ids)) {
                        $_GET['query']['id_fiche']="";
                    } else {
                        $rawIds = !empty($_GET['query']['id_fiche']) ? explode(',', $_GET['query']['id_fiche']) : [];
                        if (empty($rawIds)) {
                            $newIds = $ids;
                        } else {
                            $newIds = [];
                            foreach ($rawIds as $id) {
                                if (in_array($id, $ids)) {
                                    $newIds[] = $id;
                                }
                            }
                        }
                        $_GET['query']['id_fiche'] = implode(',', $newIds);
                    }
                }
            }
        }
        return $this->getService(BazarApiController::class)->getBazarListData();
    }
}

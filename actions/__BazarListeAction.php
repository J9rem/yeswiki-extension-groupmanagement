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

use YesWiki\Core\YesWikiAction;
use YesWiki\Groupmanagement\Controller\GroupController;

class __BazarListeAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        $keepOnlyEntriesWhereCanEdit = $this->formatBoolean($arg, false, 'keeponlyentrieswherecanedit');
        return $this->getService(GroupController::class)->defineBazarListeActionParams(
            $arg,
            $_GET ?? [],
            function (bool $isDynamic, bool $isAdmin, array $_arg) use ($keepOnlyEntriesWhereCanEdit) {
                $replaceTemplate = !$isDynamic && $keepOnlyEntriesWhereCanEdit && !$isAdmin;
                $options = [
                    'keeponlyentrieswherecanedit' => ($replaceTemplate
                        ? true
                        : (!$isAdmin && $isDynamic && $keepOnlyEntriesWhereCanEdit))
                ];
                return compact(['replaceTemplate','options']);
            }
        );
    }

    public function run()
    {
    }
}

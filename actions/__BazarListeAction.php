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

use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiAction;

class __BazarListeAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        $keepOnlyEntriesWhereCanEdit = $this->formatBoolean($arg, false, 'keeponlyentrieswherecanedit');
        $isDynamic = $this->formatBoolean($arg, false, 'dynamic');
        $templateEngine = $this->getService(TemplateEngine::class);
        $template = $_GET['template'] ?? $arg['template'] ?? null;
        $template = is_string($template) ? $template : null;
        if (($template === 'calendar.tpl.html' && !$templateEngine->hasTemplate("@bazar/{$template}")) ||
            ($template === 'calendar' && !$templateEngine->hasTemplate("@bazar/{$template}.tpl.html"))) {
            $template = "calendar";
            $isDynamic = true;
        }
        if ($isDynamic || !$keepOnlyEntriesWhereCanEdit || $this->wiki->UserIsAdmin()) {
            return [
                'keeponlyentrieswherecanedit' => (!$this->wiki->UserIsAdmin() && $isDynamic && $keepOnlyEntriesWhereCanEdit)
            ];
        } else {
            $newTemplate = in_array($template, ["map","map.tpl.html","gogocarto","gogocarto.tpl.html"]) ? $template : "groupmanagement_pre_template.tpl.html";
            return [
                'keeponlyentrieswherecanedit' => $keepOnlyEntriesWhereCanEdit,
                'template' => $newTemplate,
                'previous-template' => $template,
            ];
        }
    }

    public function run()
    {
    }
}
